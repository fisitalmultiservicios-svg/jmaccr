<?php

namespace App\Http\Controllers;

use App\Domain\Site\Site;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function show(string $slug)
    {
        $page = DB::table('posts')->where('slug', $slug)->where('status', 'published')->whereNotNull('published_revision_id')->first();
        abort_unless($page, 404);
        $page->slug = 'novedades/'.$page->slug;

        return view('site.post', ['page' => $page, 'document' => json_decode($page->body_json, true), 'site' => Site::context()]);
    }
}
