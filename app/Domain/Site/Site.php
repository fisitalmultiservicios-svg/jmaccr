<?php

namespace App\Domain\Site;

use Illuminate\Support\Facades\DB;

final class Site
{
    public static function setting(string $key, mixed $default = null): mixed
    {
        $value = DB::table('site_settings')->where('key', $key)->value('json_value');

        return $value === null ? $default : json_decode($value, true, 32, JSON_THROW_ON_ERROR);
    }

    public static function defaults(): array
    {
        return ['primary' => '#174B91', 'secondary' => '#1C87BB', 'text' => '#18263D', 'background' => '#FFFFFF', 'muted' => '#526279', 'font' => 'Arial', 'heading_size' => 64, 'body_size' => 16, 'content_width' => 1200, 'radius' => 12, 'logo_media_id' => null, 'footer_text' => 'LIDER EN EL MERCADO INDUSTRIAL', 'footer_credit' => 'Creado por fisitalcr', 'header_sticky' => true];
    }

    public static function context(bool $preview = false): array
    {
        $theme = DB::table('site_themes')->when(! $preview, fn ($q) => $q->where('status', 'published'))->orderByDesc('revision')->first();
        $tokens = array_replace(self::defaults(), $theme ? json_decode($theme->design_tokens, true) : []);
        $menu = DB::table('nav_menus')->where('name', 'principal')->value('id');

        return ['tokens' => $tokens, 'menu' => DB::table('nav_items')->where('menu_id', $menu)->where('visible', true)->orderBy('position')->get(), 'business' => self::setting('business', []), 'preview' => $preview];
    }

    public static function css(array $tokens): string
    {
        $safe = self::defaults();
        foreach (['primary', 'secondary', 'text', 'background', 'muted'] as $field) {
            if (preg_match('/^#[0-9a-f]{6}$/i', (string) ($tokens[$field] ?? ''))) {
                $safe[$field] = $tokens[$field];
            }
        }
        foreach (['heading_size', 'body_size', 'content_width', 'radius'] as $field) {
            $safe[$field] = max(0, min(1600, (int) ($tokens[$field] ?? $safe[$field])));
        }
        if (in_array($tokens['font'] ?? '', ['Arial', 'Verdana', 'Georgia', 'Tahoma', 'Trebuchet MS'])) {
            $safe['font'] = $tokens['font'];
        }

        return ':root{--primary:'.$safe['primary'].';--secondary:'.$safe['secondary'].';--text:'.$safe['text'].';--background:'.$safe['background'].';--muted:'.$safe['muted'].';--font:"'.$safe['font'].'";--heading-size:'.$safe['heading_size'].'px;--body-size:'.$safe['body_size'].'px;--content-width:'.$safe['content_width'].'px;--radius:'.$safe['radius'].'px;}';
    }
}
