<?php

namespace App\Domain\Sales;

use App\Domain\Site\Site;
use Illuminate\Support\Facades\Crypt;

final class Privacy
{
    public const ACCEPTANCE_NOT_REQUIRED = 'sin_aceptacion_requerida';

    public static function encrypt(?string $value): ?string
    {
        return $value === null || $value === '' ? null : Crypt::encryptString($value);
    }

    public static function decrypt(?string $value): string
    {
        return $value ? Crypt::decryptString($value) : '';
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }

    public static function phone(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    public static function legal(): array
    {
        return Site::setting('privacy.document', ['approved' => false, 'version' => '', 'body' => '']);
    }

    public static function person(object $row, array $fields): object
    {
        foreach ($fields as $field) {
            $row->$field = self::decrypt($row->$field ?? null);
        }

        return $row;
    }
}
