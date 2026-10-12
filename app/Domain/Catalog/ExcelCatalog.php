<?php

namespace App\Domain\Catalog;

use DOMDocument;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Options as WriterOptions;
use OpenSpout\Writer\XLSX\Writer;
use ZipArchive;

final class ExcelCatalog
{
    public const MAX_ROWS = 2000;

    public const HEADERS = ['Nombre', 'Descripción', 'Categoría', 'Imagen', 'SKU'];

    private const COLUMNS = ['sku' => 'sku', 'codigo' => 'sku', 'nombre' => 'name', 'categoria' => 'category', 'marca' => 'brand', 'resumen' => 'summary', 'descripcion' => 'description', 'unidad' => 'unit', 'estado' => 'status', 'existencias' => 'stock', 'stock' => 'stock', 'precio interno' => 'private_price_crc', 'etiquetas' => 'tags_text', 'imagen' => 'image'];

    public function directory(): string
    {
        $directory = storage_path('app/private/catalog-imports');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            throw new \RuntimeException('No se pudo preparar el almacenamiento privado de Excel.');
        }

        return $directory;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    private function inspect(string $path): void
    {
        if (! is_file($path) || filesize($path) > CatalogFileLimits::MAX_BYTES) {
            $this->fail('El Excel supera el límite de 128 MB.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            $this->fail('El archivo no es un Excel .xlsx válido.');
        }
        try {
            if ($zip->numFiles > 8192 || $zip->locateName('xl/workbook.xml') === false) {
                $this->fail('Usa un Excel sencillo basado en la plantilla JMAC.');
            }
            $total = $xmlTotal = 0;
            $names = $printerSettings = [];
            $contentTypes = '';
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $entry['name'];
                $total += $entry['size'];
                if (isset($names[$name])) {
                    $this->fail('El Excel contiene partes internas repetidas.');
                }
                $names[$name] = true;
                if (str_contains($name, '..') || str_contains($name, '\\') || str_starts_with($name, '/')) {
                    $this->fail('La estructura interna del Excel no es válida. Guarda una copia nueva como .xlsx.');
                }
                if (($entry['encryption_method'] ?? 0) !== 0) {
                    $this->fail('El Excel contiene partes cifradas. Guarda una copia .xlsx sin contraseña.');
                }
                if ($total > 256 * 1024 * 1024) {
                    $this->fail('El Excel supera 256 MB al descomprimirlo, aunque el archivo pese menos. Comprime las fotografías en Excel o divide el inventario en varios archivos.');
                }
                if (preg_match('~vbaProject|\.(?:php|js|exe|svg)$~i', $name)) {
                    $this->fail('El Excel contiene macros o archivos ejecutables no admitidos. Guarda una copia .xlsx sin macros.');
                }
                if (preg_match('~externalLinks|connections\.xml~i', $name)) {
                    $this->fail('El Excel contiene vínculos de datos o conexiones externas. Elimínalos y guarda una copia con valores y fotografías incrustadas.');
                }
                if (preg_match('~embeddings~i', $name)) {
                    $this->fail('El Excel contiene objetos incrustados, como documentos o controles. Elimina esos objetos; las fotografías pegadas en Imagen sí se admiten.');
                }
                if (preg_match('~^xl/printerSettings/printerSettings[1-9][0-9]*\.bin$~D', $name)) {
                    if ($entry['size'] > 256 * 1024) {
                        $this->fail('Los ajustes de impresión del Excel superan 256 KB. Guarda una copia sin ajustes de impresora.');
                    }
                    // Standard OOXML DEVMODE data: validate its declared type below,
                    // but never read, extract, execute or publish its binary bytes.
                    $printerSettings[] = $name;

                    continue;
                }
                if (preg_match('/\.bin$/i', $name)) {
                    $this->fail('El Excel contiene un archivo binario no admitido. Guarda una copia .xlsx sin macros, controles ni objetos incrustados.');
                }
                if (! preg_match('/\.(xml|rels|vml)$/i', $name)) {
                    if (! str_ends_with($name, '/') && ! preg_match('/\.(png|jpe?g|webp|avif)$/i', $name)) {
                        $this->fail('El Excel incluye objetos o archivos no permitidos. Usa la plantilla JMAC.');
                    }

                    if (! str_ends_with($name, '/') && $entry['size'] > 8 * 1024 * 1024) {
                        $this->fail('Una fotografía excede los 8 MB; comprímela antes de subir el Excel.');
                    }

                    continue;
                }
                if ($entry['size'] > 10 * 1024 * 1024) {
                    $this->fail('Una parte XML del Excel supera 10 MB. Copia solo las filas utilizadas a la plantilla o divide el inventario en varios archivos.');
                }
                $xmlTotal += $entry['size'];
                if ($xmlTotal > 16 * 1024 * 1024) {
                    $this->fail('Los datos XML del libro superan 16 MB. Divide el Excel en partes.');
                }
                $xml = $zip->getFromIndex($index, 10 * 1024 * 1024 + 1);
                if ($xml === false || strlen($xml) !== $entry['size'] || ! mb_check_encoding($xml, 'UTF-8') || str_contains($xml, "\0") || preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i', $xml) || (str_starts_with($name, 'xl/worksheets/') && preg_match('/<(?:[\w]+:)?f(?:\s|>)/', $xml))) {
                    $this->fail('El Excel contiene XML inválido o fórmulas. Copia y pega solo valores antes de subirlo.');
                }
                if ($name === '[Content_Types].xml' && stripos($xml, 'macroEnabled') !== false) {
                    $this->fail('No se admiten libros habilitados para macros.');
                }
                if ($name === '[Content_Types].xml') {
                    $contentTypes = $xml;
                }
                if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                    $previous = libxml_use_internal_errors(true);
                    $probe = new \XMLReader;
                    try {
                        if (! $probe->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
                            $this->fail('La hoja Excel no es válida.');
                        }
                        while ($probe->read()) {
                            if ($probe->nodeType !== \XMLReader::ELEMENT) {
                                continue;
                            }
                            $reference = match ($probe->localName) {
                                'c' => $probe->getAttribute('r'),
                                'dimension' => $probe->getAttribute('ref'),
                                default => null,
                            };
                            foreach (explode(':', $reference ?? '') as $coordinate) {
                                if ($coordinate === '') {
                                    continue;
                                }
                                if (! preg_match('/^([A-Z]{1,2})([1-9][0-9]{0,3})$/D', $coordinate, $match)) {
                                    $this->fail('La hoja excede el límite de 2.000 productos y 30 columnas.');
                                }
                                $column = 0;
                                foreach (str_split($match[1]) as $letter) {
                                    $column = $column * 26 + ord($letter) - 64;
                                }
                                if ($column > 30 || (int) $match[2] > self::MAX_ROWS + 1) {
                                    $this->fail('La hoja excede el límite de 2.000 productos y 30 columnas. Copia solo las filas utilizadas a la plantilla.');
                                }
                            }
                            if ($probe->localName === 'row' && (int) $probe->getAttribute('r') > self::MAX_ROWS + 1) {
                                $this->fail('La hoja supera las 2.000 filas de productos.');
                            }
                        }
                        if (libxml_get_errors()) {
                            $this->fail('La hoja Excel contiene XML mal formado.');
                        }
                    } finally {
                        $probe->close();
                        libxml_clear_errors();
                        libxml_use_internal_errors($previous);
                    }
                }
                if (str_ends_with($name, '.rels')) {
                    $document = new DOMDocument;
                    $previous = libxml_use_internal_errors(true);
                    try {
                        $ok = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
                        if (! $ok || $document->doctype) {
                            $this->fail('Las relaciones internas del Excel no son válidas.');
                        }
                        foreach ($document->getElementsByTagNameNS('*', 'Relationship') as $relationship) {
                            if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0 || preg_match('~^[a-z][a-z0-9+.-]*:~i', $relationship->getAttribute('Target'))) {
                                $this->fail('Quita los vínculos externos del Excel antes de importarlo.');
                            }
                            if (preg_match('~/(?:worksheet|sharedStrings|styles|theme)$~', $relationship->getAttribute('Type')) && ! preg_match('/\.xml$/i', $relationship->getAttribute('Target'))) {
                                $this->fail('Las relaciones de datos deben referirse a XML interno del libro.');
                            }
                        }
                    } finally {
                        libxml_clear_errors();
                        libxml_use_internal_errors($previous);
                    }
                }
            }
            if ($printerSettings !== []) {
                $this->validatePrinterSettings($contentTypes, $printerSettings);
            }
        } finally {
            $zip->close();
        }
    }

    private function validatePrinterSettings(string $xml, array $parts): void
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if ($xml === '' || ! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT) || $document->doctype) {
                $this->fail('Los tipos de contenido del Excel no son válidos. Guarda una copia nueva como .xlsx.');
            }
            $namespace = 'http://schemas.openxmlformats.org/package/2006/content-types';
            $binaryType = null;
            $overrides = [];
            foreach ($document->getElementsByTagNameNS($namespace, 'Default') as $entry) {
                if (strtolower($entry->getAttribute('Extension')) === 'bin') {
                    if ($binaryType !== null) {
                        $this->fail('El Excel declara tipos binarios repetidos. Guarda una copia nueva como .xlsx.');
                    }
                    $binaryType = $entry->getAttribute('ContentType');
                }
            }
            foreach ($document->getElementsByTagNameNS($namespace, 'Override') as $entry) {
                $part = $entry->getAttribute('PartName');
                if (isset($overrides[$part])) {
                    $this->fail('El Excel declara tipos de contenido repetidos. Guarda una copia nueva como .xlsx.');
                }
                $overrides[$part] = $entry->getAttribute('ContentType');
            }
            foreach ($parts as $part) {
                if (($overrides['/'.$part] ?? $binaryType) !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.printerSettings') {
                    $this->fail('Los ajustes de impresión del Excel tienen un tipo no admitido. Guarda una copia .xlsx sin macros ni objetos incrustados.');
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function read(string $path): array
    {
        $this->inspect($path);
        $options = new ReaderOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->setTempFolder($this->directory());
        $reader = new Reader($options);
        $rows = $errors = $seen = [];
        $columns = null;
        $bytes = 0;
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    if ($line > self::MAX_ROWS + 1 || $row->getNumCells() > 30) {
                        $this->fail('Máximo 2.000 productos y 30 columnas por archivo. Divide el Excel en partes.');
                    }
                    $values = [];
                    foreach ($row->getCells() as $cell) {
                        $value = $cell->getValue();
                        if ($cell instanceof FormulaCell || (! is_scalar($value) && $value !== null)) {
                            $this->fail('Usa texto o números, sin fórmulas ni fechas en las columnas de productos.');
                        }
                        $values[] = trim((string) ($value ?? ''));
                    }
                    if (! array_filter($values, fn ($value) => $value !== '')) {
                        continue;
                    }
                    if ($columns === null) {
                        $columns = [];
                        foreach ($values as $index => $header) {
                            if ($header === '') {
                                continue;
                            }
                            $key = Str::ascii(mb_strtolower($header));
                            if (! isset(self::COLUMNS[$key]) || in_array(self::COLUMNS[$key], $columns, true)) {
                                $this->fail('Las columnas no corresponden a la plantilla JMAC o están repetidas. Descarga la plantilla nueva; no uses exportaciones WordPress.');
                            }
                            $columns[$index] = self::COLUMNS[$key];
                        }
                        if (! in_array('sku', $columns, true) || ! in_array('name', $columns, true)) {
                            $this->fail('La primera fila debe tener SKU y Nombre.');
                        }

                        continue;
                    }
                    $fields = [];
                    foreach ($columns as $index => $field) {
                        $fields[$field] = ($values[$index] ?? '') === '' ? null : $values[$index];
                    }
                    $fields['sku'] = mb_strtoupper($fields['sku'] ?? '');
                    if (array_key_exists('status', $fields)) {
                        $status = mb_strtolower($fields['status'] ?? 'borrador');
                        $fields['status'] = ['borrador' => 'draft', 'publicado' => 'published', 'oculto' => 'hidden', 'archivado' => 'archived'][$status] ?? $status;
                    }
                    if (array_key_exists('unit', $fields)) {
                        $fields['unit'] ??= 'unidad';
                    }
                    if (! empty($fields['private_price_crc'])) {
                        $fields['private_price_crc'] = str_replace(',', '.', $fields['private_price_crc']);
                    }
                    $validation = Validator::make($fields, ['sku' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9 ._+\/\-]*$/'], 'name' => 'required|string|max:180', 'category' => 'nullable|string|max:180', 'brand' => 'nullable|string|max:180', 'summary' => 'nullable|string|max:2000', 'description' => 'nullable|string|max:6000', 'unit' => 'sometimes|required|string|max:50', 'status' => 'sometimes|required|in:draft,published,hidden,archived', 'stock' => 'nullable|integer|min:0|max:99999999', 'private_price_crc' => 'nullable|numeric|min:0|max:999999999999', 'tags_text' => 'nullable|string|max:1000', 'image' => 'nullable|string|max:2000']);
                    $validation->setAttributeNames(['sku' => 'SKU', 'name' => 'Nombre', 'category' => 'Categoría', 'brand' => 'Marca', 'summary' => 'Resumen', 'description' => 'Descripción', 'unit' => 'Unidad', 'status' => 'Estado', 'stock' => 'Existencias', 'private_price_crc' => 'Precio interno', 'tags_text' => 'Etiquetas']);
                    if ($validation->fails()) {
                        $errors[] = ['row_number' => $line, 'safe_error' => implode(' ', $validation->errors()->all())];
                    } elseif (isset($seen[$fields['sku']])) {
                        $errors[] = ['row_number' => $line, 'safe_error' => 'SKU repetido en el mismo Excel. Cada código debe aparecer una sola vez.'];
                    } else {
                        $seen[$fields['sku']] = true;
                        $rows[] = ['line' => $line, 'fields' => $fields];
                        $bytes += strlen(json_encode($fields, JSON_THROW_ON_ERROR));
                        if ($bytes > 8 * 1024 * 1024 || count($rows) > self::MAX_ROWS) {
                            $this->fail('El contenido supera el límite de importación. Divide el Excel en partes.');
                        }
                    }
                }
                break; // Only the first worksheet contains product data.
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->fail('No se pudo leer el Excel. Guárdalo como .xlsx, con la plantilla JMAC y solo valores.');
        } finally {
            $reader->close();
        }
        if ($rows === [] && $errors === []) {
            $this->fail('El Excel está vacío. Añade al menos un producto nuevo; no se reemplaza el catálogo con una hoja vacía.');
        }

        $pictures = [];
        $imageColumn = $columns !== null ? array_search('image', $columns, true) : false;
        if ($imageColumn !== false && $errors === []) {
            $pictures = app(ExcelPictures::class)->map($path, $imageColumn, array_fill_keys(array_column($rows, 'line'), true));
        }
        foreach ($rows as &$row) {
            $text = $row['fields']['image'] ?? null;
            unset($row['fields']['image']);
            $row['photos'] = $pictures[$row['line']] ?? [];
            if ($text && $row['photos'] === []) {
                $errors[] = ['row_number' => $row['line'], 'safe_error' => 'Imagen debe contener una fotografía incrustada, no un enlace o nombre de archivo. No se realizan descargas externas.'];
            }
        }
        unset($row);

        return ['rows' => $rows, 'errors' => $errors];
    }

    public function template(): string
    {
        $path = $this->directory().'/plantilla-'.Str::uuid().'.xlsx';
        $options = new WriterOptions;
        $options->setTempFolder($this->directory());
        $writer = new Writer($options);
        $writer->openToFile($path);
        try {
            $writer->getCurrentSheet()->setName('Productos')->setColumnWidth(24, ...range(1, count(self::HEADERS)));
            $writer->addRow(Row::fromValues(self::HEADERS, (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('174B91')));
            $writer->addRow(Row::fromValuesWithStyles(array_fill(0, count(self::HEADERS), ''), columnStyles: [4 => (new Style)->setFormat('@')]));
            $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');
            foreach (['JMAC 2.0 — catálogo nuevo', 'Completa la primera hoja Productos. No borres ni dupliques los encabezados.', 'SKU y Nombre son obligatorios. Formatea SKU como Texto para conservar ceros iniciales.', 'Máximo 2.000 productos y archivo de 128 MB, sujeto al límite PHP del hosting. Sin fórmulas, macros ni vínculos externos.', 'Estado: borrador, publicado, oculto o archivado. Vacío significa borrador.', 'Precio interno es privado; no se publican precios ni se cobran pagos.', 'Sumar: agrega códigos nuevos y actualiza los existentes; una columna omitida conserva su valor anterior.', 'Reemplazar: deja activo solo el Excel y retira a papelera los códigos ausentes. Conserva preventas e imágenes de los SKU coincidentes.', 'Pega fotografías en la columna Imagen y en la fila del SKU: se procesan como candidatos privados hasta su aprobación.', 'No utilices el CSV histórico ni exportaciones del WordPress anterior.'] as $instruction) {
                $writer->addRow(Row::fromValues([$instruction]));
            }
        } finally {
            $writer->close();
        }
        chmod($path, 0600);

        return $path;
    }
}
