<?php

namespace App\Domain\Catalog;

final class CatalogFileLimits
{
    public const MAX_BYTES = 128 * 1024 * 1024;

    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1' || $value === '0') {
            return PHP_INT_MAX;
        }
        $number = (float) $value;

        return (int) ($number * match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        });
    }

    public static function uploadBytes(): int
    {
        return max(0, min(self::MAX_BYTES, self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size')) - 1024 * 1024));
    }

    public static function summary(): array
    {
        return ['app_mb' => 128, 'effective_mb' => round(self::uploadBytes() / 1024 / 1024, 1), 'upload_ini' => ini_get('upload_max_filesize'), 'post_ini' => ini_get('post_max_size')];
    }
}
