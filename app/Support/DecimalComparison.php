<?php

namespace App\Support;

final class DecimalComparison
{
    /** Compare decimal strings without float conversion; null means invalid input. */
    public static function compare(string $left, string $right): ?int
    {
        $a = self::parts($left);
        $b = self::parts($right);
        if ($a === null || $b === null) {
            return null;
        }
        if ($a['sign'] !== $b['sign']) {
            return $a['sign'] <=> $b['sign'];
        }
        if ($a['sign'] === 0) {
            return 0;
        }
        $magnitude = $a['position'] <=> $b['position'];
        if ($magnitude === 0) {
            $length = max(strlen($a['digits']), strlen($b['digits']));
            $magnitude = strcmp(str_pad($a['digits'], $length, '0'), str_pad($b['digits'], $length, '0')) <=> 0;
        }

        return $a['sign'] * $magnitude;
    }

    private static function parts(string $value): ?array
    {
        if (! preg_match('/^([+-]?)(?:(\d+)(?:\.(\d*))?|\.(\d+))(?:[eE]([+-]?\d+))?$/', trim($value), $m)) {
            return null;
        }
        $whole = $m[2] !== '' ? $m[2] : '0';
        $raw = $whole.($m[2] !== '' ? ($m[3] ?? '') : ($m[4] ?? ''));
        $digits = ltrim($raw, '0');
        if ($digits === '') {
            return ['sign' => 0, 'digits' => '', 'position' => 0];
        }
        // Reject unrepresentable exponents rather than overflow or expand them.
        $exponentText = $m[5] ?? '0';
        if (strlen(ltrim(ltrim($exponentText, '+-'), '0')) > 9) {
            return null;
        }
        $exponent = (int) $exponentText;

        return [
            'sign' => $m[1] === '-' ? -1 : 1,
            'digits' => rtrim($digits, '0'),
            'position' => strlen($whole) + $exponent - (strlen($raw) - strlen($digits)),
        ];
    }
}
