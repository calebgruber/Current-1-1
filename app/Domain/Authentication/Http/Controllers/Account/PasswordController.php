<?php

namespace App\Domain\Authentication\Http\Controllers\Account;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Authentication\Services\SecurityPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function edit(Request $request, SecurityPolicy $policy)
    {
        return view('account.password', [
            'expired' => $policy->passwordExpired($request->user()),
            'policy' => $this->policySummary($policy),
        ]);
    }

    public function update(Request $request, SecurityPolicy $policy): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', $policy->passwordRule()],
        ]);

        $user = $request->user();
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from your current one.']);
        }

        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();
        $request->session()->passwordConfirmed();
        AuditLog::record('password.changed', $user, [], [], 'Password changed by user');

        return redirect()->route('account.security')->with('success', 'Password updated.');
    }

    /** @return array{min_length: int, mixed: bool, expiry_days: int} */
    public static function policySummary(SecurityPolicy $policy): array
    {
        $settings = app(\App\Domain\Settings\Services\SettingsRepository::class);

        return [
            'min_length' => (int) $settings->get('security.password_min_length', 12),
            'mixed' => (bool) $settings->get('security.password_require_mixed', true),
            'expiry_days' => (int) $settings->get('security.password_expiry_days', 0),
        ];
    }
}
