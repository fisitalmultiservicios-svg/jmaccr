<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Cms\Blocks;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function index()
    {
        return view('admin.menus', ['menus' => DB::table('nav_menus')->get()]);
    }

    public function edit(int $id)
    {
        $menu = DB::table('nav_menus')->find($id);
        abort_unless($menu, 404);
        $revision = $menu->current_revision_id ? DB::table('menu_revisions')->find($menu->current_revision_id) : null;
        $items = $revision ? json_decode($revision->items_json, true) : DB::table('nav_items')->where('menu_id', $id)->orderBy('position')->get()->map(fn ($i) => ['label' => $i->label, 'url' => $i->url, 'visible' => (bool) $i->visible])->toArray();

        return view('admin.menu-editor', ['menu' => $menu, 'items' => $items, 'revisions' => DB::table('menu_revisions')->where('menu_id', $id)->orderByDesc('revision')->get()]);
    }

    public function save(Request $request, int $id)
    {
        $data = $request->validate(['base_revision' => 'required|integer', 'items' => 'present|array|max:30', 'items.*.label' => 'required|string|max:80', 'items.*.url' => 'required|string|max:1000', 'items.*.visible' => 'required|boolean']);
        foreach ($data['items'] as $item) {
            abort_unless(Blocks::safeUrl($item['url']), 422, 'Enlace inseguro.');
        }DB::transaction(function () use ($id, $data, $request) {
            $menu = DB::table('nav_menus')->lockForUpdate()->find($id);
            abort_unless($menu, 404);
            abort_unless((int) $menu->current_revision_id === (int) $data['base_revision'], 409, 'El menú cambió.');
            $version = (int) DB::table('menu_revisions')->where('menu_id', $id)->max('revision') + 1;
            $revision = DB::table('menu_revisions')->insertGetId(['menu_id' => $id, 'items_json' => json_encode($data['items']), 'revision' => $version, 'author_id' => $request->user()->id, 'created_at' => now()]);
            DB::table('nav_menus')->where('id', $id)->update(['current_revision_id' => $revision]);
            Audit::record('menus.saved', 'nav_menus', $id, ['revision' => $version]);
        }, 3);

        return response()->json(['message' => 'Menú guardado en borrador.']);
    }

    public function publish(Request $request, int $id)
    {
        $data = $request->validate(['revision_id' => 'required|integer']);
        DB::transaction(function () use ($id, $data) {
            DB::table('nav_menus')->where('id', $id)->lockForUpdate()->first();
            $revision = DB::table('menu_revisions')->where('menu_id', $id)->find($data['revision_id']);
            abort_unless($revision, 404);
            DB::table('nav_items')->where('menu_id', $id)->delete();
            foreach (json_decode($revision->items_json, true) as $position => $item) {
                abort_unless(Blocks::safeUrl($item['url']), 422);
                DB::table('nav_items')->insert(['menu_id' => $id, 'label' => $item['label'], 'url' => $item['url'], 'visible' => $item['visible'], 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);
            }DB::table('nav_menus')->where('id', $id)->update(['published_revision_id' => $revision->id]);
            Audit::record('menus.published', 'nav_menus', $id, ['revision' => $revision->revision]);
        }, 3);

        return back()->with('status', 'Menú publicado.');
    }

    public function rollback(Request $request, int $id)
    {
        $data = $request->validate(['revision_id' => 'required|integer']);
        $revision = DB::table('menu_revisions')->where('menu_id', $id)->find($data['revision_id']);
        abort_unless($revision, 404);
        $menu = DB::table('nav_menus')->find($id);
        $request->merge(['base_revision' => (int) $menu->current_revision_id, 'items' => json_decode($revision->items_json, true)]);
        $this->save($request, $id);

        return back()->with('status', 'Menú restaurado como nuevo borrador.');
    }
}
