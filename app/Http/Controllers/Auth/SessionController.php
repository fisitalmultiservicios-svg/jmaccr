<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Audit;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:1024']);
        $key = 'login:'.hash('sha256', strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Demasiados intentos. Intenta de nuevo más tarde.');
        }
        RateLimiter::hit($key, 300);
        $user = User::where('email', strtolower($data['email']))->first();
        // A fixed dummy hash avoids a fast path for unknown accounts.
        $hash = $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (! Hash::check($data['password'], $hash) || ! $user || $user->status !== 'active') {
            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }
        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->auth_version);
        $request->session()->forget('mfa_user_id');
        $user->last_login_at = now();
        $user->save();
        Audit::record('auth.password_verified', 'users', $user->id);

        return redirect('/admin');
    }

    public function destroy(Request $request)
    {
        Audit::record('auth.logout', 'users', $request->user()?->id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
