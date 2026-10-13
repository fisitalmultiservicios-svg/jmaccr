<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CatalogFileLimits;
use App\Domain\Catalog\CatalogImportPhotos;
use App\Domain\Catalog\CatalogImports;
use App\Domain\Catalog\ExcelCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogImportController extends Controller
{
    private function authorizeImport(Request $request): void
    {
        abort_unless(CatalogImports::allowed($request->user()), 403);
    }

    public function index(Request $request, CatalogImports $imports)
    {
        $this->authorizeImport($request);
        $imports->cleanup();

        return view('admin.catalog-import', ['limits' => CatalogFileLimits::summary(), 'canReplace' => CatalogImports::canReplace($request->user()), 'batches' => DB::table('import_batches')->where('actor_id', $request->user()->id)->orderByDesc('id')->paginate(20)]);
    }

    public function template(Request $request, ExcelCatalog $excel)
    {
        $this->authorizeImport($request);

        return response()->download($excel->template(), 'JMAC-plantilla-productos.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }

    public function preview(Request $request, CatalogImports $imports)
    {
        $this->authorizeImport($request);
        $data = $request->validate(['file' => 'required|file|extensions:xlsx|max:131072', 'mode' => 'required|in:append,replace', 'new_catalog' => 'accepted']);
        $id = $imports->preview($request->file('file'), $data['mode'], $request->user());

        return redirect('/admin/catalog/imports/'.$id);
    }

    private function batch(Request $request, int $id): object
    {
        $this->authorizeImport($request);
        $batch = DB::table('import_batches')->where('actor_id', $request->user()->id)->find($id);
        abort_unless($batch, 404);

        return $batch;
    }

    public function show(Request $request, int $id, CatalogImports $imports)
    {
        $imports->cleanup();
        $batch = $this->batch($request, $id);
        $mapping = json_decode($batch->mapping, true);
        $counts = json_decode($batch->counts, true);
        $page = $request->validate(['page' => 'nullable|integer|min:1|max:40'])['page'] ?? 1;
        $rows = $batch->status === 'ready' ? array_slice($imports->rows($batch), ($page - 1) * 50, 50) : [];

        return view('admin.catalog-import-preview', ['batch' => $batch, 'mapping' => $mapping, 'counts' => $counts, 'rows' => $rows, 'pageNumber' => (int) $page, 'canApprovePhotos' => $request->user()->hasPermission('media.approve'), 'canPublishProducts' => $request->user()->hasPermission('products.publish'), 'errorsList' => DB::table('import_errors')->where('import_batch_id', $id)->orderBy('row_number')->paginate(50)]);
    }

    public function prepare(Request $request, int $id, CatalogImports $imports)
    {
        $this->batch($request, $id);
        if (! $imports->preparePhotos($id, $request->user())) {
            return redirect('/admin/catalog/imports/'.$id)->with('photo_retry_delay', 5000)->with('status', 'Otra petición está preparando estas fotografías. El avance se conserva y esta pantalla volverá a comprobarlo.');
        }

        return redirect('/admin/catalog/imports/'.$id);
    }

    public function photo(Request $request, int $id, int $index, CatalogImportPhotos $photos)
    {
        $batch = $this->batch($request, $id);
        abort_unless($batch->status === 'ready' && $request->user()->hasPermission('media.create'), 404);
        $image = $photos->image(json_decode($batch->mapping, true), $index);

        return response($image['thumb_bytes'], 200, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function apply(Request $request, int $id, CatalogImports $imports)
    {
        $batch = $this->batch($request, $id);
        $mode = json_decode($batch->mapping, true)['mode'];
        $request->validate(['confirm' => 'accepted', 'phrase' => $mode === 'replace' ? 'required|in:REEMPLAZAR' : 'nullable|string|max:30', 'approve_photos' => 'sometimes|accepted', 'publish_products' => 'sometimes|accepted']);
        $imports->apply($id, $request->user(), $request->boolean('approve_photos'), $request->boolean('publish_products'));

        return redirect('/admin/catalog/imports/'.$id)->with('status', 'Importación terminada. El catálogo se guardó y el histórico de preventas se conserva.');
    }

    public function cancel(Request $request, int $id, CatalogImports $imports)
    {
        $this->batch($request, $id);
        $imports->cancel($id, $request->user());

        return redirect('/admin/catalog/imports')->with('status', 'Vista previa cancelada; el catálogo no cambió.');
    }
}
