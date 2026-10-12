<?php

namespace App\Domain\Catalog;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Read internal OOXML image relationships; never fetch or execute a URL. */
final class ExcelPictures
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    private function xml(ZipArchive $zip, string $part): DOMDocument
    {
        $bytes = $zip->getFromName($part, 10 * 1024 * 1024 + 1);
        if ($bytes === false || strlen($bytes) > 10 * 1024 * 1024 || ! mb_check_encoding($bytes, 'UTF-8') || str_contains($bytes, "\0") || preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i', $bytes)) {
            $this->fail('Las referencias internas de las fotografías no son válidas.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($bytes, LIBXML_NONET | LIBXML_COMPACT) || $document->doctype) {
                $this->fail('El Excel tiene referencias de imágenes mal formadas.');
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function relative(string $source, string $target): string
    {
        if ($target === '' || str_contains($target, '\\') || str_contains($target, '%') || str_contains($target, '#') || str_contains($target, '?') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $target)) {
            $this->fail('Las fotografías deben estar incrustadas dentro del Excel.');
        }
        $segments = str_starts_with($target, '/') ? [] : explode('/', dirname($source));
        foreach (explode('/', ltrim($target, '/')) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    $this->fail('Ruta interna de imagen inválida.');
                }
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }
        $part = implode('/', $segments);
        if (! str_starts_with($part, 'xl/')) {
            $this->fail('La imagen no pertenece al libro Excel.');
        }

        return $part;
    }

    private function relationships(ZipArchive $zip, string $source): array
    {
        $part = dirname($source).'/_rels/'.basename($source).'.rels';
        if ($zip->locateName($part) === false) {
            return [];
        }
        $result = [];
        foreach ($this->xml($zip, $part)->getElementsByTagNameNS('*', 'Relationship') as $relationship) {
            if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0) {
                $this->fail('No se descargan fotografías ni vínculos externos desde el Excel.');
            }
            $id = $relationship->getAttribute('Id');
            if ($id === '' || isset($result[$id])) {
                $this->fail('Relaciones repetidas o inválidas en el Excel.');
            }
            $result[$id] = ['part' => $this->relative($source, $relationship->getAttribute('Target')), 'type' => $relationship->getAttribute('Type')];
        }

        return $result;
    }

    private function rid(\DOMElement $element, string $attribute = 'id'): string
    {
        return $element->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', $attribute)
            ?: $element->getAttributeNS('http://purl.oclc.org/ooxml/officeDocument/relationships', $attribute);
    }

    private function image(ZipArchive $zip, array $relationship): string
    {
        $part = $relationship['part'];
        $stat = $zip->statName($part);
        if (! str_ends_with($relationship['type'], '/image') || ! preg_match('~^xl/media/[a-zA-Z0-9_.-]+\.(?:png|jpe?g|webp|avif)$~i', $part) || ! $stat || $stat['size'] > 8 * 1024 * 1024 || $stat['size'] < 1) {
            $this->fail('Las fotografías incrustadas deben ser JPEG, PNG o WebP de hasta 8 MB.');
        }

        return $part;
    }

    public function map(string $path, int $column, array $productRows): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            $this->fail('No se pudo abrir el libro para leer sus fotografías.');
        }
        try {
            $first = $this->xml($zip, 'xl/workbook.xml')->getElementsByTagNameNS('*', 'sheet')->item(0);
            $books = $this->relationships($zip, 'xl/workbook.xml');
            $sheet = $first ? ($books[$this->rid($first)] ?? null) : null;
            if (! $sheet || ! str_ends_with($sheet['type'], '/worksheet')) {
                $this->fail('No se identificó la primera hoja de productos.');
            }
            $document = $this->xml($zip, $sheet['part']);
            $relations = $this->relationships($zip, $sheet['part']);
            $result = [];
            foreach ($document->getElementsByTagNameNS('*', 'drawing') as $drawing) {
                $drawingPart = $relations[$this->rid($drawing)] ?? null;
                if (! $drawingPart || ! str_ends_with($drawingPart['type'], '/drawing')) {
                    $this->fail('El dibujo de una fotografía no es válido.');
                }
                $pictures = $this->relationships($zip, $drawingPart['part']);
                $xpath = new DOMXPath($this->xml($zip, $drawingPart['part']));
                foreach ($xpath->query('//*[local-name()="oneCellAnchor" or local-name()="twoCellAnchor"]') as $anchor) {
                    $row = (int) $xpath->evaluate('string(./*[local-name()="from"]/*[local-name()="row"])', $anchor) + 1;
                    $col = $xpath->evaluate('string(./*[local-name()="from"]/*[local-name()="col"])', $anchor);
                    if ($col === '' || (int) $col !== $column || ! isset($productRows[$row])) {
                        continue;
                    }
                    foreach ($xpath->query('.//*[local-name()="pic"]//*[local-name()="blip"]', $anchor) as $blip) {
                        $reference = $pictures[$this->rid($blip, 'embed')] ?? null;
                        if (! $reference) {
                            $this->fail('Una fotografía de la fila '.$row.' no tiene su archivo incrustado.');
                        }
                        $result[$row][] = $this->image($zip, $reference);
                    }
                }
            }
            foreach ($this->cellImages($zip, $document, $column, $productRows) as $row => $parts) {
                $result[$row] = [...($result[$row] ?? []), ...$parts];
            }
            foreach ($result as $row => &$parts) {
                $parts = array_values(array_unique($parts));
                if (count($parts) > 5) {
                    $this->fail('Máximo cinco fotografías por SKU; revisa la fila '.$row.'.');
                }
            }
            unset($parts);
            if ($result === []) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (str_starts_with($zip->getNameIndex($i), 'xl/media/')) {
                        $this->fail('Hay fotografías en el Excel, pero no se asociaron a la columna Imagen. Colócalas en esa columna y en la fila de su SKU.');
                    }
                }
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    /** Excel 365 "Place in Cell": vm -> valueMetadata -> rich value -> image relationship. */
    private function cellImages(ZipArchive $zip, DOMDocument $sheet, int $column, array $productRows): array
    {
        if ($zip->locateName('xl/richData/richValueRel.xml') === false || $zip->locateName('xl/metadata.xml') === false) {
            return [];
        }
        $source = 'xl/richData/richValueRel.xml';
        $relations = $this->relationships($zip, $source);
        $imageParts = [];
        foreach ($this->xml($zip, $source)->getElementsByTagNameNS('*', 'rel') as $rel) {
            $reference = $relations[$this->rid($rel)] ?? null;
            if (! $reference) {
                $this->fail('Referencia inválida en una fotografía dentro de celda.');
            }
            $imageParts[] = $this->image($zip, $reference);
        }
        $values = [];
        $structures = $this->xml($zip, 'xl/richData/rdrichvaluestructure.xml')->getElementsByTagNameNS('*', 's');
        foreach ($this->xml($zip, 'xl/richData/rdrichvalue.xml')->getElementsByTagNameNS('*', 'rv') as $rich) {
            $structure = $structures->item((int) $rich->getAttribute('s'));
            $field = null;
            if ($structure && $structure->getAttribute('t') === '_localImage') {
                foreach ($structure->getElementsByTagNameNS('*', 'k') as $index => $key) {
                    if ($key->getAttribute('n') === '_rvRel:LocalImageIdentifier') {
                        $field = $index;
                    }
                }
            }
            $entry = $field !== null ? $rich->getElementsByTagNameNS('*', 'v')->item($field) : null;
            $values[] = $entry && ctype_digit($entry->textContent) ? ($imageParts[(int) $entry->textContent] ?? null) : null;
        }
        $metadata = $this->xml($zip, 'xl/metadata.xml');
        $future = [];
        foreach ($metadata->getElementsByTagNameNS('*', 'futureMetadata') as $entry) {
            if ($entry->getAttribute('name') === 'XLRICHVALUE') {
                foreach ($entry->getElementsByTagNameNS('*', 'bk') as $book) {
                    $value = $book->getElementsByTagNameNS('*', 'rvb')->item(0);
                    $future[] = $value ? ($values[(int) $value->getAttribute('i')] ?? null) : null;
                }
            }
        }
        $types = $metadata->getElementsByTagNameNS('*', 'metadataType');
        $valueMetadata = $metadata->getElementsByTagNameNS('*', 'valueMetadata')->item(0);
        $mapping = [];
        if ($valueMetadata) {
            foreach ($valueMetadata->getElementsByTagNameNS('*', 'bk') as $index => $book) {
                $record = $book->getElementsByTagNameNS('*', 'rc')->item(0);
                if ($record && $types->item((int) $record->getAttribute('t') - 1)?->getAttribute('name') === 'XLRICHVALUE') {
                    $mapping[$index + 1] = $future[(int) $record->getAttribute('v')] ?? null;
                }
            }
        }
        $result = [];
        foreach ($sheet->getElementsByTagNameNS('*', 'c') as $cell) {
            if (! $cell->hasAttribute('vm') || ! preg_match('/^([A-Z]+)([1-9][0-9]*)$/D', $cell->getAttribute('r'), $match)) {
                continue;
            }
            $col = 0;
            foreach (str_split($match[1]) as $letter) {
                $col = $col * 26 + ord($letter) - 64;
            }
            $row = (int) $match[2];
            if ($col - 1 === $column && isset($productRows[$row])) {
                $part = $mapping[(int) $cell->getAttribute('vm')] ?? null;
                if (! $part) {
                    $this->fail('La fotografía dentro de celda de la fila '.$row.' no se pudo asociar.');
                }
                $result[$row][] = $part;
            }
        }

        return $result;
    }
}
