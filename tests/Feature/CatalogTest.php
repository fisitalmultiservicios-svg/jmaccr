<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->authenticateRole();
    }

    private function product(array $overrides = []): array
    {
        return array_replace(['_token' => 'test-csrf', 'name' => 'Rodamiento técnico nuevo', 'slug' => 'rodamiento-tecnico-nuevo', 'sku' => 'NUEVO-01', 'summary' => 'Información nueva para prueba', 'description' => '<p>Ficha nueva</p>', 'status' => 'draft', 'unit' => 'unidad', 'is_featured' => 0, 'position' => 0, 'private_price_crc' => 98765], $overrides);
    }

    public function test_new_category_brand_and_product_are_saved_then_found_only_after_publication(): void
    {
        $this->post('/admin/catalog/categories', ['_token' => 'test-csrf', 'name' => 'Categoría nueva', 'slug' => 'categoria-nueva', 'status' => 'published', 'position' => 0])->assertRedirect();
        $category = DB::table('categories')->first();
        $this->post('/admin/catalog/brands', ['_token' => 'test-csrf', 'name' => 'Marca nueva', 'slug' => 'marca-nueva', 'status' => 'published', 'position' => 0])->assertRedirect();
        $brand = DB::table('brands')->first();
        $payload = $this->product(['category_primary_id' => $category->id, 'brand_id' => $brand->id, 'attributes' => [['name' => 'Diámetro', 'value' => '12', 'unit' => 'mm']]]);
        $this->post('/admin/catalog/products', $payload)->assertRedirect();
        $product = DB::table('products')->first();
        $this->get('/api/catalog/search?q=tecnico')->assertJsonPath('total', 0);
        $payload['status'] = 'published';
        $this->post('/admin/catalog/products/'.$product->id, $payload)->assertRedirect();
        foreach (['técnico', 'NUEVO-01'] as $q) {
            $this->get('/api/catalog/search?q='.urlencode($q))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.sku', 'NUEVO-01')->assertDontSee('private_price_crc')->assertDontSee('98765');
        }
        $this->get('/catalogo/rodamiento-tecnico-nuevo/')->assertOk()->assertSee('Diámetro')->assertSee('12')->assertDontSee('98765');
        $this->get('/catalogo/?jmac_cat=categoria-nueva')->assertSee('Rodamiento técnico nuevo');
        $this->get('/api/catalog/search?attributes[Diámetro]=12')->assertJsonPath('total', 1);
        $this->get('/admin/catalog/products/'.$product->id.'/edit')->assertOk();
    }

    public function test_duplicate_sku_missing_sku_and_archival_are_safe(): void
    {
        $this->post('/admin/catalog/products', $this->product(['status' => 'published']))->assertRedirect();
        $this->post('/admin/catalog/products', $this->product(['slug' => 'otro-producto']))->assertSessionHasErrors('sku');
        $this->postJson('/admin/catalog/products', $this->product(['slug' => 'sin-sku', 'sku' => null, 'status' => 'published']))->assertUnprocessable();
        $this->assertDatabaseCount('products', 1);
        $id = DB::table('products')->value('id');
        $this->post('/admin/catalog/products/'.$id.'/archive', ['_token' => 'test-csrf'])->assertRedirect();
        $this->get('/api/catalog/search')->assertJsonPath('total', 0);
        $this->get('/catalogo/rodamiento-tecnico-nuevo/')->assertNotFound();
        $this->post('/admin/catalog/products/'.$id.'/delete', ['_token' => 'test-csrf'])->assertRedirect();
        $this->assertNotNull(DB::table('products')->where('id', $id)->value('deleted_at'));
        $this->post('/admin/catalog/products/'.$id.'/restore', ['_token' => 'test-csrf'])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $id, 'status' => 'draft', 'deleted_at' => null]);
    }

    public function test_taxonomy_cycles_duplicate_slugs_and_referenced_deletion_are_rejected(): void
    {
        $parent = DB::table('categories')->insertGetId(['name' => 'Padre nuevo', 'slug' => 'padre-nuevo']);
        $child = DB::table('categories')->insertGetId(['name' => 'Hijo nuevo', 'slug' => 'hijo-nuevo', 'parent_id' => $parent]);
        $this->post('/admin/catalog/categories/'.$parent, ['_token' => 'test-csrf', 'name' => 'Padre nuevo', 'slug' => 'padre-nuevo', 'status' => 'hidden', 'position' => 0, 'parent_id' => (string) $child])->assertSessionHasErrors('parent_id');
        $this->post('/admin/catalog/categories', ['_token' => 'test-csrf', 'name' => 'Otro', 'slug' => 'padre-nuevo', 'status' => 'hidden', 'position' => 0])->assertSessionHasErrors('slug');
        $this->post('/admin/catalog/categories/'.$parent.'/delete', ['_token' => 'test-csrf'])->assertUnprocessable();
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_legacy_filter_and_sql_special_characters_are_safe_and_empty(): void
    {
        $this->get('/catalogo/?jmac_cat=cadenas')->assertOk()->assertSee('No encontramos resultados');
        $this->get('/api/catalog/search?q='.urlencode("%' OR 1=1 --"))->assertOk()->assertJsonPath('total', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_duplicate_creates_draft_without_reusing_unique_sku(): void
    {
        $this->post('/admin/catalog/products', $this->product(['status' => 'published']))->assertRedirect();
        $id = DB::table('products')->value('id');
        $this->post('/admin/catalog/products/'.$id.'/duplicate', ['_token' => 'test-csrf'])->assertRedirect();
        $this->assertDatabaseCount('products', 2);
        $copy = DB::table('products')->where('id', '!=', $id)->first();
        $this->assertNull($copy->sku);
        $this->assertSame('draft', $copy->status);
        $this->get('/api/catalog/search')->assertJsonPath('total', 1);
    }

    public function test_sales_cannot_change_catalog(): void
    {
        $this->authenticateRole('ventas');
        $this->get('/admin/catalog/products')->assertForbidden();
        $this->post('/admin/catalog/products', $this->product())->assertForbidden();
        $this->post('/admin/catalog/categories', ['_token' => 'test-csrf'])->assertForbidden();
    }

    public function test_one_thousand_new_fixtures_are_paginated_without_exposing_private_fields(): void
    {
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['name' => 'Producto nuevo '.$i, 'slug' => 'nuevo-'.$i, 'sku' => 'QA-'.$i, 'search_text' => 'producto nuevo qa-'.$i, 'status' => 'published', 'private_price_crc' => 99999];
        }foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('products')->insert($chunk);
        }$start = microtime(true);
        $response = $this->get('/api/catalog/search?page=2')->assertOk()->assertJsonPath('total', 1000);
        $this->assertCount(12, $response->json('data'));
        $this->assertLessThan(5,microtime(true) - $start);
        $response->assertDontSee('private_price_crc')->assertDontSee('99999');
    }
}
