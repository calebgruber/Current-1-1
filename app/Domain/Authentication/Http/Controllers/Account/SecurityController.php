<?php

namespace App\Domain\Authentication\Http\Controllers\Account;

use App\Domain\Authentication\Services\ActiveSessions;
use App\Domain\Authentication\Services\SecurityPolicy;
use App\Domain\Authentication\Services\TwoFactorAuthenticator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;

class SecurityController extends Controller
{
    public function show(Request $request, SecurityPolicy $policy, TwoFactorAuthenticator $twoFactor, ActiveSessions $sessions)
    {
        $user = $request->user();

        return view('account.security', [
            'twoFactor' => [
                'enabled' => $user->hasTwoFactorEnabled(),
                'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
                'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
                'recovery_codes_remaining' => $twoFactor->remainingRecoveryCodes($user),
            ],
            'passkeys' => $user->passkeys()->latest()->get()->map(fn (Passkey $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'authenticator' => $p->authenticator ?? null,
                'last_used_at' => $p->last_used_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ])->values(),
            'sessions' => $sessions->supported() ? $sessions->forUser($user, $request->session()->getId()) : [],
            'sessionsSupported' => $sessions->supported(),
            'policy' => [
                'require_two_factor' => $policy->requiresTwoFactor($user),
                'password_expired' => $policy->passwordExpired($user),
                'passkeys_allowed' => $policy->passkeysAllowed(),
                'password' => PasswordController::policySummary($policy),
            ],
            'passwordChangedAt' => $user->password_changed_at?->toIso8601String(),
        ]);
    }

    public function sessions(Request $request, ActiveSessions $sessions): JsonResponse
    {
        return response()->json([
            'supported' => $sessions->supported(),
            'sessions' => $sessions->supported() ? $sessions->forUser($request->user(), $request->session()->getId()) : [],
        ]);
    }
}
