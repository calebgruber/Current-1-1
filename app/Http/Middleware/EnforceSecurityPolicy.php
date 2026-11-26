<?php

namespace App\Http\Middleware;

use App\Domain\Authentication\Services\SecurityPolicy;
use App\Domain\Authentication\Services\SessionAssurance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies organization security policy to authenticated sessions:
 * deactivated users are signed out, and users who must enrol in
 * two-factor authentication (or rotate an expired password) are sent
 * to their security settings until they comply.
 */
class EnforceSecurityPolicy
{
    public function __construct(private readonly SecurityPolicy $policy, private readonly SessionAssurance $assurance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['login' => 'Your account has been deactivated.']);
        }

        if ($this->policy->requiresTwoFactor($user) && ! $this->assurance->satisfied($request, $user, $this->policy)) {
            $hasFactor = $user->hasTwoFactorEnabled()
                || ($this->policy->passkeysAllowed() && $user->passkeys()->exists());

            // Existing factors must be verified before they can be replaced.
            if ($request->routeIs('logout') || ($request->isMethod('GET') && $request->routeIs('account.security'))) {
                return $next($request);
            }
            if (! $hasFactor && $request->routeIs('account.security*', 'passkey.*', 'password.confirm*', 'account.password*')) {
                return $next($request);
            }

            $warning = $hasFactor
                ? 'Sign out and sign in using your authenticator app or passkey to continue.'
                : 'Your organization requires two-factor authentication or a passkey. Please set one up to continue.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $warning], 403);
            }

            return redirect()->route('account.security')->with('warning', $warning);
        }

        $exempt = $request->routeIs('account.security*', 'logout', 'two-factor.*', 'passkey.*', 'password.confirm*', 'account.password*');
        if ($exempt) {
            return $next($request);
        }

        if ($this->policy->passwordExpired($user)) {
            return redirect()->route('account.security')->with('warning', 'Your password has expired. Please choose a new password.');
        }

        return $next($request);
    }
}
