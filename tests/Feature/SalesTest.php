<?php

namespace Tests\Feature;

use App\Domain\Notifications\Outbox;
use App\Domain\Sales\Privacy;
use App\Domain\Sales\Requests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    private int $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->withSession(['_token' => 'test-csrf'])->withCredentials();
        $this->product = DB::table('products')->insertGetId(['name' => 'Producto nuevo QA', 'slug' => 'producto-qa', 'sku' => 'NUEVO-QA-01', 'status' => 'published', 'unit' => 'unidad', 'search_text' => 'producto nuevo qa']);
        $this->legal();
    }

    private function legal(): void
    {
        DB::table('site_settings')->updateOrInsert(['key' => 'privacy.document'], ['json_value' => json_encode(['approved' => true, 'version' => 'qa-v1', 'body' => 'Texto legal de prueba únicamente para verificar el comportamiento del formulario.']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('site_settings')->updateOrInsert(['key' => 'business'], ['json_value' => json_encode(['notify_email' => 'ventas@example.test']), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function preorder(array $override = []): array
    {
        return array_replace(['_token' => 'test-csrf', 'idempotency_key' => Str::uuid()->toString(), 'customer' => ['name' => 'Cliente prueba', 'phone' => '+50688888888', 'email' => 'cliente@example.test', 'company' => 'Empresa QA'], 'items' => [['product_id' => $this->product, 'quantity' => 2]], 'notes' => 'Solicitud nueva de prueba', 'privacy_accepted' => true, 'consent_version' => 'qa-v1', 'email_opt_in' => false, 'website' => ''], $override);
    }

    private function contact(array $override = []): array
    {
        return array_replace(['_token' => 'test-csrf', 'idempotency_key' => Str::uuid()->toString(), 'name' => 'Persona QA', 'phone' => '+50688888888', 'email' => 'persona@example.test', 'company' => 'Empresa QA', 'message' => 'Mensaje nuevo de prueba', 'privacy_accepted' => true, 'consent_version' => 'qa-v1', 'email_opt_in' => false, 'website' => ''], $override);
    }

    public function test_preorder_commits_once_with_snapshots_encrypted_pii_and_matching_retry_reference(): void
    {
        $payload = $this->preorder();
        $first = $this->postJson('/api/preorders', $payload)->assertCreated()->assertJsonPath('status', 'registrada');
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $second = $this->postJson('/api/preorders', $payload)->assertOk();
        $this->assertSame($first->json('reference'), $second->json('reference'));
        $this->assertDatabaseCount('preorders', 1);
        $this->assertDatabaseCount('preorder_items', 1);
        $this->assertDatabaseCount('notifications_outbox', 1);
        $row = DB::table('preorders')->first();
        $this->assertNotSame('Cliente prueba', $row->customer_name);
        $this->assertSame('Cliente prueba', Privacy::decrypt($row->customer_name));
        $this->assertDatabaseHas('preorder_items', ['sku_snapshot' => 'NUEVO-QA-01', 'quantity' => 2]);
        DB::table('products')->where('id', $this->product)->update(['name' => 'Nombre editado', 'sku' => 'CAMBIADO', 'status' => 'archived']);
        $this->postJson('/api/preorders', $payload)->assertOk()->assertJsonPath('reference', $first->json('reference'));
        $this->assertDatabaseHas('preorder_items', ['name_snapshot' => 'Producto nuevo QA', 'sku_snapshot' => 'NUEVO-QA-01']);
    }

    public function test_changed_payload_or_another_session_cannot_reuse_idempotency_key(): void
    {
        $payload = $this->preorder();
        $service = app(Requests::class);
        $service->preorder($payload, 'session-a');
        try {
            $service->preorder($payload, 'session-b');
            $this->fail('Session mismatch must fail');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }$payload['customer']['name'] = 'Otro cliente';
        try {
            $service->preorder($payload, 'session-a');
            $this->fail('Payload mismatch must fail');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }$this->assertDatabaseCount('preorders', 1);
    }

    public function test_invalid_unpublished_duplicate_items_and_missing_consent_are_rejected(): void
    {
        $payload = $this->preorder();
        $payload['customer']['phone'] = '';
        $this->postJson('/api/preorders', $payload)->assertUnprocessable();
        $this->postJson('/api/preorders', $this->preorder(['privacy_accepted' => false]))->assertUnprocessable();
        $payload = $this->preorder();
        $payload['items'][] = $payload['items'][0];
        $this->postJson('/api/preorders', $payload)->assertUnprocessable();
        DB::table('products')->where('id', $this->product)->update(['status' => 'draft']);
        $this->postJson('/api/preorders', $this->preorder())->assertUnprocessable();
        $this->assertDatabaseCount('preorders', 0);
        $this->assertDatabaseCount('preorder_items', 0);
    }

    public function test_unapproved_legal_honeypot_and_rate_limit_prevent_capture(): void
    {
        $this->postJson('/api/contacts', $this->contact(['website' => 'spam']))->assertUnprocessable();
        DB::table('site_settings')->where('key', 'privacy.document')->delete();
        $this->postJson('/api/contacts', $this->contact())->assertUnprocessable();
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/contacts', $this->contact());
        }$response->assertStatus(429);
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_smtp_connection_failure_does_not_lose_preorder_and_retry_is_visible(): void
    {
        $payload = $this->preorder();
        $this->postJson('/api/preorders', $payload)->assertCreated();
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 9, 'timeout' => 1, 'scheme' => 'smtp'], 'mail.from.address' => 'qa@example.test']);
        $summary = app(Outbox::class)->process(2);
        $this->assertSame(1, $summary['failed']);
        $this->assertDatabaseHas('notifications_outbox', ['status' => 'failed', 'error_code' => 'SMTP_DELIVERY_FAILED', 'retries' => 1]);
        $this->assertDatabaseCount('preorders', 1);
        $this->postJson('/api/preorders', $payload)->assertOk();
    }

    public function test_missing_mail_config_keeps_pending_without_claiming_delivery(): void
    {
        $this->postJson('/api/preorders', $this->preorder())->assertCreated();
        config(['mail.default' => 'log']);
        $summary = app(Outbox::class)->process(1);
        $this->assertSame(1, $summary['configuration_pending']);
        $this->assertDatabaseHas('notifications_outbox', ['status' => 'pending', 'error_code' => 'CONFIGURATION_REQUIRED', 'sent_at' => null]);
    }

    public function test_contact_is_persisted_idempotently_and_pii_is_encrypted(): void
    {
        $payload = $this->contact();
        $first = $this->postJson('/api/contacts', $payload)->assertCreated();
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $this->postJson('/api/contacts', $payload)->assertOk()->assertJsonPath('reference', $first->json('reference'));
        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertDatabaseCount('contact_events', 1);
        $this->assertDatabaseCount('notifications_outbox', 1);
        $row = DB::table('contact_submissions')->first();
        $this->assertSame('Mensaje nuevo de prueba', Privacy::decrypt($row->message));
        $this->assertStringNotContainsString('Mensaje nuevo de prueba', $row->message);
    }

    public function test_sales_transitions_history_assignment_and_editor_denial(): void
    {
        $this->postJson('/api/preorders', $this->preorder())->assertCreated();
        $id = DB::table('preorders')->value('id');
        $user = $this->authenticateRole('ventas');
        $this->get('/admin/preorders/'.$id)->assertOk()->assertSee('Cliente prueba');
        $this->postJson('/admin/preorders/'.$id, ['_token' => 'test-csrf', 'status' => 'cerrada'])->assertUnprocessable();
        $this->post('/admin/preorders/'.$id, ['_token' => 'test-csrf', 'status' => 'en_revision', 'assigned_to' => $user->id, 'note' => 'Revisión de prueba'])->assertRedirect();
        $this->assertDatabaseHas('preorders', ['id' => $id, 'status' => 'en_revision', 'assigned_to' => $user->id]);
        $event = DB::table('preorder_events')->orderByDesc('id')->first();
        $this->assertSame('Revisión de prueba', Privacy::decrypt($event->note));
        $this->authenticateRole('editor_web');
        $this->get('/admin/preorders')->assertForbidden();
        $this->get('/admin/contacts')->assertForbidden();
    }

    public function test_csv_export_requires_permission_and_neutralizes_formulas(): void
    {
        $payload = $this->preorder();
        $payload['customer']['name'] = '=INJECTION()';
        $this->postJson('/api/preorders', $payload)->assertCreated();
        $this->authenticateRole('ventas');
        $response = $this->get('/admin/preorders/export')->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString("'=INJECTION()", $content);
        $this->authenticateRole('editor_web');
        $this->get('/admin/preorders/export')->assertForbidden();
    }

    public function test_anonymization_removes_personal_data_and_cancels_pending_messages(): void
    {
        $this->postJson('/api/contacts', $this->contact())->assertCreated();
        $id = DB::table('contact_submissions')->value('id');
        $this->authenticateRole();
        $this->post('/admin/contacts/'.$id.'/anonymize', ['_token' => 'test-csrf', 'confirm' => 1])->assertRedirect();
        $this->assertDatabaseHas('contact_submissions', ['id' => $id, 'name' => null, 'phone' => null, 'email' => null, 'message' => null]);
        $this->assertDatabaseHas('notifications_outbox', ['status' => 'cancelled', 'recipient' => null]);
    }
}
