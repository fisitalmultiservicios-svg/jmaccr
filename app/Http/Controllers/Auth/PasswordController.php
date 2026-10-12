<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Audit;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    public function requestForm()
    {
        return view('auth.forgot');
    }

    public function send(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:254']);
        Password::sendResetLink(['email' => strtolower($data['email']), 'status' => 'active']);

        return back()->with('status', 'Si la cuenta está activa, recibirás instrucciones para restablecer tu contraseña.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->password = Hash::make($password);
            $user->remember_token = Str::random(60);
            $user->auth_version++;
            $user->save();
            Audit::record('auth.password_reset', 'users', $user->id);
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Enlace inválido o vencido.']);
        }

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Inicia sesión y verifica tu segundo factor.');
    }
}
