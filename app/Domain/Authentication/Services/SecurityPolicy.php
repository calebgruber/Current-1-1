<?php

namespace App\Domain\Authentication\Services;

use App\Domain\Settings\Services\SettingsRepository;
use App\Models\User;
use Illuminate\Validation\Rules\Password;

/** Configurable organization security policy backed by the settings table. */
class SecurityPolicy
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function passwordRule(): Password
    {
        $rule = Password::min((int) $this->settings->get('security.password_min_length', 12));
        if ($this->settings->get('security.password_require_mixed', true)) {
            $rule = $rule->mixedCase()->numbers();
        }

        return $rule;
    }

    public function requiresTwoFactor(User $user): bool
    {
        if ($this->settings->get('security.require_two_factor', false)) {
            return true;
        }

        return $user->is_super_admin && (bool) $this->settings->get('security.require_two_factor_for_admins', true);
    }

    public function passwordExpired(User $user): bool
    {
        $days = (int) $this->settings->get('security.password_expiry_days', 0);
        if ($days <= 0 || ! $user->password_changed_at) {
            return false;
        }

        return $user->password_changed_at->addDays($days)->isPast();
    }

    public function maxLoginAttempts(): int
    {
        return max(1, (int) $this->settings->get('security.max_login_attempts', 5));
    }

    public function lockoutMinutes(): int
    {
        return max(1, (int) $this->settings->get('security.lockout_minutes', 15));
    }

    public function sessionLifetimeMinutes(): int
    {
        return max(5, (int) $this->settings->get('security.session_lifetime_minutes', 480));
    }

    public function passkeysAllowed(): bool
    {
        return (bool) $this->settings->get('security.allow_passkeys', true);
    }
}
