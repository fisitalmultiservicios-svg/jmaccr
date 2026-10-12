<?php

use App\Domain\Auth\Audit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('jmac:admin {email} {--name=Administrador JMAC}', function () {
    $email = strtolower($this->argument('email'));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
        $this->error('Correo inválido o cuenta existente.');

        return 1;
    }
    $role = Role::where('name', 'superadmin')->first();
    if (! $role) {
        $this->error('Ejecuta primero migrate y db:seed.');

        return 1;
    }
    $password = $this->secret('Contraseña nueva (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos)');
    $confirm = $this->secret('Repite la contraseña');
    $v = Validator::make(['password' => $password, 'password_confirmation' => $confirm], ['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
    if ($v->fails()) {
        $this->error('Contraseña débil o confirmación incorrecta. No se creó la cuenta.');

        return 1;
    }
    DB::transaction(function () use ($email, $password, $role) {
        $user = new User;
        $user->name = $this->option('name');
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->save();
        $user->roles()->attach($role);
        Audit::record('users.provisioned', 'users', $user->id, ['role' => 'superadmin']);
    });
    $this->info('Cuenta creada. El primer acceso exige activar MFA.');

    return 0;
})->purpose('Crear explícitamente un administrador nuevo sin contraseñas predeterminadas');

Artisan::command('jmac:verify-empty', function () {
    $failed = false;
    foreach (['categories', 'brands', 'products', 'product_media', 'preorders', 'preorder_items', 'contact_submissions', 'posts', 'post_categories'] as $table) {
        $count = DB::table($table)->count();
        $this->line("$table: $count");
        $failed = $failed || $count !== 0;
    }
    $this->line('pages published: '.DB::table('pages')->whereNotNull('published_revision_id')->count());

    return $failed ? 1 : 0;
})->purpose('Verificar datos comerciales vacíos en una instalación nueva, sin borrar datos');

Artisan::command('jmac:diagnostics', function () {
    $ok = true;
    $this->line('PHP '.PHP_VERSION);
    foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'curl', 'dom', 'zip', 'gd', 'intl'] as $ext) {
        $loaded = extension_loaded($ext);
        $this->line("$ext: ".($loaded ? 'OK' : 'FALTA'));
        $ok = $ok && $loaded;
    }
    DB::connection()->getPdo();
    $this->line('DB: conectada');
    $this->line('storage writable: '.(is_writable(storage_path()) ? 'sí' : 'no'));

    return $ok && is_writable(storage_path()) ? 0 : 1;
})->purpose('Diagnóstico privado de extensiones y base de datos sin revelar credenciales');
