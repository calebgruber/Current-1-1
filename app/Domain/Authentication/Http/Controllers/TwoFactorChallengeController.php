<?php

namespace App\Domain\Authentication\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Authentication\Services\Authenticator;
use App\Domain\Authentication\Services\TwoFactorAuthenticator;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request, TwoFactorAuthenticator $twoFactor, Authenticator $auth): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => 'Your sign-in expired. Please sign in again.']);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:16', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:32', 'required_without:code'],
        ]);

        if (! empty($data['code'])) {
            if (! $twoFactor->verify($user, $data['code'])) {
                AuditLog::record('login.failed', $user, [], [], 'Invalid two-factor code', $user->id);
                throw ValidationException::withMessages(['code' => 'The code is invalid or has already been used.']);
            }
            $method = 'authenticator app';
        } else {
            if (! $twoFactor->useRecoveryCode($user, (string) $data['recovery_code'])) {
                AuditLog::record('login.failed', $user, [], [], 'Invalid recovery code', $user->id);
                throw ValidationException::withMessages(['recovery_code' => 'The recovery code is invalid.']);
            }
            $method = 'recovery code';
        }

        $pending = $request->session()->pull('login.two_factor');
        $auth->completeLogin($request, $user, (bool) ($pending['remember'] ?? false), 'login', 'Signed in with password + '.$method);

        return redirect()->intended(route('dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('login.two_factor');
        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('login.two_factor');

            return null;
        }
        $user = User::query()->find($pending['id'] ?? 0);

        return $user && $user->is_active && $user->hasTwoFactorEnabled() ? $user : null;
    }
}
