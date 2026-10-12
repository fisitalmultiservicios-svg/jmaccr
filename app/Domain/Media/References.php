<?php

namespace App\Domain\Media;

use Illuminate\Support\Facades\DB;

final class References
{
    public static function used(int $id): bool
    {
        foreach (['product_media' => 'media_id', 'product_documents' => 'media_id', 'brands' => 'logo_media_id', 'categories' => 'cover_media_id', 'posts' => 'featured_media_id'] as $table => $column) {
            if (DB::table($table)->where($column, $id)->exists()) {
                return true;
            }
        }
        $walk = function ($data) use (&$walk, $id): bool {
            if (! is_array($data)) {
                return false;
            }foreach ($data as $key => $value) {
                if (in_array($key, ['media_id', 'image_media_id', 'logo_media_id']) && (int) $value === $id) {
                    return true;
                }if (is_array($value) && $walk($value)) {
                    return true;
                }
            }

            return false;
        };
        foreach (['page_revisions' => 'blocks_json', 'post_revisions' => 'body_json', 'site_themes' => 'design_tokens', 'cms_sections' => 'blocks_json'] as $table => $column) {
            foreach (DB::table($table)->select($column)->cursor() as $row) {
                if ($walk(json_decode($row->$column, true))) {
                    return true;
                }
            }
        }

        return false;
    }
}
