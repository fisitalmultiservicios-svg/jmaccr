<?php

namespace App\Domain\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;

final class PdfUpload
{
    public function store(UploadedFile $file, int $actor, string $alt): int
    {
        if (! $file->isValid() || strtolower($file->getClientOriginalExtension()) !== 'pdf' || $file->getSize() > 8 * 1024 * 1024 || (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) !== 'application/pdf') {
            $this->reject();
        }
        try {
            $pdf = new Fpdi;
            $pages = $pdf->setSourceFile($file->getRealPath());
            if ($pages < 1 || $pages > 80) {
                $this->reject();
            }for ($number = 1; $number <= $pages; $number++) {
                $page = $pdf->importPage($number);
                $size = $pdf->getTemplateSize($page);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($page);
            } $bytes = $pdf->Output('S');
        } catch (\Throwable $exception) {
            $this->reject();
        }
        $path = 'documents/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $bytes);
        try {
            return DB::table('media')->insertGetId(['disk' => 'local', 'path' => $path, 'mime' => 'application/pdf', 'bytes' => strlen($bytes), 'alt' => $alt, 'checksum' => hash('sha256', $bytes), 'status' => 'candidate', 'uploaded_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['file' => 'PDF inválido, cifrado o no compatible. Exporta un PDF estándar sin protección, hasta 8 MB y 80 páginas.']);
    }
}
