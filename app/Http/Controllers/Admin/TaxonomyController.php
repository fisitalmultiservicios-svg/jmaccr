<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaxonomyController extends Controller
{
    private function table(): string
    {
        $table = request()->segment(3);
        abort_unless(in_array($table, ['categories', 'brands']), 404);

        return $table;
    }

    public function index(Request $request)
    {
        $table = $this->table();

        return view('admin.taxonomies', ['table' => $table, 'items' => DB::table($table)->when($request->q, fn ($q) => $q->where('name', 'like', '%'.mb_substr($request->q, 0, 120).'%'))->orderBy('position')->paginate(20), 'parents' => DB::table('categories')->get(), 'media' => DB::table('media')->where('mime', 'like', 'image/%')->get()]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $table = $this->table();
        if ($id) {
            abort_unless(DB::table($table)->where('id', $id)->exists(), 404);
        }$image = $table === 'categories' ? 'cover_media_id' : 'logo_media_id';
        $text = $table === 'categories' ? 'summary' : 'description';
        $data = $request->validate(['name' => 'required|string|max:180', 'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:180', Rule::unique($table, 'slug')->ignore($id)], $text => 'nullable|string|max:5000', 'status' => 'required|in:hidden,published', 'position' => 'required|integer|min:0|max:100000', $image => 'nullable|integer|exists:media,id', 'parent_id' => $table === 'categories' ? 'nullable|integer|exists:categories,id' : 'prohibited']);
        if ($table === 'brands') {
            unset($data['parent_id']);
        }
        if (! empty($data[$image])) {
            abort_unless(DB::table('media')->where('id', $data[$image])->where('mime', 'like', 'image/%')->when($data['status'] === 'published', fn ($q) => $q->where('status', 'approved'))->exists(), 422, 'Selecciona una imagen aprobada para publicar.');
        }
        DB::transaction(function () use ($table, $data, $id) {
            if ($table === 'categories') {
                DB::table('site_settings')->where('key', 'brand.draft')->lockForUpdate()->first();
                $parent = $data['parent_id'] ?? null;
                $seen = [];
                while ($parent) {
                    if (($id !== null && (int) $parent === (int) $id) || in_array($parent, $seen)) {
                        throw ValidationException::withMessages(['parent_id' => 'La jerarquía no puede tener ciclos.']);
                    }$seen[] = $parent;
                    $parent = DB::table('categories')->where('id', $parent)->value('parent_id');
                }
            }
            if ($id) {
                DB::table($table)->where('id', $id)->update($data + ['updated_at' => now()]);
            } else {
                $id = DB::table($table)->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
            }Audit::record($table.'.saved', $table, $id, ['status' => $data['status']]);
        }, 3);

        return back()->with('status', 'Registro guardado.');
    }

    public function delete(int $id)
    {
        $table = $this->table();
        if ($table === 'categories') {
            abort_if(DB::table('categories')->where('parent_id', $id)->exists() || DB::table('category_product')->where('category_id', $id)->exists() || DB::table('products')->where('category_primary_id', $id)->exists(), 422, 'Primero cambia las relaciones de esta categoría.');
        } else {
            abort_if(DB::table('products')->where('brand_id', $id)->exists(), 422, 'Primero cambia la marca de los productos vinculados.');
        }DB::table($table)->where('id', $id)->delete();
        Audit::record($table.'.deleted', $table, $id);

        return back()->with('status', 'Registro eliminado.');
    }

    public function sort(Request $request)
    {
        $table = $this->table();
        $data = $request->validate(['offset' => 'required|integer|min:0|max:100000', 'ids' => 'required|array|max:300', 'ids.*' => 'required|integer|distinct|exists:'.$table.',id']);
        DB::transaction(function () use ($data, $table) {
            foreach ($data['ids'] as $position => $id) {
                DB::table($table)->where('id', $id)->update(['position' => $data['offset'] + $position, 'updated_at' => now()]);
            }Audit::record($table.'.sorted', $table, null, ['count' => count($data['ids'])]);
        });

        return response()->json(['message' => 'Orden guardado.']);
    }
}
