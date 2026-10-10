<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MfaConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    private function user(string $role = 'superadmin', bool $enrolled = false): User
    {
        $user = User::factory()->create($enrolled ? [
            'mfa_secret' => TOTP::generate()->getSecret(),
            'mfa_confirmed_at' => now(),
        ] : []);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user->refresh();
    }

    private function passwordLogin(User $user): void
    {
        $this->withSession(['_token' => 'test-csrf'])->post('/admin/login', [
            '_token' => 'test-csrf',
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_mfa_is_required_by_default(): void
    {
        $this->assertTrue(config('auth.mfa_required'));
        $this->passwordLogin($this->user());
        $this->get('/admin')->assertRedirect('/admin/mfa/enroll');
    }

    public function test_explicitly_disabled_mfa_allows_password_login_without_enrollment(): void
    {
        config(['auth.mfa_required' => false]);
        $user = $this->user();
        $this->passwordLogin($user);
        $this->get('/admin')->assertOk();
        $this->get('/admin/pages')->assertOk();
        $this->assertNull($user->refresh()->mfa_confirmed_at);
        $this->assertNull($user->mfa_secret);
        $this->assertNull(session('mfa_user_id'));
    }

    public function test_suspension_preserves_previously_enrolled_second_factor(): void
    {
        config(['auth.mfa_required' => false]);
        $user = $this->user(enrolled: true);
        $secret = $user->mfa_secret;
        $this->passwordLogin($user);
        $this->get('/admin')->assertOk();
        $this->assertNull(session('mfa_user_id'));
        $this->assertSame($secret, $user->refresh()->mfa_secret);
        $this->assertNotNull($user->mfa_confirmed_at);
    }

    public function test_suspension_still_requires_password_authentication(): void
    {
        config(['auth.mfa_required' => false]);
        foreach (['/admin', '/admin/pages', '/admin/users', '/admin/media'] as $path) {
            $this->get($path)->assertRedirect('/admin/login');
        }
        $user = $this->user();
        $this->withSession(['_token' => 'test-csrf'])->post('/admin/login', [
            '_token' => 'test-csrf', 'email' => $user->email, 'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspension_preserves_role_permissions_and_csrf(): void
    {
        config(['auth.mfa_required' => false]);
        $this->passwordLogin($this->user('ventas'));
        $this->get('/admin')->assertOk();
        foreach (['/admin/pages', '/admin/users', '/admin/media', '/admin/audit'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/admin/logout')->assertStatus(419);
    }

    public function test_suspension_does_not_override_session_revocation(): void
    {
        config(['auth.mfa_required' => false]);
        $user = $this->user();
        $this->passwordLogin($user);
        $this->get('/admin')->assertOk();
        $user->increment('auth_version');
        // A separate PHP request reloads the changed account from the database.
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_suspension_does_not_override_disabled_accounts(): void
    {
        config(['auth.mfa_required' => false]);
        $user = $this->user();
        $this->passwordLogin($user);
        $this->get('/admin')->assertOk();
        $user->status = 'disabled';
        $user->save();
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_reenabling_requires_enrollment_in_existing_session(): void
    {
        config(['auth.mfa_required' => false]);
        $this->passwordLogin($this->user());
        $this->get('/admin')->assertOk();
        config(['auth.mfa_required' => true]);
        $this->get('/admin')->assertRedirect('/admin/mfa/enroll');
    }

    public function test_reenabling_requires_challenge_for_enrolled_existing_session(): void
    {
        config(['auth.mfa_required' => false]);
        $this->passwordLogin($this->user(enrolled: true));
        $this->get('/admin')->assertOk();
        config(['auth.mfa_required' => true]);
        $this->get('/admin')->assertRedirect('/admin/mfa/challenge');
    }

    #[DataProvider('unsupportedModes')]
    public function test_ambiguous_configuration_still_requires_mfa(mixed $mode): void
    {
        config(['auth.mfa_required' => $mode]);
        $this->passwordLogin($this->user());
        $this->get('/admin')->assertRedirect('/admin/mfa/enroll');
    }

    public static function unsupportedModes(): array
    {
        return [
            'missing value' => [null],
            'empty string' => [''],
            'numeric zero' => [0],
            'string instead of boolean' => ['false'],
        ];
    }
}
