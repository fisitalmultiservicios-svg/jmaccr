<?php

namespace Tests\Feature;

use App\Domain\Catalog\CatalogImportPhotos;
use App\Domain\Catalog\CatalogImports;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ExcelPhotoImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->authenticateRole();
        Storage::disk('local')->deleteDirectory('media');
        Storage::disk('local')->deleteDirectory('catalog-imports');
    }

    private function file(string $type = 'anchors'): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/excel/nuevo-'.$type.'.xlsx'), 'catalogo-nuevo-con-fotos.xlsx', null, null, true);
    }

    private function preview(string $type = 'anchors', string $mode = 'append'): int
    {
        return app(CatalogImports::class)->preview($this->file($type), $mode, auth()->user());
    }

    private function prepare(int $id): object
    {
        for ($i = 0; $i < 10; $i++) {
            $batch = DB::table('import_batches')->find($id);
            if ($batch->status !== 'processing') {
                return $batch;
            }
            app(CatalogImports::class)->preparePhotos($id, auth()->user());
        }
        $this->fail('Photo processing did not finish');
    }

    public function test_exact_five_headers_with_anchored_photos_and_blank_row_keep_sku_association(): void
    {
        $id = $this->preview();
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'processing']);
        foreach (['products', 'media', 'categories'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $batch = $this->prepare($id);
        $this->assertSame('ready', $batch->status);
        $rows = app(CatalogImports::class)->rows($batch);
        $this->assertSame([2, 4], array_column($rows, 'line'));
        $this->assertSame('000045', $rows[0]['fields']['sku']);
        $this->assertCount(1, $rows[0]['photos']);
        $this->assertNotSame($rows[0]['photos'], $rows[1]['photos']);
        $this->get('/admin/catalog/imports/'.$id)->assertOk()->assertSee('/photos/0')->assertSee('autorizo su publicación');
        $this->get('/admin/catalog/imports/'.$id.'/photos/0')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->post('/admin/catalog/imports/'.$id.'/apply', ['_token' => 'test-csrf', 'confirm' => 1])->assertRedirect();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('media', 2);
        $this->assertDatabaseCount('product_media', 2);
        $this->assertSame(2, DB::table('media')->where('status', 'candidate')->count());
        $this->assertSame(2, DB::table('products')->where('status', 'draft')->count());
        $product = DB::table('products')->where('sku', '000045')->first();
        $this->assertSame('Descripción nueva QA 0', strip_tags($product->description));
        $this->assertStringStartsWith('media/', DB::table('media')->first()->path);
        $mapping = json_decode(DB::table('import_batches')->find($id)->mapping, true);
        $this->assertFalse(Storage::disk('local')->exists(app(CatalogImportPhotos::class)->sourcePath($mapping)));
        $this->get('/media/'.DB::table('media')->first()->id)->assertNotFound();
    }

    public function test_excel_365_in_cell_photos_can_be_explicitly_approved_and_published_once(): void
    {
        $id = $this->preview('in-cell');
        $this->assertSame('ready', $this->prepare($id)->status);
        $request = ['_token' => 'test-csrf', 'confirm' => 1, 'approve_photos' => 1, 'publish_products' => 1];
        $this->post('/admin/catalog/imports/'.$id.'/apply', $request)->assertRedirect();
        $this->assertSame(2, DB::table('products')->where('status', 'published')->count());
        $this->assertSame(2, DB::table('media')->where('status', 'approved')->count());
        $this->get('/api/catalog/search')->assertJsonPath('total', 2)->assertJsonCount(1, 'data.0.images');
        $this->get('/media/'.DB::table('media')->first()->id)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->post('/admin/catalog/imports/'.$id.'/apply', $request)->assertRedirect();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('media', 2);
    }

    public function test_large_photo_batch_is_finite_and_preparation_does_not_create_media_records(): void
    {
        $id = $this->preview('many');
        $this->post('/admin/catalog/imports/'.$id.'/apply', ['_token' => 'test-csrf', 'confirm' => 1])->assertSessionHasErrors('file');
        $this->post('/admin/catalog/imports/'.$id.'/prepare', ['_token' => 'test-csrf'])->assertRedirect();
        $batch = DB::table('import_batches')->find($id);
        $mapping = json_decode($batch->mapping, true);
        $this->assertGreaterThan(0, $mapping['photos_prepared']);
        $this->assertLessThanOrEqual(10, $mapping['photos_prepared']);
        $this->assertSame('processing', $batch->status);
        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame('ready', $this->prepare($id)->status);
    }

    public function test_photo_approval_publish_csrf_and_ownership_permissions_are_real(): void
    {
        $id = $this->preview();
        $this->post('/admin/catalog/imports/'.$id.'/prepare')->assertStatus(419);
        $this->prepare($id);
        $this->post('/admin/catalog/imports/'.$id.'/apply', ['_token' => 'test-csrf', 'confirm' => 1, 'publish_products' => 1])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('products', 0);
        Role::where('name', 'superadmin')->first()->permissions()->detach(Permission::where('name', 'media.approve')->first()->id);
        $this->post('/admin/catalog/imports/'.$id.'/apply', ['_token' => 'test-csrf', 'confirm' => 1, 'approve_photos' => 1])->assertForbidden();
        $this->authenticateRole();
        $this->get('/admin/catalog/imports/'.$id.'/photos/0')->assertNotFound();
    }

    public function test_cancel_expiry_and_corrupt_photo_do_not_leave_candidates_or_catalog_rows(): void
    {
        $id = $this->preview();
        $batch = $this->prepare($id);
        $mapping = json_decode($batch->mapping, true);
        $this->post('/admin/catalog/imports/'.$id.'/cancel', ['_token' => 'test-csrf'])->assertRedirect();
        $this->assertFalse(Storage::disk('local')->exists(app(CatalogImportPhotos::class)->sourcePath($mapping)));
        $id = $this->preview();
        DB::table('import_batches')->where('id', $id)->update(['created_at' => now()->subHours(3)]);
        app(CatalogImports::class)->cleanup();
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'expired']);
        $id = $this->preview();
        $batch = DB::table('import_batches')->find($id);
        $mapping = json_decode($batch->mapping, true);
        $source = Storage::disk('local')->path(app(CatalogImportPhotos::class)->sourcePath($mapping));
        $zip = new ZipArchive;
        $zip->open($source);
        $zip->addFromString($mapping['photo_parts'][0], 'Invalid photo QA');
        $zip->close();
        $mapping['source_sha256'] = hash_file('sha256', $source);
        DB::table('import_batches')->where('id', $id)->update(['mapping' => json_encode($mapping)]);
        $this->post('/admin/catalog/imports/'.$id.'/prepare', ['_token' => 'test-csrf'])->assertRedirect();
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'invalid']);
        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_database_failure_removes_written_photos_and_rolls_back_entire_catalog(): void
    {
        $id = $this->preview();
        $this->prepare($id);
        $armed = true;
        $inserted = 0;
        DB::listen(function ($query) use (&$armed, &$inserted) {
            if ($armed && preg_match('/^insert into ["`]?product_media["`]?\s/i', $query->sql) && ++$inserted === 2) {
                throw new \RuntimeException('Intentional photo transaction failure');
            }
        });
        try {
            $this->post('/admin/catalog/imports/'.$id.'/apply', ['_token' => 'test-csrf', 'confirm' => 1])->assertStatus(500);
        } finally {
            $armed = false;
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('local')->files('media'));
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'ready']);
    }

    public function test_reimport_same_photos_preserves_ids_and_can_approve_existing_candidates(): void
    {
        $id = $this->preview();
        $this->prepare($id);
        app(CatalogImports::class)->apply($id, auth()->user());
        $mediaIds = DB::table('media')->orderBy('id')->pluck('id')->all();
        $productIds = DB::table('products')->orderBy('id')->pluck('id')->all();
        $id = $this->preview();
        $this->prepare($id);
        app(CatalogImports::class)->apply($id, auth()->user(), true, true);
        $this->assertSame($mediaIds, DB::table('media')->orderBy('id')->pluck('id')->all());
        $this->assertSame($productIds, DB::table('products')->orderBy('id')->pluck('id')->all());
        $this->assertSame(2, DB::table('media')->where('status', 'approved')->count());
        $this->assertSame(2, DB::table('products')->where('status', 'published')->count());
    }

    public function test_catalog_search_and_photo_preparation_do_not_consume_confirmation_rate_limit(): void
    {
        $id = $this->preview('many');
        for ($i = 0; $i < 20; $i++) {
            $this->get('/api/catalog/search')->assertOk();
        }
        for ($i = 0; $i < 3 && DB::table('import_batches')->find($id)->status === 'processing'; $i++) {
            $this->post('/admin/catalog/imports/'.$id.'/prepare', ['_token' => 'test-csrf'])->assertRedirect();
        }
        $this->post('/admin/catalog/imports/'.$id.'/apply', ['_token' => 'test-csrf', 'confirm' => 1])->assertRedirect();
        $this->assertDatabaseCount('products', 12);
    }
}
