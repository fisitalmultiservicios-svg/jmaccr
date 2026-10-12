<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Catalog;
use App\Domain\Site\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function search(Request $request)
    {
        $products = Catalog::search($request);

        return response()->json(['data' => array_map(Catalog::publicData(...), $products->items()), 'total' => $products->total(), 'page' => $products->currentPage(), 'last_page' => $products->lastPage()]);
    }

    public function show(string $slug)
    {
        $product = DB::table('products')->where('slug', $slug)->where('status', 'published')->whereNull('deleted_at')->select('id', 'sku', 'name', 'slug', 'summary', 'description', 'unit', 'seo_title', 'seo_description')->first();
        abort_unless($product, 404);
        Catalog::hydrate([$product]);

        return view('site.product', ['product' => $product, 'page' => (object) ['title' => $product->name, 'slug' => 'catalogo/'.$product->slug, 'status' => 'published', 'seo_title' => $product->seo_title, 'seo_description' => $product->seo_description], 'site' => Site::context(), 'documents' => DB::table('product_documents')->join('media', 'media.id', '=', 'product_documents.media_id')->where('product_id', $product->id)->where('media.status', 'approved')->select('media_id', 'alt')->get()]);
    }
}
