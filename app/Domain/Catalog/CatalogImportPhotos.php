<?php

namespace App\Domain\Catalog;

use App\Domain\Media\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Encrypted staged photos, prepared in finite HTTP requests before confirmation. */
final class CatalogImportPhotos
{
    public function sourcePath(array $mapping): string
    {
        return 'catalog-imports/'.$this->token($mapping).'.xlsx';
    }

    private function token(array $mapping): string
    {
        $token = $mapping['token'] ?? '';
        abort_unless(is_string($token) && preg_match('/^[a-f0-9-]{36}$/D', $token), 404);

        return $token;
    }

    private function preparedPath(array $mapping, int $index): string
    {
        abort_unless($index >= 0 && $index < count($mapping['photo_parts'] ?? []), 404);

        return 'catalog-imports/'.$this->token($mapping).'-photo-'.$index.'.jci';
    }

    public function stage(UploadedFile $file, array $mapping): void
    {
        $destination = Storage::disk('local')->path($this->sourcePath($mapping));
        if (! copy($file->getRealPath(), $destination) || ! chmod($destination, 0600)) {
            throw ValidationException::withMessages(['file' => 'No se pudo guardar temporalmente el libro privado.']);
        }
    }

    public function prepare(array $mapping): int
    {
        $parts = $mapping['photo_parts'] ?? [];
        $offset = (int) ($mapping['photos_prepared'] ?? 0);
        $source = Storage::disk('local')->path($this->sourcePath($mapping));
        if (! is_file($source) || ! hash_equals($mapping['source_sha256'], hash_file('sha256', $source))) {
            throw ValidationException::withMessages(['file' => 'El libro temporal no está disponible o cambió. Vuelve a subirlo.']);
        }
        $zip = new ZipArchive;
        if ($zip->open($source, ZipArchive::CHECKCONS) !== true) {
            throw ValidationException::withMessages(['file' => 'No se pudo abrir el libro temporal.']);
        }
        $started = microtime(true);
        try {
            $directory = app(ExcelCatalog::class)->directory();
            while ($offset < count($parts) && $offset < (int) ($mapping['photos_prepared'] ?? 0) + 10) {
                $part = $parts[$offset];
                if (! is_string($part) || ! preg_match('~^xl/media/[a-zA-Z0-9_.-]+\.(png|jpe?g|webp|avif)$~i', $part)) {
                    throw ValidationException::withMessages(['file' => 'Ruta de fotografía inválida.']);
                }
                $stat = $zip->statName($part);
                if (! $stat || $stat['size'] > 8 * 1024 * 1024) {
                    throw ValidationException::withMessages(['file' => 'Una fotografía no está disponible o supera 8 MB.']);
                }
                $bytes = $zip->getFromName($part, 8 * 1024 * 1024 + 1);
                if ($bytes === false || strlen($bytes) !== $stat['size']) {
                    throw ValidationException::withMessages(['file' => 'No se pudo extraer una fotografía.']);
                }
                $temporary = tempnam($directory, 'image-');
                try {
                    file_put_contents($temporary, $bytes);
                    chmod($temporary, 0600);
                    unset($bytes);
                    $image = app(ImageUpload::class)->prepare(new UploadedFile($temporary, basename($part), null, null, true));
                    $image['bytes'] = base64_encode($image['bytes']);
                    $image['thumb_bytes'] = base64_encode($image['thumb_bytes']);
                    $cipher = Crypt::encryptString(json_encode($image, JSON_THROW_ON_ERROR));
                    $path = $this->preparedPath($mapping, $offset);
                    if (! Storage::disk('local')->put($path, $cipher)) {
                        throw ValidationException::withMessages(['file' => 'No se pudo guardar la fotografía procesada.']);
                    }
                    chmod(Storage::disk('local')->path($path), 0600);
                    unset($image, $cipher);
                    $offset++;
                } finally {
                    if (is_file($temporary)) {
                        unlink($temporary);
                    }
                }
                if (microtime(true) - $started >= 5) {
                    break;
                }
            }
        } finally {
            $zip->close();
        }

        return $offset;
    }

    public function image(array $mapping, int $index): array
    {
        try {
            $contents = Storage::disk('local')->get($this->preparedPath($mapping, $index));
            $image = json_decode(Crypt::decryptString($contents ?? ''), true, 8, JSON_THROW_ON_ERROR);
            foreach (['bytes', 'thumb_bytes'] as $field) {
                $image[$field] = base64_decode($image[$field], true);
                if (! is_string($image[$field]) || strlen($image[$field]) > 8 * 1024 * 1024 || ! str_starts_with($image[$field], 'RIFF') || substr($image[$field], 8, 4) !== 'WEBP') {
                    throw new \RuntimeException('Invalid prepared photo');
                }
            }

            return $image;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['file' => 'Una fotografía preparada ya no está disponible. Vuelve a analizar el Excel.']);
        }
    }

    public function index(array $mapping, string $part): int
    {
        $index = array_search($part, $mapping['photo_parts'] ?? [], true);
        abort_unless($index !== false, 422, 'Fotografía no asociada al lote.');

        return $index;
    }

    public function cleanup(array $mapping): void
    {
        Storage::disk('local')->delete($this->sourcePath($mapping));
        if (isset($mapping['batch_id']) && is_int($mapping['batch_id'])) {
            Storage::disk('local')->delete('catalog-imports/prepare-'.$mapping['batch_id'].'.lock');
        }
        foreach ($mapping['photo_parts'] ?? [] as $index => $part) {
            Storage::disk('local')->delete($this->preparedPath($mapping, $index));
        }
    }
}
