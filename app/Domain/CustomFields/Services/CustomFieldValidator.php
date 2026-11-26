<?php

namespace App\Domain\CustomFields\Services;

use App\Support\DecimalComparison;
use DateTimeImmutable;

/**
 * Pure validation/normalisation of custom field input.
 *
 * Definition: [key, label, type, required, options, validation]
 *   options:    list<string|array{value,label}> for dropdown/multi_select
 *   validation: min, max, max_length, entity (relation target), allowed (relation types)
 *
 * $exists(type, id) lets callers verify user/asset/relation references.
 */
final class CustomFieldValidator
{
    /** @var callable|null */
    private $exists;

    public function __construct(?callable $exists = null)
    {
        $this->exists = $exists;
    }

    /**
     * @param  list<array>  $definitions
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, string>, 1: array<string, mixed>} [errors keyed by field key, cleaned values]
     */
    public function validate(array $definitions, array $input, bool $partial = false): array
    {
        $errors = [];
        $clean = [];

        foreach ($definitions as $def) {
            $key = $def['key'];
            if ($partial && ! array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key] ?? null;
            $empty = $value === null || $value === '' || $value === [];

            if ($empty) {
                if (! empty($def['required']) && $def['type'] !== 'checkbox') {
                    $errors[$key] = "{$def['label']} is required.";
                } else {
                    $clean[$key] = $def['type'] === 'checkbox' ? false : null;
                }

                continue;
            }

            [$error, $normalised] = $this->field($def, $value);
            if ($error !== null) {
                $errors[$key] = $error;
            } else {
                $clean[$key] = $normalised;
            }
        }

        return [$errors, $clean];
    }

    /** @return array{0: ?string, 1: mixed} */
    private function field(array $def, mixed $value): array
    {
        $label = $def['label'];
        $rules = $def['validation'] ?? [];

        switch ($def['type']) {
            case 'text':
            case 'long_text':
                if (! is_scalar($value)) {
                    return ["{$label} must be text.", null];
                }
                $value = trim((string) $value);
                $max = (int) ($rules['max_length'] ?? ($def['type'] === 'text' ? 255 : 20000));
                if (mb_strlen($value) > $max) {
                    return ["{$label} may not be longer than {$max} characters.", null];
                }

                return [null, $value];

            case 'number':
            case 'currency':
                if (! is_numeric($value)) {
                    return ["{$label} must be a number.", null];
                }
                $number = DecimalComparison::numberToString($value);
                $n = $number !== null ? $this->decimal($number, $def['type'] === 'currency') : null;
                if ($n === null) {
                    return ["{$label} must fit within 14 integer digits and 4 decimal places.", null];
                }
                foreach (['min', 'max'] as $bound) {
                    if (! isset($rules[$bound])) {
                        continue;
                    }
                    $limit = DecimalComparison::numberToString($rules[$bound]);
                    $comparison = $limit !== null ? DecimalComparison::compare($n, $limit) : null;
                    if ($comparison === null) {
                        return ["{$label} has an invalid {$bound} limit.", null];
                    }
                    if ($bound === 'min' && $comparison < 0) {
                        return ["{$label} must be at least {$limit}.", null];
                    }
                    if ($bound === 'max' && $comparison > 0) {
                        return ["{$label} may not be greater than {$limit}.", null];
                    }
                }

                return [null, $n];

            case 'date':
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

                return $d && $d->format('Y-m-d') === (string) $value ? [null, (string) $value] : ["{$label} must be a date (YYYY-MM-DD).", null];

            case 'datetime':
                try {
                    return [null, (new DateTimeImmutable((string) $value))->format('Y-m-d H:i:s')];
                } catch (\Exception) {
                    return ["{$label} must be a valid date and time.", null];
                }

            case 'checkbox':
                $b = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                return $b === null ? ["{$label} must be yes or no.", null] : [null, $b];

            case 'dropdown':
                return in_array((string) $value, $this->optionValues($def), true) ? [null, (string) $value] : ["{$label} must be one of the listed options.", null];

            case 'multi_select':
                $values = array_map('strval', (array) $value);
                $invalid = array_diff($values, $this->optionValues($def));

                return $invalid === [] ? [null, array_values(array_unique($values))] : ["{$label} contains an invalid option.", null];

            case 'url':
                $ok = filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', (string) $value);

                return $ok ? [null, (string) $value] : ["{$label} must be a valid http(s) URL.", null];

            case 'user':
            case 'asset':
                if (! ctype_digit((string) $value) || (int) $value < 1) {
                    return ["{$label} must reference a {$def['type']}.", null];
                }
                if ($this->exists && ! ($this->exists)($def['type'], (int) $value)) {
                    return ["The selected {$def['type']} for {$label} does not exist.", null];
                }

                return [null, (int) $value];

            case 'relation':
                if (! is_array($value) || ! isset($value['type'], $value['id']) || ! ctype_digit((string) $value['id'])) {
                    return ["{$label} must reference a record.", null];
                }
                $allowed = (array) ($rules['allowed'] ?? (isset($rules['entity']) ? [$rules['entity']] : []));
                if ($allowed !== [] && ! in_array($value['type'], $allowed, true)) {
                    return ["{$label} cannot reference a {$value['type']}.", null];
                }
                if ($this->exists && ! ($this->exists)((string) $value['type'], (int) $value['id'])) {
                    return ["The record selected for {$label} does not exist.", null];
                }

                return [null, ['type' => (string) $value['type'], 'id' => (int) $value['id']]];

            case 'file':
                if (! is_array($value) || empty($value['path']) || str_contains((string) $value['path'], '..')) {
                    return ["{$label} must be an uploaded file.", null];
                }

                return [null, ['path' => (string) $value['path'], 'name' => (string) ($value['name'] ?? basename((string) $value['path'])), 'size' => (int) ($value['size'] ?? 0)]];
        }

        return ["{$label} has an unsupported type.", null];
    }

    private function optionValues(array $def): array
    {
        return array_map(
            fn ($o) => (string) (is_array($o) ? ($o['value'] ?? $o['label'] ?? '') : $o),
            $def['options'] ?? [],
        );
    }

    /** Normalize DECIMAL(18,4) without first losing precision to a float. */
    private function decimal(string $value, bool $currency): ?string
    {
        if (! preg_match('/^([+-]?)(?:(\d+)(?:\.(\d*))?|\.(\d+))(?:[eE]([+-]?\d+))?$/', trim($value), $m)) {
            return null;
        }
        $whole = $m[2] !== '' ? $m[2] : '0';
        $fraction = $m[2] !== '' ? ($m[3] ?? '') : ($m[4] ?? '');
        $raw = $whole.$fraction;
        $digits = ltrim($raw, '0');
        if ($digits === '') {
            return '0';
        }
        $exponent = (int) ($m[5] ?? 0);
        $position = strlen($whole) + $exponent - (strlen($raw) - strlen($digits));
        $digits = rtrim($digits, '0');
        if ($position > 14 || max(0, strlen($digits) - $position) > 4) {
            return null;
        }
        $integer = $position > 0 ? substr(str_pad($digits, $position, '0'), 0, $position) : '0';
        $decimal = $position < 0 ? str_repeat('0', -$position).$digits : substr($digits, $position);
        if ($currency) {
            $decimal = str_pad($decimal, 3, '0');
            $cents = $integer.substr($decimal, 0, 2);
            if ($decimal[2] >= '5') {
                for ($i = strlen($cents) - 1; $i >= 0; $i--) {
                    if ($cents[$i] !== '9') {
                        $cents[$i] = (string) ((int) $cents[$i] + 1);
                        break;
                    }
                    $cents[$i] = '0';
                }
                if ($i < 0) {
                    $cents = '1'.$cents;
                }
            }
            $integer = substr($cents, 0, -2);
            $decimal = substr($cents, -2);
            if (strlen($integer) > 14) {
                return null;
            }
        }

        return ($m[1] === '-' ? '-' : '').$integer.($decimal !== '' ? '.'.$decimal : '');
    }
}
