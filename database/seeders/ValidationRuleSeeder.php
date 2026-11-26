<?php

namespace Database\Seeders;

use App\Domain\Rules\Models\ValidationRule;
use Illuminate\Database\Seeder;

/**
 * Example dependency rules. They match product tags, so they activate as soon
 * as products are tagged (e.g. "moving-light", "dante", "wireless-mic").
 */
class ValidationRuleSeeder extends Seeder
{
    /** @return list<array{name: string, description: string, severity: string, definition: array}> */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'Moving lights need PowerCON cabling',
                'description' => 'Every moving light should have a PowerCON power cable allocated.',
                'severity' => 'error',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['moving-light']],
                    'check' => 'requires',
                    'requirement' => ['match' => 'tag', 'values' => ['powercon'], 'per' => 1, 'quantity' => 1],
                    'basis' => 'pull',
                    'message' => '{items} need {required} PowerCON cable(s); {found} allocated, {missing} missing.',
                ],
            ],
            [
                'name' => 'Dante devices need network infrastructure',
                'description' => 'Jobs with Dante devices must include a network switch.',
                'severity' => 'error',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['dante']],
                    'check' => 'requires_any',
                    'requirement' => ['match' => 'tag', 'values' => ['network-switch'], 'quantity' => 1],
                    'basis' => 'pull',
                    'message' => '{items} need a network switch; none allocated.',
                ],
            ],
            [
                'name' => 'Wireless microphones need batteries',
                'description' => 'Two AA batteries per wireless transmitter.',
                'severity' => 'warning',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['wireless-mic']],
                    'check' => 'requires',
                    'requirement' => ['match' => 'tag', 'values' => ['battery-aa'], 'per' => 1, 'quantity' => 2],
                    'basis' => 'pull',
                    'message' => '{items} need {required} batteries; {found} allocated.',
                ],
            ],
            [
                'name' => 'Hanging fixtures need mounting hardware',
                'description' => 'Each hung fixture needs a clamp and a safety cable.',
                'severity' => 'error',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['hanging-fixture']],
                    'check' => 'requires',
                    'requirement' => ['match' => 'tag', 'values' => ['safety-cable'], 'per' => 1, 'quantity' => 1],
                    'basis' => 'pull',
                    'message' => '{items} need {required} safety cable(s); {missing} missing.',
                ],
            ],
            [
                'name' => 'Carry 10% spares on lighting fixtures',
                'description' => 'Recommend backup units for productions.',
                'severity' => 'info',
                'definition' => [
                    'scope' => ['match' => 'tag', 'values' => ['hanging-fixture']],
                    'check' => 'min_spares',
                    'requirement' => ['ratio' => 0.1],
                    'basis' => 'required',
                    'message' => '{items} should carry {required} spare(s); {found} allocated.',
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $rule) {
            ValidationRule::query()->firstOrCreate(['name' => $rule['name']], $rule + ['is_active' => true]);
        }
    }
}
