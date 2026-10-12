<?php

namespace App\Domain\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ImageUpload
{
    public function store(UploadedFile $file, int $actorId, string $alt): int
    {
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
            $width = min(1920, $size[0]);
            $height = max(1, (int) round($size[1] * $width / $size[0]));
            $clean = imagecreatetruecolor($width, $height);
            imagealphablending($clean, false);
            imagesavealpha($clean, true);
            imagecopyresampled($clean, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
            ob_start();
            imagewebp($clean, null, 85);
            $bytes = ob_get_clean();
            imagedestroy($clean);
        } finally {
            imagedestroy($image);
        }
        $path = 'media/'.Str::uuid().'.webp';
        Storage::disk('local')->put($path, $bytes);
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
        $thumbPath = 'media/'.Str::uuid().'-thumb.webp';
        Storage::disk('local')->put($thumbPath, $thumbBytes);
        try {
            return DB::table('media')->insertGetId([
                'disk' => 'local', 'path' => $path, 'mime' => 'image/webp', 'bytes' => strlen($bytes),
                'width' => $width, 'height' => $height, 'alt' => $alt, 'checksum' => hash('sha256', $bytes),
                'variants_json' => json_encode(['thumb' => ['path' => $thumbPath, 'width' => $thumbWidth, 'height' => $thumbHeight]]),
                'status' => 'candidate', 'uploaded_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
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
