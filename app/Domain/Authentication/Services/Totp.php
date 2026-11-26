<?php

namespace App\Domain\Authentication\Services;

/**
 * RFC 6238 time-based one-time passwords (SHA-1, 6 digits, 30s) compatible
 * with Google Authenticator, 1Password, Authy, Microsoft Authenticator, etc.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private readonly int $digits = 6,
        private readonly int $period = 30,
    ) {}

    public function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), $this->period);

        return $this->hotp(self::base32Decode($secret), $counter);
    }

    /**
     * Verifies a code within ±$window steps. Returns the matched time step so
     * callers can reject replays (a step may only be used once).
     */
    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null, ?int $lastUsedStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{'.$this->digits.'}$/', $code)) {
            return null;
        }
        $key = self::base32Decode($secret);
        $step = intdiv($timestamp ?? time(), $this->period);
        for ($i = -$window; $i <= $window; $i++) {
            $candidate = $step + $i;
            if ($lastUsedStep !== null && $candidate <= $lastUsedStep) {
                continue;
            }
            if (hash_equals($this->hotp($key, $candidate), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer), rawurlencode($account), $secret, rawurlencode($issuer), $this->digits, $this->period,
        );
    }

    private function hotp(string $key, int $counter): string
    {
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $data): string
    {
        $data = strtoupper(preg_replace('/[\s=-]+/', '', $data) ?? '');
        $bits = '';
        foreach (str_split($data) as $c) {
            $pos = strpos(self::ALPHABET, $c);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
