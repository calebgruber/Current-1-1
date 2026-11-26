<?php

namespace App\Domain\Authentication\Http\Controllers\Account;

use App\Domain\Authentication\Services\SecurityPolicy;
use App\Domain\Authentication\Services\SessionAssurance;
use App\Domain\Authentication\Services\TwoFactorAuthenticator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthenticator $twoFactor) {}

    /** Starts (or restarts) enrolment and returns the secret + QR code. */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Two-factor authentication is already enabled.'], 409);
        }

        return response()->json($this->twoFactor->beginEnrolment($user));
    }

    public function confirm(Request $request, SessionAssurance $assurance): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Two-factor authentication is already enabled.'], 409);
        }

        $codes = $this->twoFactor->confirm($user, $data['code']);
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Check the time on your device and try again.']);
        }

        $assurance->markTwoFactor($request, $user);

        return response()->json(['recovery_codes' => $codes]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 409);
        }

        return response()->json(['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user)]);
    }

    public function disable(Request $request, SecurityPolicy $policy): RedirectResponse
    {
        $user = $request->user();
        if ($policy->requiresTwoFactor($user) && $user->hasTwoFactorEnabled()
            && (! $policy->passkeysAllowed() || $user->passkeys()->doesntExist())) {
            $message = $policy->passkeysAllowed()
                ? 'Your organization requires two-factor authentication. Add a passkey before removing your authenticator app.'
                : 'Your organization requires two-factor authentication, and passkeys are disabled.';

            return back()->with('error', $message);
        }

        $this->twoFactor->disable($user);

        return back()->with('success', 'Two-factor authentication disabled.');
    }
}
