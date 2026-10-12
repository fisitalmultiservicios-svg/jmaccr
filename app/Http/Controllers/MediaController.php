<?php

namespace App\Http\Controllers;

use App\Domain\Auth\Audit;
use App\Domain\Media\ImageUpload;
use App\Domain\Media\PdfUpload;
use App\Domain\Media\References;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index()
    {
        return view('admin.media', ['items' => DB::table('media')->orderByDesc('id')->paginate(20), 'candidates' => array_map('basename', glob(resource_path('media/candidates/*')))]);
    }

    public function store(Request $request, ImageUpload $upload)
    {
        $data = $request->validate(['file' => 'required|file|max:8192', 'alt' => 'required|string|max:255']);
        $id = strtolower($data['file']->getClientOriginalExtension()) === 'pdf' ? app(PdfUpload::class)->store($data['file'], $request->user()->id, $data['alt']) : $upload->store($data['file'], $request->user()->id, $data['alt']);
        Audit::record('media.created', 'media', $id, ['status' => 'candidate']);

        return redirect('/admin/media')->with('status', 'Archivo reprocesado y guardado como candidato; no está publicado.');
    }

    public function preview(int $id)
    {
        $item = DB::table('media')->find($id);
        abort_unless($item, 404);
        if ($item->mime === 'application/pdf') {
            return Storage::disk($item->disk)->download($item->path, 'documento-jmac.pdf', ['Content-Type' => $item->mime, 'Cache-Control' => 'private, no-store']);
        }
        $variants = json_decode($item->variants_json ?? '{}', true);
        $path = request('size') === 'thumb' && isset($variants['thumb']) ? $variants['thumb']['path'] : $item->path;

        return Storage::disk($item->disk)->response($path, null, ['Content-Type' => $item->mime, 'Cache-Control' => 'private, no-store']);
    }

    public function candidate(string $name)
    {
        abort_unless(basename($name) === $name && in_array($name, array_map('basename', glob(resource_path('media/candidates/*'))), true), 404);

        return response()->file(resource_path('media/candidates/'.$name), ['Cache-Control' => 'private, no-store']);
    }

    public function importCandidate(Request $request, string $name, ImageUpload $upload)
    {
        abort_unless(basename($name) === $name && in_array($name, array_map('basename', glob(resource_path('media/candidates/*'))), true), 404);
        $file = new UploadedFile(resource_path('media/candidates/'.$name), $name, null, null, true);
        $id = $upload->store($file, $request->user()->id, pathinfo($name, PATHINFO_FILENAME));
        Audit::record('media.candidate_loaded', 'media', $id, ['status' => 'candidate']);

        return back()->with('status', 'Candidato cargado a Medios. No está aprobado ni publicado.');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['alt' => 'required|string|max:255']);
        abort_unless(DB::table('media')->where('id', $id)->exists(), 404);
        DB::table('media')->where('id', $id)->update($data + ['updated_at' => now()]);
        Audit::record('media.updated', 'media', $id);

        return back()->with('status', 'Descripción actualizada.');
    }

    public function approve(Request $request, int $id)
    {
        $request->validate(['owner_approved' => 'accepted']);
        abort_unless(DB::table('media')->where('id', $id)->exists(), 404);
        DB::table('media')->where('id', $id)->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id, 'updated_at' => now()]);
        Audit::record('media.approved', 'media', $id, ['status' => 'approved']);

        return back()->with('status', 'Media aprobada por el usuario autorizado.');
    }

    public function delete(int $id)
    {
        $item = DB::table('media')->find($id);
        abort_unless($item, 404);
        abort_if(References::used($id), 422, 'El archivo está vinculado a contenido o revisiones. No se puede eliminar.');
        DB::table('media')->where('id', $id)->delete();
        Storage::disk($item->disk)->delete($item->path);
        foreach (json_decode($item->variants_json ?? '{}', true) as $variant) {
            Storage::disk($item->disk)->delete($variant['path']);
        }Audit::record('media.deleted', 'media', $id);

        return back()->with('status', 'Archivo sin referencias eliminado.');
    }
}
