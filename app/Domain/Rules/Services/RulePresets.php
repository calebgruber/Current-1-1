<?php

namespace App\Domain\Rules\Services;

/** Starter rules offered in the no-code builder. Tag/name selectors avoid hard-coded IDs. */
final class RulePresets
{
    public static function all(): array
    {
        return [
            [
                'key' => 'moving_lights_powercon',
                'name' => 'Moving lights need PowerCON cables',
                'description' => 'Every moving light needs a PowerCON power jumper.',
                'severity' => 'error',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['moving-light', 'moving light']],
                    'check' => 'requires',
                    'requirement' => ['match' => 'name', 'operator' => 'contains', 'values' => ['powercon'], 'per' => 1, 'quantity' => 1],
                    'basis' => 'pull',
                    'message' => '{items}: {required} PowerCON cable(s) needed, {found} allocated ({missing} missing).',
                ],
            ],
            [
                'key' => 'dante_switch',
                'name' => 'Dante devices need a network switch',
                'description' => 'Any Dante device on a job needs at least one network switch.',
                'severity' => 'warning',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['dante']],
                    'check' => 'requires_any',
                    'requirement' => ['match' => 'name', 'operator' => 'contains', 'values' => ['switch'], 'per' => 1, 'quantity' => 1],
                    'basis' => 'pull',
                    'message' => '{items} need a network switch; none allocated.',
                ],
            ],
            [
                'key' => 'wireless_batteries',
                'name' => 'Wireless mics need batteries (2 per mic)',
                'description' => 'Two AA batteries for every wireless microphone or beltpack.',
                'severity' => 'warning',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['wireless-mic', 'wireless mic']],
                    'check' => 'requires',
                    'requirement' => ['match' => 'name', 'operator' => 'contains', 'values' => ['battery'], 'per' => 1, 'quantity' => 2],
                    'basis' => 'pull',
                    'message' => '{items} need {required} batteries; found {found} ({missing} missing).',
                ],
            ],
            [
                'key' => 'hanging_clamps',
                'name' => 'Hanging fixtures need clamps',
                'description' => 'One hanging clamp per rigged fixture.',
                'severity' => 'error',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['hanging', 'rigged']],
                    'check' => 'requires',
                    'requirement' => ['match' => 'name', 'operator' => 'contains', 'values' => ['clamp'], 'per' => 1, 'quantity' => 1],
                    'basis' => 'pull',
                    'message' => '{items}: {required} clamp(s) needed, {found} allocated.',
                ],
            ],
            [
                'key' => 'hanging_safeties',
                'name' => 'Hanging fixtures need safety cables',
                'description' => 'One safety cable per rigged fixture.',
                'severity' => 'error',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['hanging', 'rigged']],
                    'check' => 'requires',
                    'requirement' => ['match' => 'name', 'operator' => 'contains', 'values' => ['safety'], 'per' => 1, 'quantity' => 1],
                    'basis' => 'pull',
                    'message' => '{items}: {required} safety cable(s) needed, {found} allocated.',
                ],
            ],
        ];
    }
}
