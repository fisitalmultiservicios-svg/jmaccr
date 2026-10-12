<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Site\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThemeController extends Controller
{
    public function index()
    {
        return view('admin.theme', ['themes' => DB::table('site_themes')->orderByDesc('revision')->get(), 'media' => DB::table('media')->where('mime', 'like', 'image/%')->get()]);
    }

    public function save(Request $request)
    {
        $data = $request->validate(['base_revision' => 'required|integer', 'name' => 'required|string|max:120', 'primary' => 'required|regex:/^#[a-fA-F0-9]{6}$/', 'secondary' => 'required|regex:/^#[a-fA-F0-9]{6}$/', 'text' => 'required|regex:/^#[a-fA-F0-9]{6}$/', 'background' => 'required|regex:/^#[a-fA-F0-9]{6}$/', 'muted' => 'required|regex:/^#[a-fA-F0-9]{6}$/', 'font' => 'required|in:Arial,Verdana,Georgia,Tahoma,Trebuchet MS', 'heading_size' => 'required|integer|min:28|max:96', 'body_size' => 'required|integer|min:14|max:22', 'content_width' => 'required|integer|min:800|max:1440', 'radius' => 'required|integer|min:0|max:32', 'logo_media_id' => 'nullable|integer|exists:media,id', 'footer_text' => 'nullable|string|max:1000', 'footer_credit' => 'nullable|string|max:180', 'header_sticky' => 'required|boolean']);
        $tokens = array_intersect_key($data, Site::defaults());
        foreach (['heading_size', 'body_size', 'content_width', 'radius', 'logo_media_id'] as $field) {
            if (isset($tokens[$field])) {
                $tokens[$field] = (int) $tokens[$field];
            }
        }$tokens['header_sticky'] = (bool) $tokens['header_sticky'];
        DB::transaction(function () use ($tokens, $data, $request) {
            DB::table('site_settings')->where('key', 'brand.draft')->lockForUpdate()->first();
            $revision = (int) DB::table('site_themes')->max('revision');
            abort_unless($revision === (int) $data['base_revision'], 409, 'El tema cambió; recarga antes de guardar.');
            DB::table('site_themes')->insert(['name' => $data['name'], 'design_tokens' => json_encode($tokens), 'status' => 'draft', 'revision' => $revision + 1, 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record('theme.saved', 'site_themes', null, ['revision' => $revision + 1]);
        }, 3);

        return back()->with('status', 'Nuevo borrador visual guardado. El público no cambió.');
    }

    public function publish(int $id)
    {
        DB::transaction(function () use ($id) {
            DB::table('site_settings')->where('key', 'brand.draft')->lockForUpdate()->first();
            $theme = DB::table('site_themes')->find($id);
            abort_unless($theme, 404);
            $tokens = json_decode($theme->design_tokens, true);
            if ($tokens['logo_media_id'] ?? null) {
                abort_unless(DB::table('media')->where('id', $tokens['logo_media_id'])->where('status', 'approved')->exists(), 422, 'Aprueba el logo antes de publicar.');
            }DB::table('site_themes')->where('status', 'published')->update(['status' => 'archived']);
            DB::table('site_themes')->where('id', $id)->update(['status' => 'published']);
            Audit::record('theme.published', 'site_themes', $id, ['revision' => $theme->revision]);
        }, 3);

        return back()->with('status', 'Diseño publicado.');
    }

    public function rollback(Request $request, int $id)
    {
        $theme = DB::table('site_themes')->find($id);
        abort_unless($theme, 404);
        DB::transaction(function () use ($theme, $request) {
            DB::table('site_settings')->where('key', 'brand.draft')->lockForUpdate()->first();
            $version = (int) DB::table('site_themes')->max('revision') + 1;
            DB::table('site_themes')->insert(['name' => 'Restauración de '.$theme->name, 'design_tokens' => $theme->design_tokens, 'status' => 'draft', 'revision' => $version, 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record('theme.restored', 'site_themes', null, ['revision' => $version]);
        }, 3);

        return back()->with('status', 'Diseño restaurado en nuevo borrador.');
    }

    public function reset(Request $request)
    {
        $theme = DB::table('site_themes')->orderByDesc('revision')->first();
        $request->merge(Site::defaults() + ['name' => 'Restablecer propuesta visual', 'base_revision' => (int) $theme->revision]);

        return $this->save($request);
    }
}
