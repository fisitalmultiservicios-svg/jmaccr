<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Audit;
use App\Domain\Auth\Mfa;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;

class MfaController extends Controller
{
    public function enroll(Request $request)
    {
        if ($request->user()->mfa_confirmed_at) {
            return redirect()->route('mfa.challenge');
        }
        if (! $request->session()->has('mfa_pending_secret')) {
            $request->session()->put('mfa_pending_secret', TOTP::generate(new NativeClock)->getSecret());
            $request->session()->put('mfa_enroll_expires', time() + 600);
        }
        $otp = TOTP::createFromSecret($request->session()->get('mfa_pending_secret'), new NativeClock);
        $otp->setLabel($request->user()->email);
        $otp->setIssuer('JMAC web');

        return view('auth.mfa', ['enroll' => true, 'secret' => $otp->getSecret()]);
    }

    public function confirm(Request $request, Mfa $mfa)
    {
        abort_if($request->user()->mfa_confirmed_at, 409);
        $request->validate(['code' => 'required|digits:6']);
        $secret = $request->session()->get('mfa_pending_secret');
        if (! $secret || time() > $request->session()->get('mfa_enroll_expires', 0)) {
            $request->session()->forget(['mfa_pending_secret', 'mfa_enroll_expires']);
            throw ValidationException::withMessages(['code' => 'La configuración expiró. Vuelve a cargar esta página.']);
        }
        $codes = $mfa->enroll($request->user(), $secret, $request->string('code')->toString());
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'Código incorrecto.']);
        }
        $user = $request->user();
        $request->session()->forget(['mfa_pending_secret', 'mfa_enroll_expires']);
        $request->session()->regenerate();
        $request->session()->put('mfa_user_id', $user->id);

        return view('auth.recovery', ['codes' => $codes]);
    }

    public function challenge(Request $request)
    {
        if (! $request->user()->mfa_confirmed_at) {
            return redirect()->route('mfa.enroll');
        }

        return view('auth.mfa', ['enroll' => false]);
    }

    public function verify(Request $request, Mfa $mfa)
    {
        $data = $request->validate(['code' => 'required|string|max:40']);
        $user = $request->user();
        abort_unless($user->mfa_confirmed_at, 403);
        $valid = preg_match('/^\d{6}$/D', $data['code']) ? $mfa->verify($user, $data['code']) : $mfa->recover($user, $data['code']);
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Código inválido o ya utilizado.']);
        }
        $request->session()->regenerate();
        $request->session()->put('mfa_user_id', $user->id);
        Audit::record('mfa.verified', 'users', $user->id);

        return redirect('/admin');
    }
}
