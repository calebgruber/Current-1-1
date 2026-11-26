<?php

namespace App\Domain\Authentication\Services;

/**
 * Single-use recovery codes. Codes are stored hashed (SHA-256) so a database
 * leak does not reveal them; the plain codes are shown to the user once.
 */
final class RecoveryCodes
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /** @return list<string> plain codes like "K7QM-2XPA-9RTD" */
    public function generate(int $count = 10): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $raw = '';
            for ($i = 0; $i < 12; $i++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[] = implode('-', str_split($raw, 4));
        }

        return array_values(array_unique($codes));
    }

    /** @param list<string> $codes */
    public function hashAll(array $codes): array
    {
        return array_map(fn (string $c) => $this->hash($c), $codes);
    }

    public function hash(string $code): string
    {
        return hash('sha256', $this->normalise($code));
    }

    /**
     * Consumes a code. Returns the remaining hashes on success, null otherwise.
     *
     * @param  list<string>  $hashes
     * @return list<string>|null
     */
    public function consume(array $hashes, string $code): ?array
    {
        $candidate = $this->hash($code);
        foreach ($hashes as $i => $hash) {
            if (hash_equals($hash, $candidate)) {
                unset($hashes[$i]);

                return array_values($hashes);
            }
        }

        return null;
    }

    private function normalise(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }
}
