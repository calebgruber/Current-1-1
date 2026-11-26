<?php

namespace App\Domain\Authentication\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;

/** Records which second factor was actually verified in this session. */
class SessionAssurance
{
    private const KEY = 'auth.second_factor';

    public function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    public function markTwoFactor(Request $request, User $user): void
    {
        $request->session()->put(self::KEY, [
            'user' => (string) $user->getKey(),
            'method' => 'totp',
            'secret_hash' => hash('sha256', (string) $user->two_factor_secret),
        ]);
    }

    public function markPasskey(Request $request, User $user, Passkey $passkey): void
    {
        $request->session()->put(self::KEY, [
            'user' => (string) $user->getKey(),
            'method' => 'passkey',
            'passkey' => (string) $passkey->getKey(),
        ]);
    }

    public function satisfied(Request $request, User $user, SecurityPolicy $policy): bool
    {
        $proof = $request->session()->get(self::KEY);
        if (! is_array($proof) || ($proof['user'] ?? null) !== (string) $user->getKey()) {
            return false;
        }

        return match ($proof['method'] ?? null) {
            'totp' => $user->hasTwoFactorEnabled()
                && is_string($proof['secret_hash'] ?? null)
                && hash_equals($proof['secret_hash'], hash('sha256', (string) $user->two_factor_secret)),
            'passkey' => $policy->passkeysAllowed()
                && is_string($proof['passkey'] ?? null)
                && $user->passkeys()->whereKey($proof['passkey'])->exists(),
            default => false,
        };
    }
}
