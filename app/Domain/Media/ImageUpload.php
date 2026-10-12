<?php

namespace App\Domain\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ImageUpload
{
    public function prepare(UploadedFile $file): array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp') || ! (imagetypes() & IMG_WEBP)) {
            throw ValidationException::withMessages(['file' => 'El hosting necesita GD con soporte WebP para preparar fotografías. Revisa Diagnóstico.']);
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (defined('IMG_AVIF') && (imagetypes() & IMG_AVIF)) {
            $allowed[] = 'image/avif';
        }
        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
        if (! $file->isValid() || $file->getSize() > 8 * 1024 * 1024 || ! in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)) {
            $this->reject();
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $size = @getimagesize($file->getRealPath());
        if (! in_array($mime, $allowed, true) || ! $size || $size[0] * $size[1] > 20_000_000) {
            $this->reject();
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            $this->reject();
        }
        try {
            $scale = min(1, 1920 / max($size[0], $size[1]));
            $width = max(1, (int) round($size[0] * $scale));
            $height = max(1, (int) round($size[1] * $scale));
            $clean = imagecreatetruecolor($width, $height);
            imagealphablending($clean, false);
            imagesavealpha($clean, true);
            imagecopyresampled($clean, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
            ob_start();
            imagewebp($clean, null, 85);
            $bytes = ob_get_clean();
            imagedestroy($clean);
            unset($clean);
        } finally {
            imagedestroy($image);
            unset($image);
        }
        $thumbnail = imagecreatefromstring($bytes);
        $thumbWidth = min(480, $width);
        $thumbHeight = max(1, (int) round($height * $thumbWidth / $width));
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $thumbnail, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        ob_start();
        imagewebp($thumb, null, 82);
        $thumbBytes = ob_get_clean();
        imagedestroy($thumb);
        imagedestroy($thumbnail);
        unset($thumb, $thumbnail);

        return ['bytes' => $bytes, 'thumb_bytes' => $thumbBytes, 'width' => $width, 'height' => $height, 'thumb_width' => $thumbWidth, 'thumb_height' => $thumbHeight];
    }

    public function store(UploadedFile $file, int $actorId, string $alt): int
    {
        return $this->storePrepared($this->prepare($file), $actorId, $alt)['id'];
    }

    public function storePrepared(array $image, int $actorId, string $alt): array
    {
        $path = 'media/'.Str::uuid().'.webp';
        $thumbPath = 'media/'.Str::uuid().'-thumb.webp';
        try {
            if (! Storage::disk('local')->put($path, $image['bytes']) || ! Storage::disk('local')->put($thumbPath, $image['thumb_bytes'])) {
                throw new \RuntimeException('No se pudo guardar la fotografía privada.');
            }
            $id = DB::table('media')->insertGetId([
                'disk' => 'local', 'path' => $path, 'mime' => 'image/webp', 'bytes' => strlen($image['bytes']),
                'width' => $image['width'], 'height' => $image['height'], 'alt' => $alt, 'checksum' => hash('sha256', $image['bytes']),
                'variants_json' => json_encode(['thumb' => ['path' => $thumbPath, 'width' => $image['thumb_width'], 'height' => $image['thumb_height']]]),
                'status' => 'candidate', 'uploaded_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['id' => $id, 'paths' => [$path, $thumbPath]];
        } catch (\Throwable $e) {
            Storage::disk('local')->delete([$path, $thumbPath]);
            throw $e;
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['file' => 'Sube una imagen JPEG, PNG o WebP válida, de hasta 8 MB y 20 megapíxeles.']);
    }
}
