<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Cms\Blocks;
use App\Domain\Communication\Posts;
use App\Domain\Site\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.posts', ['posts' => DB::table('posts')->when($request->q, fn ($q) => $q->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_substr($request->q, 0, 100)).'%'))->when(in_array($request->status, ['draft', 'published', 'archived']), fn ($q) => $q->where('status', $request->status))->orderByDesc('id')->paginate(20)->withQueryString(), 'categories' => DB::table('post_categories')->orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:180', 'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:180|unique:posts']);
        $id = DB::table('posts')->insertGetId($data + ['body_json' => json_encode(['schema_version' => 1, 'blocks' => []]), 'status' => 'draft', 'author_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('posts.created', 'posts', $id);

        return redirect('/admin/posts/'.$id.'/edit');
    }

    private function meta(Request $request, int $id): array
    {
        $d = $request->validate(['title' => 'required|string|max:180', 'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:180', Rule::unique('posts')->ignore($id)], 'excerpt' => 'nullable|string|max:1500', 'seo_title' => 'nullable|string|max:180', 'seo_description' => 'nullable|string|max:320', 'featured_media_id' => 'nullable|integer|exists:media,id', 'tags' => 'nullable|string|max:500', 'category_ids' => 'nullable|string|max:500']);
        $ids = array_values(array_unique(array_map('intval', array_filter(explode(',', $d['category_ids'] ?? '')))));
        abort_unless(count($ids) === DB::table('post_categories')->whereIn('id', $ids)->count(), 422, 'Categoría desconocida.');
        $d['category_ids'] = $ids;
        $d['tags_json'] = json_encode(array_values(array_filter(array_map('trim', explode(',', $d['tags'] ?? '')))));
        unset($d['tags']);

        return $d;
    }

    public function edit(int $id)
    {
        $page = DB::table('posts')->find($id);
        abort_unless($page, 404);
        $revision = DB::table('post_revisions')->where('post_id', $id)->find($page->current_revision_id);
        $meta = $revision ? json_decode($revision->meta_json, true) : (array) $page;
        $meta['tags'] = implode(', ', json_decode($meta['tags_json'] ?? '[]', true));
        $meta['category_ids'] = implode(',', $meta['category_ids'] ?? DB::table('post_category_links')->where('post_id', $id)->pluck('post_category_id')->all());

        return view('admin.post-editor', ['page' => $page, 'meta' => $meta, 'document' => $revision ? json_decode($revision->body_json, true) : ['schema_version' => 1, 'blocks' => []], 'media' => DB::table('media')->where('mime', 'like', 'image/%')->get(), 'sections' => DB::table('cms_sections')->orderBy('name')->get(), 'revisions' => DB::table('post_revisions')->where('post_id', $id)->orderByDesc('revision')->get(), 'categories' => DB::table('post_categories')->get()]);
    }

    public function save(Request $request, int $id, Posts $posts)
    {
        $data = $request->validate(['document' => 'required|array', 'base_revision' => 'required|integer|min:0']);
        $revision = $posts->save($id, $this->meta($request, $id), $data['document'], $data['base_revision'], auth()->id());

        return response()->json(['revision_id' => $revision, 'message' => 'Borrador guardado. El público no cambió.']);
    }

    public function publish(Request $request, int $id, Posts $posts)
    {
        $d = $request->validate(['revision_id' => 'required|integer', 'scheduled_for' => 'nullable|date|after:now']);
        if (empty($d['scheduled_for'])) {
            $posts->publish($id, $d['revision_id']);
        } else {
            $r = DB::table('post_revisions')->where('post_id', $id)->find($d['revision_id']);
            abort_unless($r, 404);
            Blocks::validate(json_decode($r->body_json, true), true);
            DB::table('posts')->where('id', $id)->update(['scheduled_revision_id' => $r->id, 'scheduled_for' => $d['scheduled_for'], 'scheduled_by' => auth()->id()]);
            Audit::record('posts.scheduled', 'posts', $id);
        }

        return back()->with('status', 'Novedad publicada o programada según la fecha indicada.');
    }

    public function rollback(Request $request, int $id, Posts $posts)
    {
        $d = $request->validate(['revision_id' => 'required|integer', 'base_revision' => 'required|integer']);
        $r = DB::table('post_revisions')->where('post_id', $id)->find($d['revision_id']);
        abort_unless($r, 404);
        $posts->save($id, json_decode($r->meta_json, true), json_decode($r->body_json, true), $d['base_revision'], auth()->id());

        return back()->with('status', 'Revisión restaurada en borrador.');
    }

    public function archive(int $id)
    {
        abort_unless(DB::table('posts')->where('id', $id)->exists(), 404);
        DB::table('posts')->where('id', $id)->update(['status' => 'archived', 'scheduled_for' => null, 'scheduled_revision_id' => null]);
        Audit::record('posts.archived', 'posts', $id);

        return back()->with('status', 'Novedad retirada.');
    }

    public function preview(Request $request, int $id)
    {
        $page = DB::table('posts')->find($id);
        abort_unless($page, 404);
        if ($request->isMethod('post')) {
            $document = Blocks::validate($request->validate(['document' => 'required|array'])['document']);
            $meta = $this->meta($request, $id);
        } else {
            $r = DB::table('post_revisions')->where('post_id', $id)->find($page->current_revision_id);
            $document = $r ? json_decode($r->body_json, true) : ['blocks' => []];
            $meta = $r ? json_decode($r->meta_json, true) : [];
        }foreach (['title', 'slug', 'excerpt', 'seo_title', 'seo_description', 'featured_media_id'] as $key) {
            if (array_key_exists($key, $meta)) {
                $page->$key = $meta[$key];
            }
        }$page->slug = 'novedades/'.$page->slug;

        return view('site.post', ['page' => $page, 'document' => $document, 'site' => Site::context(true)]);
    }

    public function category(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:120', 'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:120|unique:post_categories']);
        DB::table('post_categories')->insert($d + ['created_at' => now(), 'updated_at' => now()]);
        Audit::record('posts.category_created', 'post_categories');

        return back()->with('status', 'Categoría de novedades creada.');
    }

    public function deleteCategory(int $id)
    {
        abort_if(DB::table('post_category_links')->where('post_category_id', $id)->exists(), 422, 'Categoría utilizada.');
        DB::table('post_categories')->where('id', $id)->delete();
        Audit::record('posts.category_deleted', 'post_categories', $id);

        return back()->with('status', 'Categoría eliminada.');
    }
}
