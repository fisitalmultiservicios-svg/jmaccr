<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAccess
{
    public function handle(Request $request, Closure $next, string $mode = 'required')
    {
        $user = $request->user();
        if (! $user || $user->status !== 'active' || $request->session()->get('auth_version') !== $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }
        if ($mode !== 'session' && config('auth.mfa_required', true) !== false && $user->requiresMfa()) {
            if (! $user->mfa_confirmed_at) {
                return redirect()->route('mfa.enroll');
            }
            if ($request->session()->get('mfa_user_id') !== $user->id) {
                return redirect()->route('mfa.challenge');
            }
        }

        $roles = $user->roles()->with('permissions')->get();
        $request->attributes->set('rbac.user_id', $user->id);
        $request->attributes->set('rbac.permissions', $roles->flatMap(fn ($role) => $role->permissions->pluck('name'))->unique()->all());
        try {
            return $next($request);
        } finally {
            $request->attributes->remove('rbac.user_id');
            $request->attributes->remove('rbac.permissions');
        }
    }
}
