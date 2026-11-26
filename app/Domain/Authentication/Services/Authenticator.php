<?php

namespace App\Domain\Authentication\Services;

use App\Domain\Administration\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Password sign-in with per-account lockout (security.max_login_attempts /
 * security.lockout_minutes) and a per-identifier+IP limiter so unknown
 * usernames are throttled exactly like real ones.
 */
class Authenticator
{
    public function __construct(private readonly SecurityPolicy $policy) {}

    public function findUser(string $login): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }
        $column = str_contains($login, '@') ? 'email' : 'username';

        return User::query()->whereRaw("LOWER({$column}) = ?", [Str::lower($login)])->first();
    }

    /**
     * Validates credentials and returns the user without logging them in.
     *
     * @throws ValidationException
     */
    public function attempt(Request $request, string $login, string $password): User
    {
        $key = $this->throttleKey($request, $login);
        $max = $this->policy->maxLoginAttempts();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->fail('Too many sign-in attempts. Try again in '.$this->minutesText(RateLimiter::availableIn($key)).'.');
        }

        $user = $this->findUser($login);

        if ($user && $user->isLocked()) {
            AuditLog::record('login.failed', $user, [], [], 'Sign-in attempt on a locked account', $user->id);
            $this->fail('This account is temporarily locked. Try again in '.$this->minutesText((int) now()->diffInSeconds($user->locked_until, true)).'.');
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, $this->policy->lockoutMinutes() * 60);
            $this->recordFailure($user, $login);
            $this->fail(__('auth.failed'));
        }

        if (! $user->is_active) {
            AuditLog::record('login.failed', $user, [], [], 'Sign-in attempt on a deactivated account', $user->id);
            $this->fail('Your account has been deactivated. Contact an administrator.');
        }

        RateLimiter::clear($key);

        return $user;
    }

    public function completeLogin(Request $request, User $user, bool $remember = false, string $event = 'login', ?string $description = null): void
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->saveQuietly();

        AuditLog::record($event, $user, [], [], $description ?? 'Signed in', $user->id);
    }

    private function recordFailure(?User $user, string $login): void
    {
        if (! $user) {
            AuditLog::record('login.failed', null, [], [], 'Failed sign-in for "'.Str::limit($login, 120).'"', null);

            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $updates = ['failed_login_attempts' => $attempts];
        if ($attempts >= $this->policy->maxLoginAttempts()) {
            $updates = ['failed_login_attempts' => 0, 'locked_until' => now()->addMinutes($this->policy->lockoutMinutes())];
        }
        $user->forceFill($updates)->saveQuietly();

        AuditLog::record('login.failed', $user, [], [], 'Incorrect password', $user->id);
        if (isset($updates['locked_until'])) {
            AuditLog::record('lockout', $user, [], ['locked_until' => $updates['locked_until']->toIso8601String()], 'Account locked after '.$attempts.' failed attempts', $user->id);
        }
    }

    private function throttleKey(Request $request, string $login): string
    {
        return 'login:'.sha1(Str::lower(trim($login)).'|'.$request->ip());
    }

    private function minutesText(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }

    /** @throws ValidationException */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['login' => $message]);
    }
}
