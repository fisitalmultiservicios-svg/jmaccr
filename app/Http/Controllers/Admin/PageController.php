<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Cms\Blocks;
use App\Domain\Cms\Pages;
use App\Domain\Site\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.pages', ['pages' => DB::table('pages')->when($request->q, fn ($q) => $q->where('title', 'like', '%'.mb_substr($request->q, 0, 120).'%'))->orderBy('id')->paginate(20)]);
    }

    public function create(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:180', 'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:180|unique:pages,slug']);
        $id = DB::transaction(function () use ($data, $request) {
            $id = DB::table('pages')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
            app(Pages::class)->save($id, $data, ['schema_version' => 1, 'blocks' => []], 0, $request->user()->id);

            return $id;
        });

        return redirect('/admin/pages/'.$id.'/edit');
    }

    public function edit(int $id)
    {
        $page = DB::table('pages')->find($id);
        abort_unless($page, 404);
        $revision = DB::table('page_revisions')->where('page_id', $id)->find($page->current_revision_id);
        $meta = $revision ? json_decode($revision->meta_json ?? '{}', true) : [];

        return view('admin.page-editor', ['page' => $page, 'meta' => $meta, 'document' => Blocks::normalize($revision ? json_decode($revision->blocks_json, true) : ['blocks' => []]), 'media' => DB::table('media')->where('mime', 'like', 'image/%')->orderByDesc('id')->get(), 'revisions' => DB::table('page_revisions')->where('page_id', $id)->orderByDesc('revision')->get(), 'sections' => DB::table('cms_sections')->orderBy('name')->get()]);
    }

    private function meta(Request $request, int $id): array
    {
        $page = DB::table('pages')->find($id);
        abort_unless($page, 404);
        $data = $request->validate(['title' => 'required|string|max:180', 'slug' => ['present', 'nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:180', Rule::unique('pages', 'slug')->ignore($id)], 'seo_title' => 'nullable|string|max:180', 'seo_description' => 'nullable|string|max:320']);
        $data['slug'] ??= '';
        if (in_array($page->slug, ['', 'nosotros', 'contacto', 'novedades', 'catalogo']) && $data['slug'] !== $page->slug) {
            abort(422, 'Las cinco rutas corporativas deben conservarse.');
        }

        return $data;
    }

    public function save(Request $request, int $id, Pages $pages)
    {
        $data = $request->validate(['document' => 'required|array', 'base_revision' => 'required|integer|min:0']);
        $revision = $pages->save($id, $this->meta($request, $id), $data['document'], $data['base_revision'], $request->user()->id);

        return response()->json(['revision_id' => $revision, 'message' => 'Borrador guardado. El público no cambió.']);
    }

    public function publish(Request $request, int $id, Pages $pages)
    {
        $data = $request->validate(['revision_id' => 'required|integer', 'scheduled_for' => 'nullable|date|after:now']);
        if (! empty($data['scheduled_for'])) {
            $revision = DB::table('page_revisions')->where('page_id', $id)->find($data['revision_id']);
            abort_unless($revision, 404);
            Blocks::validate(Blocks::normalize(json_decode($revision->blocks_json, true)), true);
            DB::table('pages')->where('id', $id)->update(['scheduled_revision_id' => $revision->id, 'scheduled_for' => $data['scheduled_for'], 'scheduled_by' => auth()->id()]);
            Audit::record('pages.scheduled', 'pages', $id, ['revision' => $revision->revision]);
        } else {
            $pages->publish($id, $data['revision_id'], $request->user()->id);
        }

        return redirect('/admin/pages/'.$id.'/edit')->with('status', empty($data['scheduled_for']) ? 'Página publicada.' : 'Publicación programada; requiere Cron de operaciones.');
    }

    public function rollback(Request $request, int $id, Pages $pages)
    {
        $data = $request->validate(['revision_id' => 'required|integer', 'base_revision' => 'required|integer']);
        $revision = DB::table('page_revisions')->where('page_id', $id)->find($data['revision_id']);
        abort_unless($revision, 404);
        $page = DB::table('pages')->find($id);
        $meta = $revision->meta_json ? json_decode($revision->meta_json, true) : array_intersect_key((array) $page, array_flip(['title', 'slug', 'seo_title', 'seo_description']));
        $pages->save($id, $meta, Blocks::normalize(json_decode($revision->blocks_json, true)), $data['base_revision'], $request->user()->id);

        return back()->with('status', 'Revisión restaurada como nuevo borrador. Publica para aplicarla.');
    }

    public function archive(int $id)
    {
        abort_unless(DB::table('pages')->where('id', $id)->exists(), 404);
        DB::table('pages')->where('id', $id)->update(['status' => 'archived', 'scheduled_revision_id' => null, 'scheduled_for' => null]);
        Audit::record('pages.archived', 'pages', $id, ['status' => 'archived']);

        return back()->with('status', 'Página retirada de publicación.');
    }

    public function preview(Request $request, int $id)
    {
        $page = DB::table('pages')->find($id);
        abort_unless($page, 404);
        if ($request->isMethod('post')) {
            $document = Blocks::validate($request->validate(['document' => 'required|array'])['document']);
            $meta = $this->meta($request, $id);
        } else {
            $revision = DB::table('page_revisions')->where('page_id', $id)->find($page->current_revision_id);
            $document = Blocks::normalize($revision ? json_decode($revision->blocks_json, true) : ['blocks' => []]);
            $meta = $revision ? json_decode($revision->meta_json ?? '{}', true) : [];
        }foreach ($meta as $key => $value) {
            $page->$key = $value;
        }

        return view('site.page', ['page' => $page, 'document' => $document, 'site' => Site::context(true)]);
    }

    public function section(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'document' => 'required|array']);
        $document = Blocks::validate($data['document']);
        $id = DB::table('cms_sections')->insertGetId(['name' => $data['name'], 'blocks_json' => json_encode($document), 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('sections.created', 'cms_sections', $id);

        return response()->json(['id' => $id]);
    }
}
