<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StagingInstallerTest extends TestCase
{
    use RefreshDatabase;

    private ?string $ownerFile = null;

    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        $this->seed();
        $this->app->detectEnvironment(fn () => 'staging');
        config(['app.debug' => false, 'app.url' => 'https://staging.example.test']);
    }

    protected function tearDown(): void
    {
        if ($this->ownerFile && is_file($this->ownerFile)) {
            unlink($this->ownerFile);
        }
        DB::setDefaultConnection($this->originalConnection);
        parent::tearDown();
    }

    private function owner(array $overrides = [], int $mode = 0600): void
    {
        $path = storage_path('app/private/owner-setup.json');
        if (file_exists($path)) {
            throw new \LogicException('No sobrescribir un archivo privado existente.');
        }
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $this->ownerFile = $path;
        file_put_contents($path, json_encode(array_replace([
            'name' => 'Dueño de prueba', 'email' => 'owner@example.test', 'password' => 'Test-Password-Only123!',
        ], $overrides)));
        chmod($path, $mode);
    }

    public function test_install_creates_only_an_explicit_admin_then_removes_secret_and_is_repeatable(): void
    {
        $this->owner();
        $key = config('app.key');
        $this->artisan('jmac:install-staging')->assertSuccessful();
        $this->assertFileDoesNotExist($this->ownerFile);
        $this->assertDatabaseCount('users', 1);
        $user = User::firstOrFail();
        $this->assertTrue($user->requiresMfa());
        $this->assertNull($user->mfa_confirmed_at);
        $this->assertTrue(Hash::check('Test-Password-Only123!', $user->password));
        $this->artisan('jmac:verify-empty')->assertSuccessful();
        $this->artisan('jmac:install-staging')->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($key, config('app.key'));
    }

    public function test_installer_rejects_production_and_does_not_create_users(): void
    {
        $this->owner();
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('jmac:install-staging')->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertFileExists($this->ownerFile);
    }

    public function test_weak_password_and_readable_secret_file_are_rejected(): void
    {
        $this->owner(['password' => 'weak']);
        $this->artisan('jmac:install-staging')->assertFailed();
        chmod($this->ownerFile, 0644);
        clearstatcache();
        $this->artisan('jmac:install-staging')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_installer_preserves_business_data_and_refuses_reinstallation(): void
    {
        $this->owner();
        DB::table('brands')->insert(['name' => 'Marca de prueba nueva', 'slug' => 'test-nueva']);
        $this->artisan('jmac:install-staging')->assertFailed();
        $this->assertDatabaseCount('brands', 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertFileExists($this->ownerFile);
    }

    public function test_installer_bootstraps_a_genuinely_empty_database(): void
    {
        $this->owner();
        config(['database.connections.installer_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('installer_test');
        $this->assertSame([], Schema::getTableListing());
        $this->artisan('jmac:install-staging')->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pages', 5);
        $this->artisan('jmac:verify-empty')->assertSuccessful();
        $this->assertFileDoesNotExist($this->ownerFile);
    }
}
