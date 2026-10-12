<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require '/workspace/jmaccr/vendor/autoload.php';
$app = require '/workspace/jmaccr/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! str_ends_with(config('database.connections.mysql.database'), '_browser')) {
    throw new LogicException('Fixture solo en BD descartable *_browser');
}
$u = new User;
$u->name = 'Administrador de prueba';
$u->email = 'qa@example.test';
$password = bin2hex(random_bytes(20));
$u->password = Hash::make($password);
$u->save();
$u->roles()->attach(Role::where('name', 'superadmin')->firstOrFail());
file_put_contents('/tmp/jmac-browser-login.json', json_encode(['email' => $u->email, 'password' => $password]));
chmod('/tmp/jmac-browser-login.json', 0600);
