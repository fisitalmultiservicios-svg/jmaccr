<?php

namespace Tests\Feature;

use App\Domain\Catalog\CatalogImports;
use App\Domain\Catalog\ExcelCatalog;
use App\Domain\System\Backups;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;
use ZipArchive;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->authenticateRole();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function excel(array $rows, array $headers = ['SKU', 'Nombre', 'Categoría', 'Marca', 'Estado', 'Precio interno']): UploadedFile
    {
        $path = tempnam(app(ExcelCatalog::class)->directory(), 'qa-');
        $this->files[] = $path;
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'catalogo-nuevo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function preview(UploadedFile $file, string $mode = 'append'): int
    {
        $response = $this->post('/admin/catalog/imports', ['_token' => 'test-csrf', 'file' => $file, 'mode' => $mode, 'new_catalog' => 1]);
        $response->assertRedirect();
        $this->assertNotNull($response->headers->get('Location'));
        $id = DB::table('import_batches')->max('id');
        $this->assertNotNull($id, json_encode(session('errors')?->all()));

        return $id;
    }

    private function apply(int $id, array $extra = [])
    {
        return $this->post('/admin/catalog/imports/'.$id.'/apply', $extra + ['_token' => 'test-csrf', 'confirm' => 1, 'phrase' => 'REEMPLAZAR']);
    }

    public function test_real_xlsx_preview_changes_nothing_then_apply_creates_catalog_and_real_encrypted_backup(): void
    {
        $this->get('/admin/catalog/products')->assertSee('Cargar productos desde Excel');
        $id = $this->preview($this->excel([['NUEVO-EXCEL-01', 'Producto Excel nuevo', 'Categoría Excel nueva', 'Marca Excel nueva', 'publicado', 123456.50]]));
        foreach (['products', 'categories', 'brands'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->get('/admin/catalog/imports/'.$id)->assertOk()->assertSee('Producto Excel nuevo')->assertSee('Confirmar carga de productos');
        $batch = DB::table('import_batches')->find($id);
        $mapping = json_decode($batch->mapping, true);
        $this->assertStringNotContainsString('Producto Excel nuevo', Storage::disk('local')->get('catalog-imports/'.$mapping['token'].'.jci'));
        $this->apply($id)->assertRedirect('/admin/catalog/imports/'.$id);
        $this->assertDatabaseHas('products', ['sku' => 'NUEVO-EXCEL-01', 'status' => 'published']);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('brands', 1);
        $this->get('/api/catalog/search?q=excel')->assertJsonPath('total', 1)->assertDontSee('123456.5');
        $this->assertCount(1, app(Backups::class)->inventory());
        $this->apply($id)->assertRedirect();
        $this->assertDatabaseCount('products', 1);
        $this->assertCount(1, app(Backups::class)->inventory());
        $this->assertFalse(Storage::disk('local')->exists('catalog-imports/'.$mapping['token'].'.jci'));
    }

    public function test_append_updates_matching_sku_and_preserves_other_products_slug_media_and_snapshots(): void
    {
        $old = DB::table('products')->insertGetId(['sku' => 'EXCEL-01', 'slug' => 'url-estable', 'name' => 'Nombre anterior', 'summary' => 'Conservar resumen', 'status' => 'published']);
        DB::table('products')->insert(['sku' => 'OTRO-01', 'slug' => 'otro', 'name' => 'Otro existente']);
        $mediaId = DB::table('media')->insertGetId(['disk' => 'local', 'path' => 'media/00000000-0000-4000-8000-000000000000.webp', 'mime' => 'image/webp', 'bytes' => 1, 'checksum' => str_repeat('a', 64), 'alt' => 'Medio nuevo QA', 'status' => 'approved']);
        DB::table('product_media')->insert(['product_id' => $old, 'media_id' => $mediaId]);
        $id = $this->preview($this->excel([['EXCEL-01', 'Nombre actualizado'], ['EXCEL-02', 'Otro nuevo']], ['SKU', 'Nombre']));
        $this->apply($id)->assertRedirect();
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseHas('products', ['id' => $old, 'name' => 'Nombre actualizado', 'slug' => 'url-estable', 'summary' => 'Conservar resumen', 'status' => 'published']);
        $this->assertDatabaseCount('product_media', 1);
    }

    public function test_replace_requires_confirmation_retires_absent_records_and_preserves_preorder_snapshots(): void
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Categoría actual nueva', 'slug' => 'cat-actual', 'status' => 'published']);
        $brand = DB::table('brands')->insertGetId(['name' => 'Marca actual nueva', 'slug' => 'marca-actual', 'status' => 'published']);
        $old = DB::table('products')->insertGetId(['sku' => 'ANTES-01', 'slug' => 'antes', 'name' => 'Producto actual nuevo', 'status' => 'published', 'category_primary_id' => $category, 'brand_id' => $brand]);
        $preorder = DB::table('preorders')->insertGetId(['year' => 2026, 'seq' => 1, 'public_code' => 'QA-2026-000001', 'idempotency_key' => '00000000-0000-4000-8000-000000000000', 'session_hash' => str_repeat('a', 64), 'request_hash' => str_repeat('b', 64), 'customer_name' => encrypt('Cliente QA'), 'customer_phone' => encrypt('00000000'), 'consent_version' => 'qa']);
        DB::table('preorder_items')->insert(['preorder_id' => $preorder, 'product_id' => $old, 'sku_snapshot' => 'ANTES-01', 'name_snapshot' => 'Nombre histórico QA', 'unit_snapshot' => 'unidad', 'quantity' => 2, 'metadata_snapshot' => '{}']);
        $id = $this->preview($this->excel([['DESPUES-01', 'Producto del Excel nuevo', 'Agrupación nueva', 'Marca nueva', 'publicado', '']]), 'replace');
        $this->apply($id, ['phrase' => 'incorrecto'])->assertSessionHasErrors('phrase');
        $this->assertNull(DB::table('products')->where('id', $old)->value('deleted_at'));
        $this->apply($id)->assertRedirect();
        $this->assertNotNull(DB::table('products')->where('id', $old)->value('deleted_at'));
        $this->assertDatabaseHas('categories', ['id' => $category, 'status' => 'hidden']);
        $this->assertDatabaseHas('brands', ['id' => $brand, 'status' => 'hidden']);
        $this->assertDatabaseHas('preorder_items', ['product_id' => $old, 'name_snapshot' => 'Nombre histórico QA', 'quantity' => 2]);
        $this->assertDatabaseCount('preorders', 1);
        $this->get('/api/catalog/search')->assertJsonPath('total', 1)->assertJsonPath('data.0.sku', 'DESPUES-01');
    }

    public function test_duplicate_and_invalid_rows_reject_entire_file_without_partial_import(): void
    {
        $id = $this->preview($this->excel([['MISMO-01', 'Primero', '', '', 'borrador', ''], ['MISMO-01', 'Segundo', '', '', 'borrador', ''], ['INVALIDO-01', '', '', '', 'borrador', -1]]));
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'invalid']);
        $this->assertDatabaseCount('import_errors', 2);
        $this->get('/admin/catalog/imports/'.$id)->assertSee('SKU repetido')->assertDontSee('Confirmar carga de productos');
        $this->apply($id)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_empty_formulas_macros_external_relationships_and_wordpress_headers_are_rejected(): void
    {
        foreach (['empty', 'formula', 'macro', 'external', 'wordpress'] as $case) {
            $file = $this->excel($case === 'empty' ? [] : [[$case === 'formula' ? '=1+1' : 'SEGURO-01', 'Seguro nuevo']], $case === 'wordpress' ? ['post_type', 'post_title'] : ['SKU', 'Nombre']);
            if (in_array($case, ['macro', 'external'])) {
                $zip = new ZipArchive;
                $zip->open($file->getRealPath());
                if ($case === 'macro') {
                    $zip->addFromString('xl/vbaProject.bin', 'No executable QA content');
                } else {
                    $zip->addFromString('xl/_rels/extra.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId9" Type="x" TargetMode="External" Target="https://example.test"/></Relationships>');
                }
                $zip->close();
            }
            // Service exercise avoids the intentional three-per-minute upload throttle.
            $id = app(CatalogImports::class)->preview($file, 'replace', auth()->user());
            $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'invalid']);
            $this->assertDatabaseCount('products', 0);
        }
    }

    public function test_cancel_expiry_payload_tamper_and_catalog_changes_prevent_writes(): void
    {
        $file = $this->excel([['SEGURO-01', 'Nuevo seguro']], ['SKU', 'Nombre']);
        $id = $this->preview($file);
        $this->post('/admin/catalog/imports/'.$id.'/cancel', ['_token' => 'test-csrf'])->assertRedirect();
        $this->apply($id)->assertSessionHasErrors('file');
        $id = app(CatalogImports::class)->preview($file, 'append', auth()->user());
        DB::table('import_batches')->where('id', $id)->update(['created_at' => now()->subHours(3)]);
        $this->apply($id)->assertSessionHasErrors('file');
        $id = app(CatalogImports::class)->preview($file, 'append', auth()->user());
        $mapping = json_decode(DB::table('import_batches')->where('id', $id)->value('mapping'), true);
        Storage::disk('local')->put('catalog-imports/'.$mapping['token'].'.jci', 'tampered');
        $this->apply($id)->assertSessionHasErrors('file');
        $id = app(CatalogImports::class)->preview($file, 'append', auth()->user());
        DB::table('products')->insert(['sku' => 'ENTRE-01', 'slug' => 'entre', 'name' => 'Cambio intermedio nuevo']);
        $this->apply($id)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_failed_backup_rolls_back_before_any_catalog_change(): void
    {
        $id = $this->preview($this->excel([['SEGURO-01', 'Nuevo seguro']], ['SKU', 'Nombre']), 'replace');
        $keyPath = app(Backups::class)->keyPath();
        $original = is_file($keyPath) ? file_get_contents($keyPath) : null;
        file_put_contents($keyPath, 'Invalid disposable test key');
        try {
            $this->apply($id)->assertSessionHasErrors('file');
        } finally {
            if ($original === null) {
                unlink($keyPath);
            } else {
                file_put_contents($keyPath, $original);
            }
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'ready']);
    }

    public function test_permissions_csrf_batch_ownership_and_replace_permission_are_enforced(): void
    {
        $id = $this->preview($this->excel([['SEGURO-01', 'Nuevo seguro']], ['SKU', 'Nombre']));
        $this->post('/admin/catalog/imports/'.$id.'/apply', ['confirm' => 1])->assertStatus(419);
        $this->authenticateRole();
        $this->get('/admin/catalog/imports/'.$id)->assertNotFound();
        $this->apply($id)->assertNotFound();
        $this->authenticateRole('ventas');
        $this->get('/admin/catalog/imports')->assertForbidden();
        $this->post('/admin/catalog/imports', ['_token' => 'test-csrf'])->assertForbidden();
        $this->authenticateRole('gestor_catalogo');
        Role::where('name', 'gestor_catalogo')->first()->permissions()->detach(Permission::where('name', 'products.delete')->value('id'));
        $this->get('/admin/catalog/imports')->assertOk()->assertDontSee('value="replace"', false);
        $this->post('/admin/catalog/imports', ['_token' => 'test-csrf', 'file' => $this->excel([['SEGURO-01', 'Seguro nuevo']], ['SKU', 'Nombre']), 'mode' => 'replace', 'new_catalog' => 1])->assertForbidden();
    }

    public function test_template_contains_no_products_and_upload_requires_new_catalog_acknowledgement(): void
    {
        $this->get('/admin/catalog/imports/template')->assertOk()->assertDownload('JMAC-plantilla-productos.xlsx');
        $this->post('/admin/catalog/imports', ['_token' => 'test-csrf', 'file' => $this->excel([['NUEVO-01', 'Nuevo']], ['SKU', 'Nombre']), 'mode' => 'append'])->assertSessionHasErrors('new_catalog');
        foreach (['products', 'categories', 'brands', 'preorders', 'posts'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_leading_zero_sku_blank_cells_and_escaped_content_survive_real_excel(): void
    {
        $id = $this->preview($this->excel([['000012', '<img src=x onerror=alert(1)>', '', 'Marca numérica nueva', 'borrador', 0]]));
        $this->get('/admin/catalog/imports/'.$id)->assertOk()->assertSee('&lt;img', false)->assertDontSee('<img src=x', false);
        $this->apply($id)->assertRedirect();
        $product = DB::table('products')->first();
        $this->assertSame('000012', $product->sku);
        $this->assertNull($product->category_primary_id);
        $this->assertSame('Marca numérica nueva', DB::table('brands')->where('id', $product->brand_id)->value('name'));
        $this->assertEquals(0, $product->private_price_crc);
    }

    public function test_xml_entities_oversized_dimensions_and_compressed_bomb_are_rejected_before_reader(): void
    {
        foreach (['entity', 'dimension', 'compressed'] as $case) {
            $file = $this->excel([['NUEVO-01', 'Nuevo seguro']], ['SKU', 'Nombre']);
            $zip = new ZipArchive;
            $zip->open($file->getRealPath());
            if ($case === 'entity') {
                $zip->addFromString('xl/styles.xml', '<!DOCTYPE x [<!ENTITY test SYSTEM "file:///unread-qa">]><x>&test;</x>');
            } elseif ($case === 'dimension') {
                $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                $xml = str_replace('<sheetData>', '<dimension ref="A1:XFD1048576"/><sheetData>', $xml);
                $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            } else {
                $zip->addFromString('xl/oversized.xml', str_repeat('A', 17 * 1024 * 1024));
            }
            $zip->close();
            $id = app(CatalogImports::class)->preview($file, 'replace', auth()->user());
            $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'invalid']);
            $this->assertDatabaseCount('products', 0);
        }
    }

    public function test_database_failure_mid_import_rolls_back_all_rows_and_leaves_original_catalog(): void
    {
        DB::table('products')->insert(['sku' => 'CONSERVAR-01', 'name' => 'Actual nuevo', 'slug' => 'conservar-actual', 'status' => 'published']);
        $id = $this->preview($this->excel([['NUEVO-01', 'Uno nuevo'], ['NUEVO-02', 'Dos nuevos']], ['SKU', 'Nombre']), 'replace');
        $armed = true;
        $inserted = 0;
        DB::listen(function ($query) use (&$armed, &$inserted) {
            if ($armed && preg_match('/^insert into ["`]?products["`]?\s/i', $query->sql) && ++$inserted === 2) {
                throw new \RuntimeException('Deliberate disposable database failure');
            }
        });
        try {
            $this->apply($id)->assertStatus(500);
        } finally {
            $armed = false;
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['sku' => 'CONSERVAR-01', 'deleted_at' => null]);
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'ready']);
    }
}
