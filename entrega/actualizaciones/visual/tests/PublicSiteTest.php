<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_routes_are_real_and_drafts_are_never_published(): void
    {
        $this->seed();
        foreach (['/', '/nosotros/', '/contacto/', '/novedades/', '/catalogo/'] as $route) {
            $this->get($route)->assertOk()->assertSee('J-MAC')->assertSee('/brand/jmac-logo.png')->assertSee('Creado por fisitalcr')->assertSee('id="cart-drawer"', false)->assertSee('Catálogo')->assertDontSee('UN PRODUCTO CON 20 AÑOS');
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('brands', 0);
        $this->assertSame(0, DB::table('pages')->whereNotNull('published_revision_id')->count());
    }

    public function test_empty_states_and_hidden_contact_information(): void
    {
        $this->seed();
        $this->get('/catalogo/')->assertOk()->assertSee('Estamos preparando')->assertDontSee('₡');
        $this->get('/novedades/')->assertSee('Aún no hay publicaciones');
        $this->get('/contacto/')->assertDontSee('wa.me');
        $this->get('/media/999')->assertNotFound();
    }
}
