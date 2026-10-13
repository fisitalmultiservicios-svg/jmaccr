<?php

namespace App\Domain\Catalog;

use App\Domain\Site\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Catalog
{
    public static function quantityLimit(?array $settings = null): int
    {
        return max(1, min(999, (int) (($settings ?? Site::setting('catalog.ui', []))['max_quantity'] ?? 999)));
    }

    public static function search(Request $request, bool $featured = false)
    {
        $input = $request->validate(['q' => 'nullable|string|max:120', 'jmac_cat' => 'nullable|string|max:180', 'category' => 'nullable|string|max:180', 'brand' => 'nullable|string|max:180', 'attributes' => 'nullable|array|max:8', 'attributes.*' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1|max:100000']);
        $query = DB::table('products')->where('status', 'published')->whereNull('deleted_at')->select(['products.id', 'sku', 'name', 'slug', 'summary', 'description', 'unit', 'brand_id', 'category_primary_id', 'is_featured', 'seo_title', 'seo_description']);
        if ($featured) {
            $query->where('is_featured', true);
        }
        if (! empty($input['q'])) {
            $words = preg_split('/\s+/', Str::ascii(mb_strtolower($input['q'])));
            foreach (array_slice($words, 0, 8) as $word) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
                $query->whereRaw("search_text LIKE ? ESCAPE '!'", [$pattern]);
            }
        }
        $slug = $input['jmac_cat'] ?? $input['category'] ?? null;
        if ($slug) {
            $category = DB::table('categories')->where('slug', $slug)->where('status', 'published')->first();
            if (! $category) {
                $query->whereRaw('1=0');
            } else {
                $ids = [$category->id];
                for ($i = 0; $i < 10; $i++) {
                    $more = DB::table('categories')->whereIn('parent_id', $ids)->where('status', 'published')->pluck('id')->all();
                    $next = array_values(array_unique([...$ids, ...$more]));
                    if (count($next) === count($ids)) {
                        break;
                    }$ids = $next;
                }$query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('category_product')->whereColumn('product_id', 'products.id')->whereIn('category_id', $ids));
            }
        }
        if (! empty($input['brand'])) {
            $brand = DB::table('brands')->where('slug', $input['brand'])->where('status', 'published')->value('id');
            $query->where('brand_id', $brand ?? 0);
        }
        foreach ($input['attributes'] ?? [] as $name => $value) {
            if (mb_strlen((string) $name) > 100) {
                abort(422);
            }$query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('product_attributes')->whereColumn('product_id', 'products.id')->where('product_attributes.name', $name)->where('value', $value));
        }
        $paginator = $query->orderBy('position')->orderBy('products.id')->paginate($featured ? 3 : 12)->withQueryString();
        self::hydrate($paginator->items());

        return $paginator;
    }

    public static function hydrate(array $products): void
    {
        $ids = array_map(fn ($p) => $p->id, $products);
        $images = DB::table('product_media')->join('media', 'media.id', '=', 'product_media.media_id')->whereIn('product_id', $ids)->where('media.status', 'approved')->orderBy('position')->select('product_id', 'media.id', 'media.alt', 'media.width', 'media.height')->get()->groupBy('product_id');
        $attributes = DB::table('product_attributes')->whereIn('product_id', $ids)->orderBy('position')->get()->groupBy('product_id');
        foreach ($products as $product) {
            $product->images = $images->get($product->id, collect())->values()->all();
            $product->attributes = $attributes->get($product->id, collect())->values()->all();
        }
    }

    public static function categories()
    {
        return DB::table('categories')->where('status', 'published')->select('categories.*')->selectSub(DB::table('category_product')->join('products', 'products.id', '=', 'category_product.product_id')->selectRaw('COUNT(*)')->whereColumn('category_product.category_id', 'categories.id')->where('products.status', 'published')->whereNull('products.deleted_at'), 'product_count')->orderBy('position')->orderBy('name')->get();
    }

    public static function brands()
    {
        return DB::table('brands')->where('status', 'published')->orderBy('position')->get();
    }

    public static function publicData(object $p): array
    {
        return ['id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'slug' => $p->slug, 'summary' => $p->summary, 'unit' => $p->unit, 'images' => array_map(fn ($image) => ['url' => '/media/'.$image->id, 'alt' => $image->alt], $p->images), 'attributes' => array_map(fn ($a) => ['name' => $a->name, 'value' => $a->value, 'unit' => $a->unit], $p->attributes)];
    }
}
