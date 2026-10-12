<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users', ['users' => User::with('roles')->orderBy('id')->paginate(20), 'roles' => Role::with('permissions')->orderBy('name')->get(), 'permissions' => Permission::orderBy('name')->get()]);
    }

    private function ownerGuard(): void
    {
        abort_unless(User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->exists(), 422, 'Debe conservarse al menos un superadmin activo.');
    }

    public function create(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:180', 'email' => 'required|email|max:254|unique:users', 'roles' => 'required|array|min:1', 'roles.*' => 'integer|distinct|exists:roles,id']);
        $u = DB::transaction(function () use ($d) {
            $u = new User;
            $u->name = $d['name'];
            $u->email = strtolower($d['email']);
            $u->password = Hash::make(Str::random(64));
            $u->save();
            $u->roles()->sync($d['roles']);
            Audit::record('users.created', 'users', $u->id);

            return $u;
        });
        Password::sendResetLink(['email' => $u->email, 'status' => 'active']);

        return back()->with('status', 'Usuario creado. Invitación en outbox; verifica SMTP y Cron.');
    }

    public function save(Request $request, int $id)
    {
        $d = $request->validate(['name' => 'required|string|max:180', 'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($id)], 'status' => 'required|in:active,disabled', 'roles' => 'required|array|min:1', 'roles.*' => 'integer|distinct|exists:roles,id']);
        DB::transaction(function () use ($d, $id) {
            User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->lockForUpdate()->get();
            $u = User::lockForUpdate()->findOrFail($id);
            $u->name = $d['name'];
            $u->email = strtolower($d['email']);
            $u->status = $d['status'];
            $u->auth_version++;
            $u->remember_token = Str::random(60);
            $u->save();
            $u->roles()->sync($d['roles']);
            $this->ownerGuard();
            DB::table('sessions')->where('user_id', $id)->delete();
            Audit::record('users.updated', 'users', $id, ['status' => $d['status']]);
        }, 3);

        return back()->with('status', 'Usuario actualizado; sesiones revocadas.');
    }

    public function invite(int $id)
    {
        $u = User::where('status', 'active')->findOrFail($id);
        Password::sendResetLink(['email' => $u->email, 'status' => 'active']);
        Audit::record('users.invited', 'users', $id);

        return back()->with('status', 'Invitación/reestablecimiento solicitado. Consulta outbox.');
    }

    public function resetMfa(Request $request, int $id)
    {
        $request->validate(['confirm' => 'accepted', 'current_password' => 'required|string']);
        abort_unless(Hash::check($request->current_password, $request->user()->password), 422, 'Contraseña actual incorrecta.');
        DB::transaction(function () use ($id) {
            $u = User::lockForUpdate()->findOrFail($id);
            $u->mfa_secret = null;
            $u->mfa_confirmed_at = null;
            $u->mfa_recovery_codes = null;
            $u->mfa_last_step = null;
            $u->auth_version++;
            $u->save();
            DB::table('sessions')->where('user_id', $id)->delete();
            Audit::record('users.mfa_reset', 'users', $id);
        });

        return back()->with('status', 'Segundo factor reiniciado; sesiones revocadas.');
    }

    public function role(Request $request)
    {
        if (! $request->input('permissions')) {
            $request->merge(['permissions' => []]);
        }$d = $request->validate(['id' => 'nullable|integer|exists:roles,id', 'name' => ['required', 'regex:/^[a-z0-9_]{3,80}$/'], 'permissions' => 'present|array', 'permissions.*' => 'integer|distinct|exists:permissions,id']);
        DB::transaction(function () use ($d) {
            $role = isset($d['id']) ? Role::lockForUpdate()->findOrFail($d['id']) : new Role;
            abort_if($role->name === 'superadmin' || $d['name'] === 'superadmin', 422, 'El rol superadmin es reservado e inmutable.');
            abort_if(Role::where('name', $d['name'])->where('id', '!=', $role->id ?? 0)->exists(), 422, 'El nombre ya existe.');
            $role->name = $d['name'];
            $role->save();
            $role->permissions()->sync($d['permissions']);
            foreach ($role->users()->get() as $u) {
                $u->auth_version++;
                $u->save();
                DB::table('sessions')->where('user_id', $u->id)->delete();
            }Audit::record('roles.updated', 'roles', $role->id, ['count' => count($d['permissions'])]);
        });

        return back()->with('status', 'Rol y permisos actualizados; sesiones afectadas revocadas.');
    }
}
