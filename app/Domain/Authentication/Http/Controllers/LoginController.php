<?php

namespace App\Domain\Authentication\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Authentication\Services\Authenticator;
use App\Domain\Authentication\Services\SecurityPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;

class LoginController extends Controller
{
    public function create(Request $request, SecurityPolicy $policy): View
    {
        return view('auth.login', [
            'passkeysEnabled' => $policy->passkeysAllowed(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, Authenticator $auth): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $user = $auth->attempt($request, $data['login'], $data['password']);
        $remember = (bool) ($data['remember'] ?? false);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('login.two_factor', [
                'id' => $user->id,
                'remember' => $remember,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        $auth->completeLogin($request, $user, $remember);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            AuditLog::record('logout', $user, [], [], 'Signed out', $user->id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
