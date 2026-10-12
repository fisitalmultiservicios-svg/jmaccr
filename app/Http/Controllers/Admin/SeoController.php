<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Site\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeoController extends Controller
{
    public function index()
    {
        return view('admin.seo', ['seo' => Site::setting('seo.global', []), 'redirects' => DB::table('redirects')->orderByDesc('id')->paginate(30)]);
    }

    public function save(Request $request)
    {
        $d = $request->validate(['title' => 'required|string|max:180', 'description' => 'nullable|string|max:320', 'og_media_id' => 'nullable|integer|exists:media,id']);
        if (! empty($d['og_media_id'])) {
            abort_unless(DB::table('media')->where('id', $d['og_media_id'])->where('status', 'approved')->where('mime', 'like', 'image/%')->exists(), 422, 'Imagen pendiente de aprobación.');
        }SettingsController::put('seo.global', $d);
        Audit::record('seo.updated', 'site_settings');

        return back()->with('status', 'SEO global guardado.');
    }

    public function redirect(Request $request)
    {
        $d = $request->validate(['old_path' => 'required|regex:~^/[a-z0-9/_-]*$~|max:180', 'target_path' => 'required|regex:~^/[a-z0-9/_-]*$~|max:180', 'status_code' => 'required|in:301,302']);
        $d['old_path'] = '/'.trim($d['old_path'], '/');
        $d['target_path'] = '/'.trim($d['target_path'], '/');
        abort_if(in_array(explode('/', $d['old_path'])[1] ?? '', ['admin', 'api', 'media', 'storage']) || in_array(explode('/', $d['target_path'])[1] ?? '', ['admin', 'api', 'media', 'storage']), 422, 'Ruta reservada.');
        $target = $d['target_path'];
        $seen = [$d['old_path']];
        for ($i = 0; $i < 20; $i++) {
            abort_if(in_array($target, $seen), 422, 'La redirección forma un ciclo.');
            $seen[] = $target;
            $next = DB::table('redirects')->where('old_path', $target)->where('enabled', true)->value('target_path');
            if (! $next) {
                break;
            }$target = $next;
        }abort_if($i === 20, 422, 'Cadena de redirecciones demasiado larga.');
        DB::table('redirects')->updateOrInsert(['old_path' => $d['old_path']], $d + ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('seo.redirect_saved', 'redirects');

        return back()->with('status', 'Redirección guardada.');
    }

    public function delete(int $id)
    {
        DB::table('redirects')->where('id', $id)->delete();
        Audit::record('seo.redirect_deleted', 'redirects', $id);

        return back()->with('status', 'Redirección eliminada.');
    }
}
