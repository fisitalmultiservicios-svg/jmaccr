<?php

namespace Tests\Feature;

use App\Domain\Catalog\CatalogImportPhotos;
use App\Domain\Catalog\CatalogImports;
use App\Domain\Catalog\ExcelCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ExcelPreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->authenticateRole();
    }

    private function preview(string $type = 'many'): int
    {
        $file = new UploadedFile(base_path('tests/Fixtures/excel/nuevo-'.$type.'.xlsx'), 'inventario-nuevo.xlsx', null, null, true);

        return app(CatalogImports::class)->preview($file, 'append', auth()->user());
    }

    private function prepare(int $id)
    {
        return $this->post('/admin/catalog/imports/'.$id.'/prepare', ['_token' => 'test-csrf']);
    }

    public function test_legacy_file_lock_does_not_cause_409_and_repeated_requests_preserve_exact_progress(): void
    {
        $id = $this->preview();
        $lock = fopen(app(ExcelCatalog::class)->directory().'/prepare-'.$id.'.lock', 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        try {
            $this->get('/admin/catalog/imports/'.$id)->assertOk()->assertSee('let submitting=false', false)->assertSee('clearTimeout(timer)', false)->assertSee('cancel-excel-photos');
            $this->prepare($id)->assertRedirect('/admin/catalog/imports/'.$id);
            $batch = DB::table('import_batches')->find($id);
            $mapping = json_decode($batch->mapping, true);
            $this->assertSame(10, $mapping['photos_prepared']);
            $this->assertSame('processing', $batch->status);
            $this->prepare($id)->assertRedirect('/admin/catalog/imports/'.$id);
            $batch = DB::table('import_batches')->find($id);
            $this->assertSame('ready', $batch->status);
            $this->assertSame(12, json_decode($batch->mapping, true)['photos_prepared']);
            $snapshot = (array) $batch;
            $this->prepare($id)->assertRedirect('/admin/catalog/imports/'.$id);
            $this->assertSame($snapshot, (array) DB::table('import_batches')->find($id));
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('media', 0);
            $this->assertDatabaseCount('import_errors', 0);
            $this->assertCount(12, array_filter(Storage::disk('local')->files('catalog-imports'), fn ($path) => str_contains($path, '-photo-')));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_stale_preparation_posts_for_completed_cancelled_invalid_and_expired_batches_are_safe_redirects(): void
    {
        $complete = $this->preview('anchors');
        $this->prepare($complete)->assertRedirect();
        $this->post('/admin/catalog/imports/'.$complete.'/apply', ['_token' => 'test-csrf', 'confirm' => 1])->assertRedirect();
        $snapshot = (array) DB::table('import_batches')->find($complete);
        $this->prepare($complete)->assertRedirect('/admin/catalog/imports/'.$complete);
        $this->assertSame($snapshot, (array) DB::table('import_batches')->find($complete));
        foreach (['cancelled', 'expired', 'invalid'] as $status) {
            $id = $this->preview('anchors');
            if ($status === 'cancelled') {
                $this->post('/admin/catalog/imports/'.$id.'/cancel', ['_token' => 'test-csrf'])->assertRedirect();
            } elseif ($status === 'expired') {
                DB::table('import_batches')->where('id', $id)->update(['created_at' => now()->subHours(3)]);
                app(CatalogImports::class)->cleanup();
            } else {
                $mapping = json_decode(DB::table('import_batches')->find($id)->mapping, true);
                $path = Storage::disk('local')->path(app(CatalogImportPhotos::class)->sourcePath($mapping));
                $zip = new ZipArchive;
                $zip->open($path);
                $zip->addFromString($mapping['photo_parts'][0], 'Inert invalid QA photograph');
                $zip->close();
                $mapping['source_sha256'] = hash_file('sha256', $path);
                DB::table('import_batches')->where('id', $id)->update(['mapping' => json_encode($mapping)]);
                $this->prepare($id)->assertRedirect();
            }
            $batch = DB::table('import_batches')->find($id);
            $this->assertSame($status, $batch->status);
            $snapshot = (array) $batch;
            $this->prepare($id)->assertRedirect('/admin/catalog/imports/'.$id);
            $this->assertSame($snapshot, (array) DB::table('import_batches')->find($id));
        }
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('media', 2);
    }

    public function test_preparation_retries_still_require_csrf_owner_and_media_permission(): void
    {
        $id = $this->preview('anchors');
        $this->post('/admin/catalog/imports/'.$id.'/prepare')->assertStatus(419);
        $this->prepare($id)->assertRedirect();
        Role::where('name', 'superadmin')->first()->permissions()->detach(Permission::where('name', 'media.create')->first()->id);
        $this->prepare($id)->assertForbidden();
        $this->authenticateRole();
        $this->prepare($id)->assertNotFound();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('media', 0);
    }
}
