<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function authenticateRole(string $role = 'superadmin'): User
    {
        $user = User::factory()->create(['mfa_confirmed_at' => now()]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        $user->refresh();
        $this->actingAs($user)->withSession(['_token' => 'test-csrf', 'auth_version' => $user->auth_version, 'mfa_user_id' => $user->id]);

        return $user;
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        $testStorage = '/tmp/jmac-tests-'.getmypid();
        foreach (['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
            if (! is_dir($testStorage.'/'.$directory)) {
                mkdir($testStorage.'/'.$directory, 0700, true);
            }
        }
        $app->useStoragePath($testStorage);
        config(['filesystems.disks.local.root' => storage_path('app/private'), 'view.compiled' => storage_path('framework/views'), 'logging.channels.single.path' => storage_path('logs/laravel.log')]);
        $connection = config('database.default');
        $database = config("database.connections.$connection.database");
        if ($connection !== 'sqlite' && ! str_ends_with((string) $database, '_test')) {
            throw new \LogicException('Tests permitidos solo en una base descartable *_test.');
        }
        if ($connection === 'sqlite' && $database !== ':memory:') {
            throw new \LogicException('SQLite de pruebas debe estar en memoria.');
        }

        return $app;
    }
}
