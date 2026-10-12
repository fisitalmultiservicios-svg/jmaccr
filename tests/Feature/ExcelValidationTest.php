<?php

namespace Tests\Feature;

use App\Domain\Catalog\CatalogImports;
use App\Domain\Catalog\ExcelCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class ExcelValidationTest extends TestCase
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

    private function file(array $parts = [], ?string $printerType = null, bool $override = false): UploadedFile
    {
        $path = tempnam(app(ExcelCatalog::class)->directory(), 'validation-');
        $this->files[] = $path;
        copy(base_path('tests/Fixtures/excel/nuevo-anchors.xlsx'), $path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        if ($printerType !== null) {
            $part = 'xl/printerSettings/printerSettings1.bin';
            $parts[$part] ??= str_repeat("\0", 220);
            $document = new \DOMDocument;
            $document->loadXML($zip->getFromName('[Content_Types].xml'));
            $entry = $document->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', $override ? 'Override' : 'Default');
            $entry->setAttribute($override ? 'PartName' : 'Extension', $override ? '/'.$part : 'bin');
            $entry->setAttribute('ContentType', $printerType);
            $document->documentElement->appendChild($entry);
            $zip->addFromString('[Content_Types].xml', $document->saveXML());
            $document->loadXML($zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels'));
            $entry = $document->createElementNS('http://schemas.openxmlformats.org/package/2006/relationships', 'Relationship');
            $entry->setAttribute('Id', 'rIdPrinter');
            $entry->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/printerSettings');
            $entry->setAttribute('Target', '../printerSettings/printerSettings1.bin');
            $document->documentElement->appendChild($entry);
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $document->saveXML());
            $document->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'));
            $entry = $document->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'pageSetup');
            $entry->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', 'rIdPrinter');
            $document->documentElement->insertBefore($entry, $document->getElementsByTagNameNS('*', 'drawing')->item(0));
            $zip->addFromString('xl/worksheets/sheet1.xml', $document->saveXML());
        }
        foreach ($parts as $name => $bytes) {
            if (is_array($bytes)) {
                $zip->addFile($bytes['file'], $name);
            } else {
                $zip->addFromString($name, $bytes);
            }
        }
        $zip->close();

        return new UploadedFile($path, 'inventario-nuevo.xlsx', null, null, true);
    }

    private function assertRejected(UploadedFile $file, string $message): void
    {
        $id = app(CatalogImports::class)->preview($file, 'append', auth()->user());
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'invalid']);
        $this->assertStringContainsString($message, DB::table('import_errors')->where('import_batch_id', $id)->value('safe_error'));
        foreach (['products', 'categories', 'brands', 'media'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_standard_ignored_printer_settings_preserve_photo_import_and_sku_with_default_or_override_type(): void
    {
        foreach ([false, true] as $override) {
            $file = $this->file(printerType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.printerSettings', override: $override);
            $this->post('/admin/catalog/imports', ['_token' => 'test-csrf', 'file' => $file, 'mode' => 'append', 'new_catalog' => 1])->assertRedirect();
            $id = DB::table('import_batches')->max('id');
            $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'processing']);
            $this->post('/admin/catalog/imports/'.$id.'/prepare', ['_token' => 'test-csrf'])->assertRedirect();
            $batch = DB::table('import_batches')->find($id);
            $this->assertSame('ready', $batch->status);
            $rows = app(CatalogImports::class)->rows($batch);
            $this->assertSame([2, 4], array_column($rows, 'line'));
            $this->assertSame('000045', $rows[0]['fields']['sku']);
            $this->assertCount(1, $rows[0]['photos']);
            $this->get('/admin/catalog/imports/'.$id.'/photos/0')->assertOk()->assertHeader('Content-Type', 'image/webp');
            $this->post('/admin/catalog/imports/'.$id.'/apply', ['_token' => 'test-csrf', 'confirm' => 1])->assertRedirect();
            $this->assertDatabaseCount('products', 2);
            $this->assertDatabaseCount('media', 2);
            $this->assertDatabaseCount('product_media', 2);
        }
    }

    public function test_other_binaries_macros_objects_and_false_printer_types_are_still_rejected(): void
    {
        foreach ([
            ['xl/vbaProject.bin', 'macros'],
            ['xl/embeddings/oleObject1.bin', 'objetos incrustados'],
            ['xl/other.bin', 'archivo binario'],
            ['xl/externalLinks/externalLink1.xml', 'conexiones externas'],
            ['xl/active.js', 'archivos ejecutables'],
        ] as [$part, $message]) {
            $this->assertRejected($this->file([$part => 'Inert QA bytes, never executed']), $message);
        }
        $this->assertRejected($this->file(['xl/printerSettings/printerSettings1.bin' => 'Inert QA']), 'tipo no admitido');
        $this->assertRejected($this->file(printerType: 'application/vnd.ms-office.vbaProject'), 'tipo no admitido');
        $this->assertRejected($this->file(['xl/printerSettings/printerSettings1.bin' => str_repeat('x', 256 * 1024 + 1)], 'application/vnd.openxmlformats-officedocument.spreadsheetml.printerSettings'), 'impresión del Excel superan 256 KB');
    }

    public function test_oversized_photo_xml_and_expanded_book_report_the_actual_limit_without_catalog_writes(): void
    {
        $this->assertRejected($this->file(['xl/media/grande.png' => str_repeat('x', 11 * 1024 * 1024)]), 'fotografía excede los 8 MB');
        $this->assertRejected($this->file(['xl/large.xml' => str_repeat('x', 11 * 1024 * 1024)]), 'parte XML del Excel supera 10 MB');
        $parts = [];
        $padding = tempnam(app(ExcelCatalog::class)->directory(), 'expanded-');
        $this->files[] = $padding;
        file_put_contents($padding, str_repeat('x', 8 * 1024 * 1024));
        for ($index = 0; $index < 33; $index++) {
            $parts['xl/media/expansion'.$index.'.png'] = ['file' => $padding];
        }
        $file = $this->file($parts);
        $this->assertLessThan(1024 * 1024, $file->getSize());
        $this->assertRejected($file, 'supera 256 MB al descomprimirlo');
    }
}
