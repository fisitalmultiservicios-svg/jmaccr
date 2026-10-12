<?php

namespace App\Http\Controllers;

use App\Domain\Cms\Blocks;
use App\Domain\Site\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SiteController extends Controller
{
    public function page(string $slug = '')
    {
        $page = DB::table('pages')->where('slug', $slug)->first();
        abort_unless($page && (in_array($slug, ['', 'nosotros', 'contacto', 'novedades', 'catalogo']) || $page->status === 'published'), 404);
        $revision = $page->status === 'published' && $page->published_revision_id ? DB::table('page_revisions')->where('page_id', $page->id)->find($page->published_revision_id) : null;

        return view('site.page', ['page' => $page, 'document' => $revision ? Blocks::normalize(json_decode($revision->blocks_json, true)) : ['blocks' => []], 'site' => Site::context()]);
    }

    public function media(int $id)
    {
        $media = DB::table('media')->where('status', 'approved')->find($id);
        abort_unless($media, 404);

        if ($media->mime === 'application/pdf') {
            return Storage::disk($media->disk)->download($media->path, 'ficha-jmac.pdf', ['Content-Type' => 'application/pdf']);
        }

        return Storage::disk($media->disk)->response($media->path, null, ['Content-Type' => $media->mime, 'Cache-Control' => 'public, max-age=86400']);
    }
}
