<?php

namespace Tests\Feature;

use App\Domain\Auth\Audit;
use App\Domain\Auth\Mfa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use OTPHP\TOTP;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    private function user(string $role = 'superadmin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user->refresh();
    }

    private function loggedAs(User $user, bool $mfa = true): self
    {
        if ($mfa && ! $user->mfa_confirmed_at) {
            $user->mfa_confirmed_at = now();
            $user->save();
        }
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version, '_token' => 'test-csrf', 'mfa_user_id' => $mfa ? $user->id : null]);

        return $this;
    }

    public function test_installation_has_zero_commercial_records_and_no_default_user(): void
    {
        foreach (['categories', 'brands', 'products', 'product_media', 'preorders', 'preorder_items', 'preorder_events', 'contact_submissions', 'posts', 'post_categories', 'users', 'import_batches'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseCount('pages', 5);
        $this->assertSame(5, DB::table('pages')->where('status', 'draft')->whereNull('published_revision_id')->count());
        $this->artisan('jmac:verify-empty')->assertExitCode(0);
    }

    public function test_seed_is_repeatable_and_does_not_overwrite_owner_data(): void
    {
        DB::table('pages')->where('slug', 'nosotros')->update(['title' => 'Texto del dueño']);
        $this->seed();
        $this->assertDatabaseCount('pages', 5);
        $this->assertDatabaseCount('page_revisions', 5);
        $this->assertDatabaseHas('pages', ['slug' => 'nosotros', 'title' => 'Texto del dueño']);
        foreach (['categories', 'brands', 'products'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_admin_redirects_unauthenticated_visitors(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/media')->assertRedirect('/admin/login');
    }

    public function test_valid_login_rotates_session_and_requires_mfa_enrollment(): void
    {
        $u = $this->user();
        $old = $this->app['session']->getId();
        $this->withSession(['_token' => 'test-csrf'])->post('/admin/login', ['_token' => 'test-csrf', 'email' => $u->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($u);
        $this->assertNotSame($old, $this->app['session']->getId());
        $this->get('/admin')->assertRedirect('/admin/mfa/enroll');
    }

    public function test_enrollment_persists_encrypted_secret_and_hashed_recovery_codes(): void
    {
        $u = $this->user();
        $this->loggedAs($u, false)->get('/admin/mfa/enroll')->assertOk();
        $secret = $this->app['session']->get('mfa_pending_secret');
        $code = TOTP::createFromSecret($secret)->now();
        $this->post('/admin/mfa/enroll', ['_token' => 'test-csrf', 'code' => $code])->assertOk()->assertSee('Segundo factor activado');
        $u->refresh();
        $this->assertNotNull($u->mfa_confirmed_at);
        $this->assertCount(8, $u->mfa_recovery_codes);
        $this->assertNotSame($secret, DB::table('users')->where('id', $u->id)->value('mfa_secret'));
        $this->assertFalse(app(Mfa::class)->verify($u, $code));
        $this->get('/admin')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.enrolled', 'resource_id' => $u->id]);
    }

    public function test_invalid_mfa_does_not_enroll_or_grant_access(): void
    {
        $u = $this->user();
        $this->loggedAs($u, false)->get('/admin/mfa/enroll');
        $this->post('/admin/mfa/enroll', ['_token' => 'test-csrf', 'code' => 'abcdef'])->assertSessionHasErrors('code');
        $this->assertNull($u->refresh()->mfa_confirmed_at);
        $this->get('/admin')->assertRedirect('/admin/mfa/enroll');
    }

    public function test_confirmed_admin_requires_mfa_after_password_login(): void
    {
        $u = $this->user();
        $u->mfa_secret = TOTP::generate()->getSecret();
        $u->mfa_confirmed_at = now();
        $u->save();
        $this->loggedAs($u, false)->get('/admin')->assertRedirect('/admin/mfa/challenge');
        $this->post('/admin/mfa/challenge', ['_token' => 'test-csrf', 'code' => TOTP::createFromSecret($u->mfa_secret)->now()])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }

    public function test_totp_and_recovery_codes_cannot_be_replayed(): void
    {
        $u = $this->user();
        $u->mfa_secret = TOTP::generate()->getSecret();
        $u->mfa_confirmed_at = now();
        $u->mfa_recovery_codes = [Hash::make('private-recovery-code')];
        $u->save();
        $mfa = app(Mfa::class);
        $code = TOTP::createFromSecret($u->mfa_secret)->now();
        $this->assertTrue($mfa->verify($u, $code));
        $this->assertFalse($mfa->verify($u, $code));
        $this->assertTrue($mfa->recover($u, 'private-recovery-code'));
        $this->assertFalse($mfa->recover($u, 'private-recovery-code'));
    }

    public function test_sales_and_read_only_roles_cannot_access_pages_media_or_audit(): void
    {
        foreach (['ventas', 'consulta'] as $role) {
            $u = $this->user($role);
            $this->loggedAs($u)->get('/admin/pages')->assertForbidden();
            $this->get('/admin/media')->assertForbidden();
            $this->get('/admin/audit')->assertForbidden();
        }
        $this->assertFalse($u->hasPermission('theme.update'));
        $this->assertFalse($u->hasPermission('products.update'));
    }

    public function test_editor_does_not_receive_personal_data_or_sensitive_permissions(): void
    {
        $u = $this->user('editor_web');
        $this->loggedAs($u)->get('/admin/pages')->assertOk();
        foreach (['preorders.view', 'contacts.view', 'users.manage', 'settings.sensitive', 'backups.restore', 'media.approve'] as $permission) {
            $this->assertFalse($u->hasPermission($permission));
        }
        $this->get('/admin/audit')->assertForbidden();
    }

    public function test_role_changes_and_deactivated_accounts_apply_to_current_session(): void
    {
        $u = $this->user('editor_web');
        $this->loggedAs($u)->get('/admin/pages')->assertOk();
        $u->roles()->detach();
        $this->get('/admin/pages')->assertForbidden();
        $u->status = 'disabled';
        $u->save();
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_auth_version_invalidates_existing_session(): void
    {
        $u = $this->user('ventas');
        $this->loggedAs($u)->get('/admin')->assertOk();
        $u->auth_version++;
        $u->save();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_csrf_token_is_required_even_for_same_origin_header(): void
    {
        $u = $this->user('editor_web');
        $this->loggedAs($u)->withHeader('Sec-Fetch-Site', 'same-origin')->post('/admin/media/upload', [])->assertStatus(419);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_login_is_rate_limited_and_errors_do_not_enumerate_users(): void
    {
        $u = $this->user();
        $this->withSession(['_token' => 'test-csrf']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['_token' => 'test-csrf', 'email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors(['email' => 'Credenciales incorrectas.']);
        }
        $this->post('/admin/login', ['_token' => 'test-csrf', 'email' => $u->email, 'password' => 'wrong'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_images_are_reencoded_and_stored_privately_as_candidates(): void
    {
        Storage::fake('local');
        $u = $this->user('editor_web');
        $this->loggedAs($u)->post('/admin/media/upload', ['_token' => 'test-csrf', 'file' => UploadedFile::fake()->image('photo.jpg', 120, 80), 'alt' => 'Foto candidata'])->assertRedirect('/admin/media');
        $media = DB::table('media')->first();
        $this->assertSame('candidate', $media->status);
        $this->assertSame('image/webp', $media->mime);
        $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer(Storage::disk('local')->get($media->path)));
        $this->get('/admin/media/'.$media->id.'/preview')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_executable_zip_svg_and_disguised_php_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $u = $this->user('editor_web');
        $this->loggedAs($u);
        foreach (['malware.jpg', 'malware.php', 'payload.svg', 'backup.zip'] as $name) {
            $this->post('/admin/media/upload', ['_token' => 'test-csrf', 'file' => UploadedFile::fake()->createWithContent($name, '<?php echo "bad"; ?>'), 'alt' => 'Prueba'])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_xss_is_escaped_in_admin_drafts(): void
    {
        $u = $this->user('editor_web');
        DB::table('pages')->where('slug', 'nosotros')->update(['title' => '<script>alert(1)</script>']);
        $this->loggedAs($u)->get('/admin/pages')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_security_headers_and_private_admin_cache(): void
    {
        $this->get('/admin/login')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString("object-src 'none'", $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_password_reset_token_is_single_use_and_revokes_sessions(): void
    {
        $u = $this->user('ventas');
        $token = Password::createToken($u);
        $this->withSession(['_token' => 'test-csrf']);
        $payload = ['_token' => 'test-csrf', 'token' => $token, 'email' => $u->email, 'password' => 'New-Strong-Password123!', 'password_confirmation' => 'New-Strong-Password123!'];
        $this->post('/admin/reset-password', $payload)->assertRedirect('/admin/login');
        $this->assertSame(2, $u->refresh()->auth_version);
        $this->assertTrue(Hash::check($payload['password'], $u->password));
        $this->post('/admin/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_audit_has_only_safe_metadata(): void
    {
        $u = $this->user('editor_web');
        $this->loggedAs($u);
        Audit::record('test', 'users', $u->id, ['password' => 'secret-value', 'phone' => 'private-phone', 'status' => 'active']);
        $changes = DB::table('audit_logs')->orderByDesc('id')->value('changes');
        $this->assertStringNotContainsString('secret-value', $changes);
        $this->assertStringNotContainsString('private-phone', $changes);
    }
}
