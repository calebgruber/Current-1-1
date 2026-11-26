<?php

namespace App\Domain\Rules\Services;

use App\Domain\Rules\DTOs\RuleFinding;

/**
 * Pure, data-driven validation of equipment packages.
 *
 * A line describes one allocation:
 *   [product_id, product, categories => list<int> (category + ancestors),
 *    tags => list<string>, custom => array<string, mixed> (product custom fields),
 *    required => int, spare => int]
 *
 * A rule definition (authored in the no-code builder):
 *   {
 *     "scope":       {"match": "category|tag|product|name|custom_field|any", "values": [...],
 *                     "field": "<custom field key>", "operator": "is|contains"},
 *     "check":       "requires|requires_any|max_quantity|min_spares",
 *     "requirement": {"match": "category|tag|product|name|custom_field", "values": [...],
 *                     "per": 1, "quantity": 1, "ratio": 0.1},
 *     "basis":       "pull|required",
 *     "message":     "Moving lights need {required} PowerCON cables; found {found}."
 *   }
 *
 * Placeholders: {required} {found} {missing} {items} {scope_quantity}.
 */
final class RuleEvaluator
{
    public const CHECKS = ['requires', 'requires_any', 'max_quantity', 'min_spares'];

    public const MATCHES = ['category', 'tag', 'product', 'name', 'custom_field', 'any'];

    /** Selectors usable for the supporting-equipment requirement. */
    public const REQUIREMENT_MATCHES = ['category', 'tag', 'product', 'name', 'custom_field'];

    public const OPERATORS = ['is', 'contains'];

    public const SEVERITIES = ['info', 'warning', 'error'];

    /**
     * @param  list<array>  $lines
     * @param  list<array{id?: int|string, name: string, severity?: string, definition: array}>  $rules
     * @return list<RuleFinding>
     */
    public function evaluate(array $lines, array $rules): array
    {
        $findings = [];
        foreach ($rules as $rule) {
            $finding = $this->evaluateRule($lines, $rule);
            if ($finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Built-in completeness check from product accessory definitions.
     *
     * @param  list<array>  $lines
     * @param  array<int, list<array{accessory_product_id: int, accessory: string, quantity: float, type: string}>>  $accessories keyed by parent product id
     * @return list<RuleFinding>
     */
    public function accessoryCompleteness(array $lines, array $accessories): array
    {
        $have = [];
        foreach ($lines as $line) {
            $have[$line['product_id']] = ($have[$line['product_id']] ?? 0) + $this->qty($line, 'pull');
        }

        $needed = [];
        foreach ($lines as $line) {
            foreach ($accessories[$line['product_id']] ?? [] as $acc) {
                if (! in_array($acc['type'] ?? 'required', ['required', 'safety'], true)) {
                    continue;
                }
                $aid = $acc['accessory_product_id'];
                $needed[$aid] ??= ['name' => $acc['accessory'], 'type' => $acc['type'] ?? 'required', 'required' => 0, 'for' => []];
                $needed[$aid]['required'] += (int) ceil(round((float) $acc['quantity'] * $this->qty($line, 'pull'), 6));
                $needed[$aid]['for'][] = $line['product'];
            }
        }

        $findings = [];
        foreach ($needed as $aid => $n) {
            $found = $have[$aid] ?? 0;
            if ($found >= $n['required']) {
                continue;
            }
            $for = implode(', ', array_unique($n['for']));
            $findings[] = new RuleFinding(
                'accessory:'.$aid,
                'Required accessories',
                $n['type'] === 'safety' ? 'error' : 'warning',
                sprintf('%s needs %d × %s; %d allocated (missing %d).', $for, $n['required'], $n['name'], $found, $n['required'] - $found),
                $n['required'],
                $found,
                array_values(array_unique($n['for'])),
                [['product_id' => $aid, 'product' => $n['name'], 'quantity' => $n['required'] - $found]],
            );
        }

        return $findings;
    }

    /** @return list<string> validation errors for a definition (used by the builder) */
    public function validateDefinition(array $definition): array
    {
        $errors = [];
        $scope = $definition['scope'] ?? null;
        if (! is_array($scope) || ! in_array($scope['match'] ?? null, self::MATCHES, true)) {
            $errors[] = 'Choose which equipment the rule applies to.';
        } elseif (($scope['match'] ?? null) !== 'any' && empty($scope['values'])) {
            $errors[] = 'Pick at least one category, tag or product for the rule scope.';
        } elseif ($error = $this->selectorError($scope, 'scope')) {
            $errors[] = $error;
        }
        $check = $definition['check'] ?? null;
        if (! in_array($check, self::CHECKS, true)) {
            $errors[] = 'Choose a check type.';
        }
        $req = $definition['requirement'] ?? [];
        if (in_array($check, ['requires', 'requires_any'], true)) {
            if (! in_array($req['match'] ?? null, self::REQUIREMENT_MATCHES, true) || empty($req['values'])) {
                $errors[] = 'Choose the supporting equipment that must be present.';
            } elseif ($error = $this->selectorError($req, 'requirement')) {
                $errors[] = $error;
            }
            if ((float) ($req['per'] ?? 1) <= 0) {
                $errors[] = '"Per" must be greater than zero.';
            }
        }
        if ($check === 'max_quantity' && ! isset($req['quantity'])) {
            $errors[] = 'Enter the maximum quantity.';
        }
        if ($check === 'min_spares' && ! isset($req['ratio']) && ! isset($req['quantity'])) {
            $errors[] = 'Enter a spare ratio or minimum spare quantity.';
        }
        if (isset($req['quantity']) && (float) $req['quantity'] < 0) {
            $errors[] = 'Quantity cannot be negative.';
        }
        if (isset($req['ratio']) && ((float) $req['ratio'] < 0 || (float) $req['ratio'] > 1)) {
            $errors[] = 'Spare ratio must be between zero and one.';
        }

        return $errors;
    }

    /** Lines matched by a selector (used by the builder's "test rule" panel). */
    public function matching(array $lines, array $selector): array
    {
        return array_values(array_filter($lines, fn ($l) => $this->matches($l, $selector)));
    }

    private function selectorError(array $selector, string $where): ?string
    {
        if (isset($selector['operator']) && ! in_array($selector['operator'], self::OPERATORS, true)) {
            return "Unknown comparison in the {$where}.";
        }
        if (($selector['match'] ?? null) === 'custom_field' && ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) ($selector['field'] ?? ''))) {
            return "Choose the custom field used in the {$where}.";
        }

        return null;
    }

    private function evaluateRule(array $lines, array $rule): ?RuleFinding
    {
        $def = $rule['definition'] ?? [];
        if ($this->validateDefinition($def) !== []) {
            return null;
        }

        $basis = ($def['basis'] ?? 'pull') === 'required' ? 'required' : 'pull';
        $scopeLines = array_values(array_filter($lines, fn ($l) => $this->matches($l, $def['scope'])));
        if ($scopeLines === []) {
            return null;
        }

        $scopeQty = array_sum(array_map(fn ($l) => $this->qty($l, $basis), $scopeLines));
        $scopeNames = array_values(array_unique(array_map(fn ($l) => $l['product'], $scopeLines)));
        $req = $def['requirement'] ?? [];
        $severity = in_array($rule['severity'] ?? 'warning', self::SEVERITIES, true) ? $rule['severity'] : 'warning';

        [$required, $found, $failed, $default] = match ($def['check']) {
            'requires' => $this->checkRequires($lines, $req, $scopeQty),
            'requires_any' => $this->checkRequiresAny($lines, $req),
            'max_quantity' => [(int) $req['quantity'], $scopeQty, $scopeQty > (int) $req['quantity'], '{items}: {found} allocated, maximum is {required}.'],
            'min_spares' => $this->checkMinSpares($scopeLines, $req),
        };

        if (! $failed) {
            return null;
        }

        $message = strtr(($def['message'] ?? '') ?: $default, [
            '{required}' => (string) $required,
            '{found}' => (string) $found,
            '{missing}' => (string) max(0, $required - $found),
            '{items}' => implode(', ', $scopeNames),
            '{scope_quantity}' => (string) $scopeQty,
        ]);

        return new RuleFinding($rule['id'] ?? null, (string) ($rule['name'] ?? 'Rule'), $severity, $message, $required, $found, $scopeNames);
    }

    private function checkRequires(array $lines, array $req, int $scopeQty): array
    {
        $per = (float) ($req['per'] ?? 1);
        $quantity = (float) ($req['quantity'] ?? 1);
        $required = (int) ceil(round($scopeQty / $per, 6)) * (int) ceil($quantity);
        $found = $this->countMatching($lines, $req);

        return [$required, $found, $found < $required, '{items} need {required} supporting item(s); {found} allocated, {missing} missing.'];
    }

    private function checkRequiresAny(array $lines, array $req): array
    {
        $required = max(1, (int) ($req['quantity'] ?? 1));
        $found = $this->countMatching($lines, $req);

        return [$required, $found, $found < $required, '{items} require at least {required} supporting item(s); none found.'];
    }

    private function checkMinSpares(array $scopeLines, array $req): array
    {
        $required = 0;
        $found = 0;
        foreach ($scopeLines as $l) {
            $need = isset($req['ratio'])
                ? (int) ceil(round((float) $req['ratio'] * (int) $l['required'], 6))
                : (int) $req['quantity'];
            $required += $need;
            $found += min((int) $l['spare'], $need);
        }

        return [$required, $found, $found < $required, '{items} should carry {required} spare(s); {found} allocated.'];
    }

    private function countMatching(array $lines, array $req): int
    {
        return array_sum(array_map(
            fn ($l) => $this->matches($l, $req) ? $this->qty($l, 'pull') : 0,
            $lines,
        ));
    }

    private function matches(array $line, array $selector): bool
    {
        $values = array_map('strval', (array) ($selector['values'] ?? []));

        return match ($selector['match'] ?? null) {
            'any' => true,
            'product' => in_array((string) $line['product_id'], $values, true),
            'category' => array_intersect(array_map('strval', $line['categories'] ?? []), $values) !== [],
            'tag' => array_intersect(array_map(fn ($t) => mb_strtolower((string) $t), $line['tags'] ?? []), array_map('mb_strtolower', $values)) !== [],
            'name' => $this->textMatches((string) ($line['product'] ?? ''), $values, $selector['operator'] ?? 'contains'),
            'custom_field' => $this->textMatches($line['custom'][(string) ($selector['field'] ?? '')] ?? null, $values, $selector['operator'] ?? 'is'),
            default => false,
        };
    }

    /** Case-insensitive "is"/"contains" comparison; list values (multi-select) match if any element matches. */
    private function textMatches(mixed $actual, array $values, string $operator): bool
    {
        if ($actual === null || $actual === '' || $values === []) {
            return false;
        }
        $candidates = is_array($actual) ? $actual : [$actual];
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                continue;
            }
            $candidate = mb_strtolower(is_bool($candidate) ? ($candidate ? 'yes' : 'no') : (string) $candidate);
            foreach ($values as $value) {
                $value = mb_strtolower(trim($value));
                if ($value === '') {
                    continue;
                }
                if ($operator === 'contains' ? str_contains($candidate, $value) : $candidate === $value) {
                    return true;
                }
            }
        }

        return false;
    }

    private function qty(array $line, string $basis): int
    {
        return $basis === 'required' ? (int) ($line['required'] ?? 0) : (int) ($line['required'] ?? 0) + (int) ($line['spare'] ?? 0);
    }
}
