<?php

namespace App\Domain\Authentication\Services;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;

/** TOTP enrolment, verification (with replay protection) and recovery codes. */
class TwoFactorAuthenticator
{
    public function __construct(
        private readonly Totp $totp,
        private readonly RecoveryCodes $recoveryCodes,
        private readonly SettingsRepository $settings,
    ) {}

    /** @return array{secret: string, otpauth_url: string, qr_svg: string} */
    public function beginEnrolment(User $user): array
    {
        $secret = $this->totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->saveQuietly();

        return $this->enrolmentPayload($user, $secret);
    }

    /** @return array{secret: string, otpauth_url: string, qr_svg: string} */
    public function enrolmentPayload(User $user, string $secret): array
    {
        $issuer = (string) $this->settings->get('general.organization_name', config('app.name'));
        $uri = $this->totp->provisioningUri($secret, $user->email, $issuer ?: 'StageTrack');

        return [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'otpauth_url' => $uri,
            'qr_svg' => $this->qrDataUri($uri),
        ];
    }

    /**
     * Confirms a pending enrolment with a code from the authenticator app.
     *
     * @return list<string>|null plain recovery codes (shown once) or null when the code is wrong
     */
    public function confirm(User $user, string $code): ?array
    {
        if (! $user->two_factor_secret || $user->two_factor_confirmed_at) {
            return null;
        }
        if (! $this->verify($user, $code)) {
            return null;
        }
        $codes = $this->recoveryCodes->generate();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->recoveryCodes->hashAll($codes),
        ])->saveQuietly();
        AuditLog::record('two_factor.enabled', $user, [], [], 'Authenticator app enrolled', $user->id);

        return $codes;
    }

    /** Verifies a TOTP code; each time step may only be used once per user. */
    public function verify(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }
        $cacheKey = 'two-factor:last-step:'.$user->id;
        $lastStep = Cache::get($cacheKey);
        $step = $this->totp->verify($user->two_factor_secret, $code, 1, null, is_int($lastStep) ? $lastStep : null);
        if ($step === null) {
            return false;
        }
        Cache::put($cacheKey, $step, now()->addMinutes(5));

        return true;
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $remaining = $this->recoveryCodes->consume((array) ($user->two_factor_recovery_codes ?? []), $code);
        if ($remaining === null) {
            return false;
        }
        $user->forceFill(['two_factor_recovery_codes' => $remaining])->saveQuietly();
        AuditLog::record('two_factor.recovery_code_used', $user, [], [], count($remaining).' recovery codes remaining', $user->id);

        return true;
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->recoveryCodes->generate();
        $user->forceFill(['two_factor_recovery_codes' => $this->recoveryCodes->hashAll($codes)])->saveQuietly();
        AuditLog::record('two_factor.recovery_regenerated', $user, [], [], 'Recovery codes regenerated', $user->id);

        return $codes;
    }

    public function disable(User $user, ?string $description = null): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->saveQuietly();
        Cache::forget('two-factor:last-step:'.$user->id);
        AuditLog::record('two_factor.disabled', $user, [], [], $description ?? 'Two-factor authentication disabled');
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count((array) ($user->two_factor_recovery_codes ?? []));
    }

    public function qrDataUri(string $content): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($content);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
