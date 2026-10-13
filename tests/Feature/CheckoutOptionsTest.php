<?php

namespace Tests\Feature;

use App\Domain\Sales\Privacy;
use App\Domain\Sales\Requests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckoutOptionsTest extends TestCase
{
    use RefreshDatabase;

    private int $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->withSession(['_token' => 'test-csrf'])->withCredentials();
        $this->product = DB::table('products')->insertGetId(['name' => 'Producto cantidades QA', 'slug' => 'cantidad-qa', 'sku' => 'CANT-QA-01', 'status' => 'published', 'search_text' => 'producto cantidades qa']);
        DB::table('site_settings')->where('key', 'privacy.document')->delete();
        DB::table('site_settings')->updateOrInsert(['key' => 'catalog.ui'], ['json_value' => json_encode(['max_quantity' => 5]), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function preorder(): array
    {
        return ['_token' => 'test-csrf', 'idempotency_key' => (string) Str::uuid(), 'customer' => ['name' => 'Persona QA', 'phone' => '+50688888888'], 'items' => [['product_id' => $this->product, 'quantity' => 3]]];
    }

    private function contact(): array
    {
        return ['_token' => 'test-csrf', 'idempotency_key' => (string) Str::uuid(), 'name' => 'Consulta QA', 'phone' => '+50688888888', 'message' => 'Consulta nueva de prueba'];
    }

    public function test_forms_are_available_without_legal_approval_and_cards_and_details_offer_configured_quantity(): void
    {
        $this->get('/contacto/')->assertOk()->assertSee('data-request-form="contact"', false)->assertDontSee('name="privacy_accepted"', false)->assertDontSee('habilitar');
        $this->get('/catalogo/')->assertOk()->assertSee('data-product-quantity', false)->assertSee('data-cart-max-quantity="5"', false)->assertSee('data-request-form="preorder"', false)->assertDontSee('name="privacy_accepted"', false)->assertDontSee('requiere aprobación');
        $this->get('/catalogo/cantidad-qa')->assertOk()->assertSee('Cantidad de Producto cantidades QA')->assertSee('max="5"', false);
    }

    public function test_preorder_and_contact_do_not_require_acceptance_or_policy_and_record_no_false_consent(): void
    {
        $payload = $this->preorder();
        $first = $this->postJson('/api/preorders', $payload)->assertCreated();
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $this->postJson('/api/preorders', $payload)->assertOk()->assertJsonPath('reference', $first->json('reference'));
        $this->assertDatabaseHas('preorders', ['consent_version' => Privacy::ACCEPTANCE_NOT_REQUIRED, 'email_opt_in' => false]);
        $this->assertDatabaseHas('preorder_items', ['quantity' => 3]);
        $payload = $this->contact() + ['privacy_accepted' => false, 'consent_version' => 'not-approved'];
        $first = $this->postJson('/api/contacts', $payload)->assertCreated();
        $this->postJson('/api/contacts', $payload)->assertOk()->assertJsonPath('reference', $first->json('reference'));
        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertDatabaseHas('contact_submissions', ['consent_version' => Privacy::ACCEPTANCE_NOT_REQUIRED, 'email_opt_in' => false]);
        $this->assertSame('Consulta QA', Privacy::decrypt(DB::table('contact_submissions')->first()->name));
    }

    public function test_server_still_rejects_quantities_outside_configured_limits_and_missing_csrf(): void
    {
        foreach ([0, 6, 1.5] as $quantity) {
            $payload = $this->preorder();
            $payload['items'][0]['quantity'] = $quantity;
            $this->postJson('/api/preorders', $payload)->assertUnprocessable();
        }
        $payload = $this->preorder();
        unset($payload['_token']);
        $this->postJson('/api/preorders', $payload)->assertStatus(419);
        $this->assertDatabaseCount('preorders', 0);
        $this->assertDatabaseCount('preorder_items', 0);
    }

    public function test_legacy_retries_keep_references_and_historical_consent_without_accepting_changed_data(): void
    {
        $service = app(Requests::class);
        foreach (['preorder', 'contact'] as $kind) {
            $payload = $kind === 'preorder' ? $this->preorder() : $this->contact();
            $first = $service->$kind($payload, 'same-qa-session');
            $table = $kind === 'preorder' ? 'preorders' : 'contact_submissions';
            $oldCustomer = ['name' => 'Persona QA', 'phone' => '50688888888', 'email' => '', 'company' => ''];
            $oldPayload = $kind === 'preorder'
                ? ['customer' => $oldCustomer, 'items' => $payload['items'], 'notes' => '', 'consent_version' => 'legacy-approved-qa', 'email_opt_in' => false]
                : ['name' => $payload['name'], 'phone' => '50688888888', 'email' => '', 'company' => '', 'message' => $payload['message'], 'consent_version' => 'legacy-approved-qa', 'email_opt_in' => false];
            $oldHash = Privacy::hash(json_encode($oldPayload));
            // Represent a persisted pre-update record with its original hash
            // and consent. The fallback must use its encrypted snapshots.
            DB::table($table)->where('idempotency_key', $payload['idempotency_key'])->update(['consent_version' => 'legacy-approved-qa', 'request_hash' => $oldHash]);
            $retry = $service->$kind($payload, 'same-qa-session');
            $this->assertSame($first['code'], $retry['code']);
            $this->assertFalse($retry['created']);
            $this->assertDatabaseCount($table, 1);
            $this->assertDatabaseHas($table, ['consent_version' => 'legacy-approved-qa', 'request_hash' => $oldHash]);
            if ($kind === 'preorder') {
                $payload['items'][0]['quantity']++;
            } else {
                $payload['message'] = 'Mensaje diferente QA';
            }
            try {
                $service->$kind($payload, 'same-qa-session');
                $this->fail('Changed payload must be rejected');
            } catch (HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
    }
}
