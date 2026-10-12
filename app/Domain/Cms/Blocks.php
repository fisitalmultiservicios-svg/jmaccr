<?php

namespace App\Domain\Cms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class Blocks
{
    public const TYPES = ['hero', 'slider', 'heading', 'rich_text', 'text_image', 'image', 'gallery', 'columns', 'icon_features', 'cta', 'buttons', 'testimonials', 'contact_form', 'whatsapp', 'news_feed', 'catalog_categories', 'featured_products', 'spacer', 'divider', 'faq', 'map'];

    public static function safeUrl(string $url): bool
    {
        return ($url !== '' && preg_match('/^\/(?!\/)[^\s\\\\]*$/u', $url)) || (filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' && ! parse_url($url, PHP_URL_USER));
    }

    public static function normalize(array $document): array
    {
        $aliases = ['section' => 'heading', 'whatsapp_link' => 'whatsapp', 'news_index' => 'news_feed', 'catalog_category_index' => 'catalog_categories', 'preorder_cart' => 'cta'];
        $blocks = [];
        foreach ($document['blocks'] ?? [] as $block) {
            $block['type'] = $aliases[$block['type']] ?? $block['type'];
            $block['data'] ??= [];
            $block['style'] ??= [];
            $block['visibility'] ??= ['desktop' => true, 'tablet' => true, 'mobile' => true];
            $blocks[] = $block;
        }

        return ['schema_version' => 1, 'blocks' => $blocks];
    }

    public static function validate(array $document, bool $publish = false, int $depth = 0): array
    {
        if ($depth > 3 || strlen(json_encode($document)) > 150000) {
            self::reject('El documento excede los límites de tamaño o profundidad.');
        }
        Validator::make($document, ['schema_version' => 'required|integer|in:1', 'blocks' => 'present|array|max:80', 'blocks.*.id' => 'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/', 'blocks.*.type' => 'required|in:'.implode(',', self::TYPES), 'blocks.*.data' => 'present|array', 'blocks.*.style' => 'present|array', 'blocks.*.visibility' => 'required|array', 'blocks.*.visibility.desktop' => 'required|boolean', 'blocks.*.visibility.tablet' => 'required|boolean', 'blocks.*.visibility.mobile' => 'required|boolean'])->validate();
        $ids = [];
        foreach ($document['blocks'] as &$block) {
            if (in_array($block['id'], $ids, true)) {
                self::reject('Cada bloque necesita un identificador único.');
            }$ids[] = $block['id'];
            $data = $block['data'];
            $data = array_intersect_key($data, array_flip(['heading', 'subheading', 'body', 'media_id', 'alt', 'button_label', 'button_url', 'secondary_label', 'secondary_url', 'items', 'columns', 'approved', 'map_url']));
            foreach (['heading' => 300, 'subheading' => 1000, 'body' => 15000, 'alt' => 255, 'button_label' => 80, 'secondary_label' => 80] as $field => $max) {
                if (isset($data[$field]) && (! is_string($data[$field]) || mb_strlen($data[$field]) > $max)) {
                    self::reject('Texto demasiado largo o inválido: '.$field);
                }
            }
            foreach (['button_url', 'secondary_url', 'map_url'] as $field) {
                if (! empty($data[$field]) && (! is_string($data[$field]) || ! self::safeUrl($data[$field]))) {
                    self::reject('El enlace debe ser interno o HTTPS seguro.');
                }
            }
            if (isset($data['media_id']) && $data['media_id'] !== null) {
                self::media($data['media_id'], $publish);
            }
            if ($block['type'] === 'testimonials' && $publish && ($data['approved'] ?? false) !== true) {
                self::reject('Los testimonios requieren aprobación explícita.');
            }
            if (isset($data['items'])) {
                if (! is_array($data['items']) || count($data['items']) > 30) {
                    self::reject('Máximo 30 elementos por bloque.');
                }
                foreach ($data['items'] as &$item) {
                    if (! is_array($item)) {
                        self::reject('Elemento inválido.');
                    }
                    $item = array_intersect_key($item, array_flip(['heading', 'body', 'media_id', 'alt', 'url', 'label', 'icon']));
                    foreach (['heading' => 300, 'body' => 3000, 'alt' => 255, 'label' => 100, 'icon' => 30] as $field => $max) {
                        if (isset($item[$field]) && (! is_string($item[$field]) || mb_strlen($item[$field]) > $max)) {
                            self::reject('Elemento inválido.');
                        }
                    }
                    if (! empty($item['url']) && ! self::safeUrl($item['url'])) {
                        self::reject('Enlace inseguro.');
                    }
                    if (isset($item['media_id']) && $item['media_id'] !== null) {
                        self::media($item['media_id'], $publish);
                    }
                }unset($item);
            }
            if (isset($data['columns'])) {
                if (! is_array($data['columns']) || count($data['columns']) > 4) {
                    self::reject('Máximo cuatro columnas.');
                }
                foreach ($data['columns'] as &$column) {
                    $column = self::validate($column, $publish, $depth + 1);
                }unset($column);
            }
            if ($block['type'] === 'rich_text' && isset($data['body'])) {
                $data['body'] = self::richText($data['body']);
            }
            $style = array_intersect_key($block['style'], array_flip(['background', 'color', 'padding', 'align', 'variant', 'width', 'height']));
            foreach (['background', 'color'] as $field) {
                if (isset($style[$field]) && ! preg_match('/^#[0-9a-f]{6}$/i', (string) $style[$field])) {
                    self::reject('Color inválido.');
                }
            }
            foreach (['padding', 'height'] as $field) {
                if (isset($style[$field]) && (! is_int($style[$field]) || $style[$field] < 0 || $style[$field] > 240)) {
                    self::reject('Espaciado inválido.');
                }
            }
            if (isset($style['align']) && ! in_array($style['align'], ['left', 'center', 'right'])) {
                self::reject('Alineación inválida.');
            }
            if (isset($style['variant']) && ! in_array($style['variant'], ['split', 'full', 'soft', 'dark'])) {
                self::reject('Variante inválida.');
            }
            if (isset($style['width']) && ! in_array($style['width'], ['contained', 'wide'])) {
                self::reject('Ancho inválido.');
            }
            $block = ['id' => $block['id'], 'type' => $block['type'], 'data' => $data, 'style' => $style, 'visibility' => array_intersect_key($block['visibility'], array_flip(['desktop', 'tablet', 'mobile']))];
        }unset($block);

        return ['schema_version' => 1, 'blocks' => $document['blocks']];
    }

    private static function media(mixed $id, bool $publish): void
    {
        if (! is_int($id) || ! DB::table('media')->where('id', $id)->when($publish, fn ($q) => $q->where('status', 'approved'))->where('mime', 'like', 'image/%')->exists()) {
            self::reject('Selecciona una imagen válida y aprobada antes de publicar.');
        }
    }

    public static function richText(string $value): string
    {
        $dom = new \DOMDocument;
        $prior = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$value.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prior);
        $render = function ($node) use (&$render): string {
            if ($node instanceof \DOMText) {
                return htmlspecialchars($node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            if (! $node instanceof \DOMElement) {
                return '';
            }
            if (in_array(strtolower($node->tagName), ['script', 'style', 'iframe', 'object', 'svg', 'math'])) {
                return '';
            }
            $content = '';
            foreach ($node->childNodes as $child) {
                $content .= $render($child);
            }
            $tag = strtolower($node->tagName);
            if (! in_array($tag, ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'blockquote'])) {
                return $content;
            }
            $href = $node->getAttribute('href');
            $attribute = $tag === 'a' && self::safeUrl($href) ? ' href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'" rel="noopener"' : '';

            return '<'.$tag.$attribute.'>'.$content.($tag === 'br' ? '' : '</'.$tag.'>');
        };

        return $render($dom->getElementsByTagName('div')->item(0));
    }

    private static function reject(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
