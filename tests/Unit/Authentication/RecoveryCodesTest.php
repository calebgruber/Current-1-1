<?php

namespace Tests\Unit\Authentication;

use App\Domain\Authentication\Services\RecoveryCodes;
use PHPUnit\Framework\TestCase;

class RecoveryCodesTest extends TestCase
{
    private RecoveryCodes $codes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codes = new RecoveryCodes;
    }

    public function test_generate_format_and_uniqueness(): void
    {
        $codes = $this->codes->generate();
        $this->assertCount(10, $codes);
        $this->assertSame($codes, array_values(array_unique($codes)));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}$/', $code);
            $this->assertDoesNotMatchRegularExpression('/[01OI]/', $code, 'ambiguous characters excluded');
        }
        $this->assertCount(3, $this->codes->generate(3));
        $this->assertSame([], $this->codes->generate(0));
    }

    public function test_hash_is_sha256_of_normalised_code(): void
    {
        $this->assertSame(hash('sha256', 'ABCD2345WXYZ'), $this->codes->hash('ABCD-2345-WXYZ'));
        $this->assertSame($this->codes->hash('ABCD-2345-WXYZ'), $this->codes->hash(' abcd 2345 wxyz '));
        $this->assertNotSame($this->codes->hash('ABCD-2345-WXYZ'), $this->codes->hash('ABCD-2345-WXY2'));
        $this->assertSame(64, strlen($this->codes->hash('x')));
    }

    public function test_hash_all(): void
    {
        $plain = ['AAAA-BBBB-CCCC', 'DDDD-EEEE-FFFF'];
        $this->assertSame([$this->codes->hash($plain[0]), $this->codes->hash($plain[1])], $this->codes->hashAll($plain));
        $this->assertNotContains($plain[0], $this->codes->hashAll($plain));
    }

    public function test_consume_removes_used_code_and_reindexes(): void
    {
        $plain = $this->codes->generate(3);
        $hashes = $this->codes->hashAll($plain);

        $remaining = $this->codes->consume($hashes, strtolower($plain[1]));
        $this->assertSame([$hashes[0], $hashes[2]], $remaining);

        $this->assertNull($this->codes->consume($remaining, $plain[1]), 'code is single-use');

        $remaining = $this->codes->consume($remaining, str_replace('-', '', $plain[0]));
        $this->assertSame([$hashes[2]], $remaining);
        $this->assertSame([], $this->codes->consume($remaining, $plain[2]));
    }

    public function test_consume_rejects_unknown_code(): void
    {
        $hashes = $this->codes->hashAll(['AAAA-BBBB-CCCC']);
        $this->assertNull($this->codes->consume($hashes, 'AAAA-BBBB-CCCD'));
        $this->assertNull($this->codes->consume($hashes, ''));
        $this->assertNull($this->codes->consume([], 'AAAA-BBBB-CCCC'));
    }
}
