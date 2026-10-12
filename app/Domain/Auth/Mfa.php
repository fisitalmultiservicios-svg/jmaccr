<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;

final class Mfa
{
    public function enroll(User $user, string $secret, string $code): ?array
    {
        return DB::transaction(function () use ($user, $secret, $code) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($locked->mfa_confirmed_at, 409);
            $step = $this->matchingStep($secret, $code, -1);
            if ($step === null) {
                return null;
            }
            $codes = array_map(fn () => bin2hex(random_bytes(10)), range(1, 8));
            $locked->mfa_secret = $secret;
            $locked->mfa_recovery_codes = array_map(fn ($code) => Hash::make($code), $codes);
            $locked->mfa_confirmed_at = now();
            $locked->mfa_last_step = $step;
            $locked->save();
            Audit::record('mfa.enrolled', 'users', $user->id);

            return $codes;
        });
    }

    private function matchingStep(string $secret, string $code, int $last): ?int
    {
        if (! preg_match('/^\d{6}$/D', $code)) {
            return null;
        }
        $otp = TOTP::createFromSecret($secret, new NativeClock);
        $step = intdiv(time(), $otp->getPeriod());
        foreach ([$step, $step - 1, $step + 1] as $candidate) {
            if ($candidate > $last && hash_equals($otp->at($candidate * $otp->getPeriod()), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $locked->mfa_secret) {
                return false;
            }
            $step = $this->matchingStep($locked->mfa_secret, $code, (int) $locked->mfa_last_step);
            if ($step === null) {
                return false;
            }
            $locked->mfa_last_step = $step;
            $locked->save();

            return true;
        });
    }

    public function recover(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            foreach ($locked->mfa_recovery_codes ?? [] as $key => $hash) {
                if (Hash::check($code, $hash)) {
                    $codes = $locked->mfa_recovery_codes;
                    unset($codes[$key]);
                    $locked->mfa_recovery_codes = array_values($codes);
                    $locked->save();
                    Audit::record('mfa.recovery_used', 'users', $user->id);

                    return true;
                }
            }

            return false;
        });
    }
}
