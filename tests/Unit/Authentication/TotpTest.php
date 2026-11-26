<?php

namespace Tests\Unit\Authentication;

use App\Domain\Authentication\Services\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** Base32 of the RFC 6238 SHA-1 seed "12345678901234567890". */
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public static function rfc6238Vectors(): array
    {
        return [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
            [20000000000, '65353130'],
        ];
    }

    #[DataProvider('rfc6238Vectors')]
    public function test_rfc6238_eight_digit_vectors(int $time, string $expected): void
    {
        $this->assertSame($expected, (new Totp(8))->code(self::SECRET, $time));
    }

    #[DataProvider('rfc6238Vectors')]
    public function test_six_digit_codes_are_truncations(int $time, string $expected): void
    {
        $this->assertSame(substr($expected, -6), (new Totp)->code(self::SECRET, $time));
    }

    public function test_base32_round_trip(): void
    {
        $this->assertSame(self::SECRET, Totp::base32Encode('12345678901234567890'));
        $this->assertSame('12345678901234567890', Totp::base32Decode(self::SECRET));
        $this->assertSame('12345678901234567890', Totp::base32Decode(strtolower(chunk_split(self::SECRET, 4, ' '))));
        $this->assertSame('foobar', Totp::base32Decode('MZXW6YTBOI======'));
        $this->assertSame('MZXW6YTBOI', Totp::base32Encode('foobar'));
        $this->assertSame('', Totp::base32Encode(''));

        $bytes = random_bytes(20);
        $this->assertSame($bytes, Totp::base32Decode(Totp::base32Encode($bytes)));
    }

    public function test_generate_secret(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertSame(20, strlen(Totp::base32Decode($secret)));
        $this->assertNotSame($secret, $totp->generateSecret());
    }

    public function test_verify_returns_matched_step(): void
    {
        $totp = new Totp;
        $this->assertSame(intdiv(1111111109, 30), $totp->verify(self::SECRET, '081804', 1, 1111111109));
        $this->assertSame(intdiv(1111111109, 30), $totp->verify(self::SECRET, ' 081 804 ', 1, 1111111109), 'whitespace ignored');
    }

    public function test_verify_rejects_wrong_or_malformed_codes(): void
    {
        $totp = new Totp;
        $this->assertNull($totp->verify(self::SECRET, '081805', 1, 1111111109));
        $this->assertNull($totp->verify(self::SECRET, '000000', 1, 1111111109));
        $this->assertNull($totp->verify(self::SECRET, '', 1, 1111111109));
        $this->assertNull($totp->verify(self::SECRET, '81804', 1, 1111111109), 'too short');
        $this->assertNull($totp->verify(self::SECRET, '07081804', 1, 1111111109), 'too long for 6 digits');
        $this->assertNull($totp->verify(self::SECRET, '08180a', 1, 1111111109));
        $this->assertNull($totp->verify('AAAAAAAAAAAAAAAA', '081804', 1, 1111111109), 'other secret');
    }

    public function test_window_tolerance(): void
    {
        $totp = new Totp;
        $t = 1111111109;
        $step = intdiv($t, 30);
        $previous = $totp->code(self::SECRET, $t - 30);
        $next = $totp->code(self::SECRET, $t + 30);
        $twoAhead = $totp->code(self::SECRET, $t + 60);

        $this->assertSame($step - 1, $totp->verify(self::SECRET, $previous, 1, $t));
        $this->assertSame($step + 1, $totp->verify(self::SECRET, $next, 1, $t));
        $this->assertNull($totp->verify(self::SECRET, $twoAhead, 1, $t));
        $this->assertSame($step + 2, $totp->verify(self::SECRET, $twoAhead, 2, $t));
        $this->assertNull($totp->verify(self::SECRET, $previous, 0, $t), 'window 0 = current step only');
    }

    public function test_replay_rejected_with_last_used_step(): void
    {
        $totp = new Totp;
        $t = 1234567890;
        $code = $totp->code(self::SECRET, $t);
        $step = $totp->verify(self::SECRET, $code, 1, $t);
        $this->assertNotNull($step);

        $this->assertNull($totp->verify(self::SECRET, $code, 1, $t, $step), 'same step may not be reused');
        $this->assertNull($totp->verify(self::SECRET, $totp->code(self::SECRET, $t - 30), 1, $t, $step), 'earlier steps rejected');
        $this->assertSame($step + 1, $totp->verify(self::SECRET, $totp->code(self::SECRET, $t + 30), 1, $t, $step));
    }

    public function test_custom_period(): void
    {
        $totp = new Totp(6, 60);
        $this->assertSame($totp->code(self::SECRET, 0), $totp->code(self::SECRET, 59));
        $this->assertNotSame($totp->code(self::SECRET, 59), $totp->code(self::SECRET, 60));
        // Counter 1 with a 60s period equals counter 1 with the default 30s period (T=59 vector).
        $this->assertSame('287082', $totp->code(self::SECRET, 60));
        $this->assertSame(1, $totp->verify(self::SECRET, '287082', 0, 119));
    }

    public function test_provisioning_uri(): void
    {
        $uri = (new Totp)->provisioningUri(self::SECRET, 'jane@example.test', 'StageTrack Enterprise');
        $this->assertSame(
            'otpauth://totp/StageTrack%20Enterprise:jane%40example.test?secret='.self::SECRET.'&issuer=StageTrack%20Enterprise&algorithm=SHA1&digits=6&period=30',
            $uri,
        );
    }

    public function test_code_without_timestamp_uses_current_time(): void
    {
        $totp = new Totp;
        $now = time();
        $code = $totp->code(self::SECRET);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotNull($totp->verify(self::SECRET, $code, 1, $now));
    }
}
