<?php

namespace App\Console\Commands;

use App\Domain\Auth\Audit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallStaging extends Command
{
    protected $signature = 'jmac:install-staging';

    protected $description = 'Instalar la etapa 1 en una base nueva de staging, mediante CLI o Cron privado';

    public function handle(): int
    {
        if (! app()->environment('staging') || config('app.debug') || ! str_starts_with(config('app.url'), 'https://')) {
            $this->error('Solo se permite staging HTTPS con APP_DEBUG=false.');

            return self::FAILURE;
        }
        if (PHP_VERSION_ID < 80300) {
            $this->error('Se requiere PHP 8.3 o posterior.');

            return self::FAILURE;
        }
        $directory = storage_path('app/private');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $lock = fopen($directory.'/installation.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->error('Otra instalación está en curso.');

            return self::FAILURE;
        }
        chmod($directory.'/installation.lock', 0600);
        try {
            return $this->install($directory);
        } catch (\Throwable $exception) {
            // Never print validation payloads, keys, passwords, SQL or stack traces.
            $this->error('Instalación detenida. Revisa permisos, configuración y conexión; no borres la base.');

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function install(string $directory): int
    {
        if (Schema::hasTable('site_settings') && DB::table('site_settings')->where('key', 'system.installation_completed')->exists()) {
            $this->info('La instalación ya se completó. No se modificaron la clave ni las cuentas. Retira este Cron.');

            return self::SUCCESS;
        }
        // A fresh database or an already migrated empty JMAC database is required.
        // Laravel 13 lists all visible MySQL schemas unless one is specified.
        // Other databases on a shared account must not affect this installation.
        $schema = DB::connection()->getDriverName() === 'mysql'
            ? DB::connection()->getDatabaseName() : null;
        $tables = Schema::getTableListing($schema);
        if ($tables !== [] && ! Schema::hasTable('migrations')) {
            $this->error('Selecciona una base NUEVA y vacía, exclusiva para JMAC.');

            return self::FAILURE;
        }
        foreach ($tables as $table) {
            if (preg_match('/(?:^|\.)wp[a-z0-9]*_/i', $table)) {
                $this->error('No se permite instalar sobre una base WordPress.');

                return self::FAILURE;
            }
        }
        foreach (['categories', 'brands', 'products', 'preorders', 'contact_submissions', 'posts', 'users'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                $this->error('La base contiene datos o cuentas. Usa una instalación nueva; no se borró ningún dato.');

                return self::FAILURE;
            }
        }
        $file = $directory.'/owner-setup.json';
        if (! is_file($file) || is_link($file) || filesize($file) > 8192 || (fileperms($file) & 0077) !== 0) {
            $this->error('Crea storage/app/private/owner-setup.json con permisos 0600.');

            return self::FAILURE;
        }
        $data = json_decode(file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            return self::FAILURE;
        }
        $validator = Validator::make($data, [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:254',
            'password' => ['required', 'string', 'max:255', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            $this->error('Revisa el nombre, correo y contraseña del archivo privado. No se creó ninguna cuenta.');

            return self::FAILURE;
        }
        if (! config('app.key') && Artisan::call('key:generate', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }
        if (Artisan::call('db:seed', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }
        if (Artisan::call('jmac:verify-empty') !== 0) {
            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $role = Role::where('name', 'superadmin')->firstOrFail();
            $user = new User;
            $user->name = $data['name'];
            $user->email = strtolower($data['email']);
            $user->password = Hash::make($data['password']);
            $user->save();
            $user->roles()->attach($role);
            Audit::record('users.provisioned', 'users', $user->id, ['role' => 'superadmin']);
            DB::table('site_settings')->insert([
                'key' => 'system.installation_completed', 'json_value' => json_encode(['completed_at' => now()->toIso8601String()]),
                'scope' => 'private', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        if (! unlink($file)) {
            $this->error('Cuenta creada. Elimina manualmente owner-setup.json; no repitas la creación de cuenta.');

            return self::FAILURE;
        }
        $this->info('Instalación terminada. Archivo privado eliminado, catálogo vacío y MFA obligatorio. Retira este Cron.');

        return self::SUCCESS;
    }
}
