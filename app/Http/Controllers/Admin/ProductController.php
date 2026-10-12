<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Catalog\Catalog;
use App\Domain\Cms\Blocks;
use App\Domain\Site\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'status' => 'nullable|in:draft,published,hidden,archived,deleted']);
        $items = DB::table('products')->when(($data['status'] ?? '') === 'deleted', fn ($q) => $q->whereNotNull('deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->when($data['q'] ?? null, fn ($q, $v) => $q->where(fn ($sub) => $sub->where('name', 'like', '%'.$v.'%')->orWhere('sku', 'like', '%'.$v.'%')))->when(! empty($data['status']) && $data['status'] !== 'deleted', fn ($q) => $q->where('status', $data['status']))->orderBy('position')->paginate(20)->withQueryString();

        return view('admin.products', ['items' => $items]);
    }

    public function edit(?int $id = null)
    {
        $product = $id ? DB::table('products')->find($id) : null;
        if ($id) {
            abort_unless($product, 404);
        }

        return view('admin.product-editor', ['product' => $product, 'categories' => DB::table('categories')->orderBy('name')->get(), 'brands' => DB::table('brands')->orderBy('name')->get(), 'media' => DB::table('media')->orderBy('id')->get(), 'categoryIds' => $id ? DB::table('category_product')->where('product_id', $id)->pluck('category_id')->all() : [], 'images' => $id ? DB::table('product_media')->where('product_id', $id)->get()->keyBy('media_id') : collect(), 'documents' => $id ? DB::table('product_documents')->where('product_id', $id)->pluck('media_id')->all() : [], 'attributes' => $id ? DB::table('product_attributes')->where('product_id', $id)->orderBy('position')->get() : collect()]);
    }

    public function save(Request $request, ?int $id = null)
    {
        if ($id) {
            abort_unless(DB::table('products')->where('id', $id)->whereNull('deleted_at')->exists(), 404);
        }
        $data = $request->validate(['name' => 'required|string|max:180', 'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:180', Rule::unique('products', 'slug')->ignore($id)], 'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($id)], 'summary' => 'nullable|string|max:2000', 'description' => 'nullable|string|max:30000', 'status' => 'required|in:draft,published,hidden,archived', 'unit' => 'required|string|max:50', 'category_primary_id' => 'nullable|integer|exists:categories,id', 'category_ids' => 'nullable|array|max:20', 'category_ids.*' => 'integer|distinct|exists:categories,id', 'brand_id' => 'nullable|integer|exists:brands,id', 'is_featured' => 'required|boolean', 'position' => 'required|integer|min:0|max:100000', 'private_price_crc' => 'nullable|numeric|min:0|max:999999999999', 'stock' => 'nullable|integer|min:0|max:99999999', 'tags_text' => 'nullable|string|max:1000', 'seo_title' => 'nullable|string|max:180', 'seo_description' => 'nullable|string|max:320', 'image_ids' => 'nullable|array|max:20', 'image_ids.*' => 'integer|distinct|exists:media,id', 'image_positions' => 'nullable|array|max:100', 'image_positions.*' => 'integer|min:0|max:100', 'document_ids' => 'nullable|array|max:10', 'document_ids.*' => 'integer|distinct|exists:media,id', 'attributes' => 'nullable|array|max:50', 'attributes.*.name' => 'required|string|max:100', 'attributes.*.value' => 'required|string|max:1000', 'attributes.*.unit' => 'nullable|string|max:50']);
        if ($data['status'] === 'published') {
            abort_unless($request->user()->hasPermission('products.publish'), 403);
            abort_if(empty($data['sku']), 422, 'El código SKU es obligatorio al publicar.');
            foreach (['category_primary_id' => 'categories', 'brand_id' => 'brands'] as $field => $table) {
                if (! empty($data[$field])) {
                    abort_unless(DB::table($table)->where('id', $data[$field])->where('status', 'published')->exists(), 422, 'Publica la categoría y marca seleccionadas primero.');
                }
            }
        }
        $images = array_map('intval', $data['image_ids'] ?? []);
        $documents = array_map('intval', $data['document_ids'] ?? []);
        foreach ([[$images, 'image/%'], [$documents, 'application/pdf']] as [$ids,$mime]) {
            if ($ids) {
                abort_unless(DB::table('media')->whereIn('id', $ids)->where('mime', 'like', $mime)->when($data['status'] === 'published', fn ($q) => $q->where('status', 'approved'))->count() === count($ids), 422, 'Revisa el tipo y aprobación de los archivos.');
            }
        }
        $fields = array_intersect_key($data, array_flip(['name', 'slug', 'sku', 'summary', 'description', 'status', 'unit', 'category_primary_id', 'brand_id', 'is_featured', 'position', 'private_price_crc', 'stock', 'seo_title', 'seo_description']));
        $fields['description'] = Blocks::richText($fields['description'] ?? '');
        $fields['tags_json'] = json_encode(array_values(array_filter(array_map('trim', explode(',', $data['tags_text'] ?? '')))));
        $fields['search_text'] = Str::ascii(mb_strtolower(implode(' ', [$data['name'], $data['sku'] ?? '', $data['summary'] ?? '', strip_tags($fields['description']), $data['tags_text'] ?? ''])));
        $fields['updated_at'] = now();
        $id = DB::transaction(function () use ($id, $fields, $data, $images, $documents) {
            if ($id) {
                DB::table('products')->where('id', $id)->lockForUpdate()->first();
                DB::table('products')->where('id', $id)->update($fields);
            } else {
                $id = DB::table('products')->insertGetId($fields + ['created_at' => now()]);
            }$cats = array_unique(array_map('intval', $data['category_ids'] ?? []));
            if (! empty($data['category_primary_id'])) {
                $cats[] = intval($data['category_primary_id']);
            }DB::table('category_product')->where('product_id', $id)->delete();
            foreach (array_unique($cats) as $cat) {
                DB::table('category_product')->insert(['product_id' => $id, 'category_id' => $cat]);
            }DB::table('product_media')->where('product_id', $id)->delete();
            usort($images, fn ($a, $b) => ($data['image_positions'][$a] ?? 0) <=> ($data['image_positions'][$b] ?? 0));
            foreach ($images as $position => $image) {
                DB::table('product_media')->insert(['product_id' => $id, 'media_id' => $image, 'position' => $position, 'is_primary' => $position === 0]);
            }DB::table('product_documents')->where('product_id', $id)->delete();
            foreach ($documents as $position => $media) {
                DB::table('product_documents')->insert(['product_id' => $id, 'media_id' => $media, 'position' => $position]);
            }DB::table('product_attributes')->where('product_id', $id)->delete();
            foreach ($data['attributes'] ?? [] as $position => $attribute) {
                DB::table('product_attributes')->insert(['product_id' => $id, 'name' => $attribute['name'], 'value' => $attribute['value'], 'unit' => $attribute['unit'] ?? null, 'position' => $position]);
            }Audit::record('products.saved', 'products', $id, ['status' => $fields['status']]);

            return $id;
        }, 3);

        return redirect('/admin/catalog/products/'.$id.'/edit')->with('status', 'Producto guardado.');
    }

    public function duplicate(int $id)
    {
        $product = DB::table('products')->find($id);
        abort_unless($product, 404);
        $new = DB::transaction(function () use ($product) {
            $fields = (array) $product;
            unset($fields['id']);
            $fields['name'] .= ' (copia)';
            $fields['sku'] = null;
            $fields['slug'] .= '-copia-'.Str::lower(Str::random(5));
            $fields['status'] = 'draft';
            $fields['deleted_at'] = null;
            $fields['created_at'] = $fields['updated_at'] = now();
            $new = DB::table('products')->insertGetId($fields);
            foreach (['category_product', 'product_media', 'product_documents', 'product_attributes'] as $table) {
                foreach (DB::table($table)->where('product_id', $product->id)->get() as $row) {
                    $copy = (array) $row;
                    unset($copy['id']);
                    $copy['product_id'] = $new;
                    DB::table($table)->insert($copy);
                }
            }Audit::record('products.duplicated', 'products', $new, ['status' => 'draft']);

            return $new;
        });

        return redirect('/admin/catalog/products/'.$new.'/edit');
    }

    public function archive(int $id)
    {
        DB::table('products')->where('id', $id)->update(['status' => 'archived', 'updated_at' => now()]);
        Audit::record('products.archived', 'products', $id, ['status' => 'archived']);

        return back()->with('status', 'Producto archivado. El histórico de solicitudes se conserva.');
    }

    public function delete(int $id)
    {
        DB::table('products')->where('id', $id)->update(['status' => 'archived', 'deleted_at' => now(), 'updated_at' => now()]);
        Audit::record('products.deleted', 'products', $id, ['status' => 'archived']);

        return redirect('/admin/catalog/products')->with('status', 'Producto retirado; puede restaurarse.');
    }

    public function restore(int $id)
    {
        DB::table('products')->where('id', $id)->update(['deleted_at' => null, 'status' => 'draft', 'updated_at' => now()]);
        Audit::record('products.restored', 'products', $id, ['status' => 'draft']);

        return back()->with('status', 'Producto restaurado en borrador.');
    }

    public function preview(int $id)
    {
        $product = DB::table('products')->find($id);
        abort_unless($product, 404);
        Catalog::hydrate([$product]);

        return view('site.product', ['product' => $product, 'page' => (object) ['title' => $product->name, 'slug' => 'catalogo/'.$product->slug, 'status' => 'draft', 'seo_title' => $product->seo_title, 'seo_description' => $product->seo_description], 'site' => Site::context(true), 'documents' => DB::table('product_documents')->where('product_id', $id)->get()]);
    }
}
