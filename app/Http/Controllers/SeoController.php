<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class SeoController extends Controller
{
    public function robots()
    {
        return response(app()->isProduction() ? "User-agent: *\nDisallow: /admin\nDisallow: /api\nDisallow: /storage\nSitemap: ".url('/sitemap.xml')."\n" : "User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap()
    {
        $paths = [];
        if (app()->isProduction()) {
            foreach (DB::table('pages')->where('status', 'published')->whereNotNull('published_revision_id')->get(['slug', 'updated_at']) as $r) {
                $paths[] = ['/'.$r->slug, $r->updated_at];
            }foreach (DB::table('products')->where('status', 'published')->whereNull('deleted_at')->get(['slug', 'updated_at']) as $r) {
                $paths[] = ['/catalogo/'.$r->slug.'/', $r->updated_at];
            }foreach (DB::table('posts')->where('status', 'published')->whereNotNull('published_revision_id')->get(['slug', 'updated_at']) as $r) {
                $paths[] = ['/novedades/'.$r->slug.'/', $r->updated_at];
            }
        }

        return response(view('site.sitemap', ['paths' => $paths]), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
