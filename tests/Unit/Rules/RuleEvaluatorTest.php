<?php

namespace Tests\Unit\Rules;

use App\Domain\Rules\DTOs\RuleFinding;
use App\Domain\Rules\Services\RuleEvaluator;
use PHPUnit\Framework\TestCase;

class RuleEvaluatorTest extends TestCase
{
    private RuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new RuleEvaluator;
    }

    private function line(int $id, string $name, int $required, int $spare = 0, array $categories = [], array $tags = []): array
    {
        return ['product_id' => $id, 'product' => $name, 'categories' => $categories, 'tags' => $tags, 'required' => $required, 'spare' => $spare];
    }

    private function rule(array $definition, string $severity = 'warning', string $name = 'Rule', $id = 1): array
    {
        return ['id' => $id, 'name' => $name, 'severity' => $severity, 'definition' => $definition];
    }

    private function movingLightsNeedPowercon(array $extra = []): array
    {
        return $this->rule($extra + [
            'scope' => ['match' => 'category', 'values' => [5]],
            'check' => 'requires',
            'requirement' => ['match' => 'tag', 'values' => ['PowerCON'], 'per' => 1, 'quantity' => 1],
        ], 'error', 'PowerCON cables');
    }

    public function test_requires_passes_when_enough_supporting_items(): void
    {
        $lines = [
            $this->line(1, 'Spot', 10, 2, [5, 2]),
            $this->line(2, 'PowerCON 5m', 12, 0, [9], ['powercon']),
        ];
        $this->assertSame([], $this->evaluator->evaluate($lines, [$this->movingLightsNeedPowercon()]));
    }

    public function test_requires_fails_and_counts_spares_by_default(): void
    {
        $lines = [
            $this->line(1, 'Spot', 10, 2, [5]),
            $this->line(2, 'PowerCON 5m', 10, 0, [9], ['PowerCON']),
        ];
        $findings = $this->evaluator->evaluate($lines, [$this->movingLightsNeedPowercon()]);

        $this->assertCount(1, $findings);
        $f = $findings[0];
        $this->assertInstanceOf(RuleFinding::class, $f);
        $this->assertSame(1, $f->ruleId);
        $this->assertSame('PowerCON cables', $f->rule);
        $this->assertSame('error', $f->severity);
        $this->assertSame(12, $f->required);
        $this->assertSame(10, $f->found);
        $this->assertSame(2, $f->missing());
        $this->assertSame(['Spot'], $f->items);
        $this->assertSame('Spot need 12 supporting item(s); 10 allocated, 2 missing.', $f->message);
        $this->assertSame(2, $f->toArray()['missing']);
    }

    public function test_requires_with_required_basis_ignores_spares(): void
    {
        $lines = [
            $this->line(1, 'Spot', 10, 2, [5]),
            $this->line(2, 'PowerCON 5m', 10, 0, [9], ['powercon']),
        ];
        $this->assertSame([], $this->evaluator->evaluate($lines, [$this->movingLightsNeedPowercon(['basis' => 'required'])]));
    }

    public function test_requires_per_and_quantity(): void
    {
        // 1 safety bond per 4 fixtures, 2 each → ceil(9/4)=3 × 2 = 6
        $rule = $this->rule([
            'scope' => ['match' => 'product', 'values' => [1]],
            'check' => 'requires',
            'requirement' => ['match' => 'product', 'values' => [3], 'per' => 4, 'quantity' => 2],
        ]);
        $findings = $this->evaluator->evaluate([$this->line(1, 'Par', 9), $this->line(3, 'Bond', 5)], [$rule]);

        $this->assertSame(6, $findings[0]->required);
        $this->assertSame(5, $findings[0]->found);

        $this->assertSame([], $this->evaluator->evaluate([$this->line(1, 'Par', 9), $this->line(3, 'Bond', 6)], [$rule]));
    }

    public function test_rule_skipped_when_no_lines_in_scope(): void
    {
        $this->assertSame([], $this->evaluator->evaluate([$this->line(7, 'Cable', 3, 0, [9])], [$this->movingLightsNeedPowercon()]));
    }

    public function test_requires_any(): void
    {
        $rule = $this->rule([
            'scope' => ['match' => 'tag', 'values' => ['hazer']],
            'check' => 'requires_any',
            'requirement' => ['match' => 'category', 'values' => [20]],
        ]);
        $hazer = $this->line(1, 'Hazer', 1, 0, [], ['Hazer']);

        $findings = $this->evaluator->evaluate([$hazer], [$rule]);
        $this->assertCount(1, $findings);
        $this->assertSame(1, $findings[0]->required);
        $this->assertSame(0, $findings[0]->found);
        $this->assertSame('warning', $findings[0]->severity);
        $this->assertSame('Hazer require at least 1 supporting item(s); none found.', $findings[0]->message);

        $this->assertSame([], $this->evaluator->evaluate([$hazer, $this->line(2, 'Fluid', 1, 0, [20, 1])], [$rule]));
    }

    public function test_requires_any_with_minimum_quantity(): void
    {
        $rule = $this->rule([
            'scope' => ['match' => 'any'],
            'check' => 'requires_any',
            'requirement' => ['match' => 'product', 'values' => ['9'], 'quantity' => 3],
        ]);
        $findings = $this->evaluator->evaluate([$this->line(1, 'X', 1), $this->line(9, 'Spare kit', 2)], [$rule]);
        $this->assertSame(3, $findings[0]->required);
        $this->assertSame(2, $findings[0]->found);
    }

    public function test_max_quantity(): void
    {
        $rule = $this->rule([
            'scope' => ['match' => 'product', 'values' => [1, 2]],
            'check' => 'max_quantity',
            'requirement' => ['quantity' => 10],
        ], 'info');

        $lines = [$this->line(1, 'A', 6, 1), $this->line(2, 'B', 4)];
        $findings = $this->evaluator->evaluate($lines, [$rule]);
        $this->assertCount(1, $findings);
        $this->assertSame('info', $findings[0]->severity);
        $this->assertSame(10, $findings[0]->required);
        $this->assertSame(11, $findings[0]->found);
        $this->assertSame('A, B: 11 allocated, maximum is 10.', $findings[0]->message);

        $this->assertSame([], $this->evaluator->evaluate([$this->line(1, 'A', 10)], [$rule]));
    }

    public function test_min_spares_ratio(): void
    {
        $rule = $this->rule([
            'scope' => ['match' => 'category', 'values' => [5]],
            'check' => 'min_spares',
            'requirement' => ['ratio' => 0.1],
        ]);

        // 20 × 0.1 = 2 spares needed, 1 carried
        $findings = $this->evaluator->evaluate([$this->line(1, 'Lustr', 20, 1, [5])], [$rule]);
        $this->assertSame(2, $findings[0]->required);
        $this->assertSame(1, $findings[0]->found);
        $this->assertSame('Lustr should carry 2 spare(s); 1 allocated.', $findings[0]->message);

        // Excess spares on one line do not compensate for another line.
        $findings = $this->evaluator->evaluate([
            $this->line(1, 'Lustr', 20, 5, [5]),
            $this->line(2, 'Spot', 10, 0, [5]),
        ], [$rule]);
        $this->assertSame(3, $findings[0]->required);
        $this->assertSame(2, $findings[0]->found);

        $this->assertSame([], $this->evaluator->evaluate([$this->line(1, 'Lustr', 20, 5, [5])], [$rule]));
    }

    public function test_min_spares_fixed_quantity(): void
    {
        $rule = $this->rule([
            'scope' => ['match' => 'any'],
            'check' => 'min_spares',
            'requirement' => ['quantity' => 2],
        ]);
        $findings = $this->evaluator->evaluate([$this->line(1, 'A', 3, 1), $this->line(2, 'B', 1, 2)], [$rule]);
        $this->assertSame(4, $findings[0]->required);
        $this->assertSame(3, $findings[0]->found);
    }

    public function test_custom_message_placeholders(): void
    {
        $rule = $this->movingLightsNeedPowercon([
            'message' => 'Items: {items}; need {required}, have {found}, missing {missing}, scope {scope_quantity}.',
        ]);
        $lines = [
            $this->line(1, 'Spot', 4, 1, [5]),
            $this->line(4, 'Wash', 3, 0, [5]),
            $this->line(2, 'PowerCON', 2, 0, [], ['powercon']),
        ];
        $findings = $this->evaluator->evaluate($lines, [$rule]);
        $this->assertSame('Items: Spot, Wash; need 8, have 2, missing 6, scope 8.', $findings[0]->message);
    }

    public function test_unknown_severity_falls_back_to_warning(): void
    {
        $rule = $this->movingLightsNeedPowercon();
        $rule['severity'] = 'catastrophic';
        $findings = $this->evaluator->evaluate([$this->line(1, 'Spot', 1, 0, [5])], [$rule]);
        $this->assertSame('warning', $findings[0]->severity);
    }

    public function test_tag_matching_is_case_insensitive(): void
    {
        $rule = $this->rule([
            'scope' => ['match' => 'tag', 'values' => ['MOVING']],
            'check' => 'max_quantity',
            'requirement' => ['quantity' => 0],
        ]);
        $this->assertCount(1, $this->evaluator->evaluate([$this->line(1, 'Spot', 1, 0, [], ['moving'])], [$rule]));
    }

    public function test_invalid_definitions_are_skipped(): void
    {
        $rules = [
            $this->rule(['scope' => ['match' => 'any'], 'check' => 'explode']),
            $this->rule(['scope' => ['match' => 'any'], 'check' => 'max_quantity']),
            $this->rule([]),
        ];
        $this->assertSame([], $this->evaluator->evaluate([$this->line(1, 'A', 100)], $rules));
    }

    public function test_validate_definition_valid(): void
    {
        $this->assertSame([], $this->evaluator->validateDefinition([
            'scope' => ['match' => 'category', 'values' => [1]],
            'check' => 'requires',
            'requirement' => ['match' => 'tag', 'values' => ['x'], 'per' => 2],
        ]));
        $this->assertSame([], $this->evaluator->validateDefinition([
            'scope' => ['match' => 'any'], 'check' => 'max_quantity', 'requirement' => ['quantity' => 0],
        ]));
        $this->assertSame([], $this->evaluator->validateDefinition([
            'scope' => ['match' => 'any'], 'check' => 'min_spares', 'requirement' => ['ratio' => 0.1],
        ]));
    }

    public function test_validate_definition_errors(): void
    {
        $this->assertSame(['Choose which equipment the rule applies to.', 'Choose a check type.'], $this->evaluator->validateDefinition([]));

        $this->assertContains('Pick at least one category, tag or product for the rule scope.', $this->evaluator->validateDefinition([
            'scope' => ['match' => 'category', 'values' => []], 'check' => 'max_quantity', 'requirement' => ['quantity' => 1],
        ]));

        $errors = $this->evaluator->validateDefinition([
            'scope' => ['match' => 'any'], 'check' => 'requires', 'requirement' => ['match' => 'any', 'values' => [1], 'per' => 0],
        ]);
        $this->assertSame(['Choose the supporting equipment that must be present.', '"Per" must be greater than zero.'], $errors);

        $this->assertSame(['Enter the maximum quantity.'], $this->evaluator->validateDefinition([
            'scope' => ['match' => 'any'], 'check' => 'max_quantity', 'requirement' => [],
        ]));
        $this->assertSame(['Enter a spare ratio or minimum spare quantity.'], $this->evaluator->validateDefinition([
            'scope' => ['match' => 'any'], 'check' => 'min_spares',
        ]));
        $this->assertSame(['Choose which equipment the rule applies to.'], $this->evaluator->validateDefinition([
            'scope' => ['match' => 'galaxy', 'values' => [1]], 'check' => 'max_quantity', 'requirement' => ['quantity' => 1],
        ]));
    }

    public function test_accessory_completeness_missing_required_accessory(): void
    {
        $lines = [
            $this->line(10, 'Lustr', 20, 5),
            $this->line(11, 'Clamp', 20, 0),
        ];
        $accessories = [10 => [
            ['accessory_product_id' => 11, 'accessory' => 'Clamp', 'quantity' => 1, 'type' => 'required'],
            ['accessory_product_id' => 12, 'accessory' => 'Safety cable', 'quantity' => 1, 'type' => 'safety'],
            ['accessory_product_id' => 13, 'accessory' => 'Gel frame', 'quantity' => 1, 'type' => 'optional'],
        ]];

        $findings = $this->evaluator->accessoryCompleteness($lines, $accessories);
        $this->assertCount(2, $findings);

        [$clamp, $safety] = $findings;
        $this->assertSame('accessory:11', $clamp->ruleId);
        $this->assertSame('Required accessories', $clamp->rule);
        $this->assertSame('warning', $clamp->severity);
        $this->assertSame(25, $clamp->required);
        $this->assertSame(20, $clamp->found);
        $this->assertSame('Lustr needs 25 × Clamp; 20 allocated (missing 5).', $clamp->message);
        $this->assertSame([['product_id' => 11, 'product' => 'Clamp', 'quantity' => 5]], $clamp->suggestions);

        $this->assertSame('error', $safety->severity);
        $this->assertSame(25, $safety->required);
        $this->assertSame(0, $safety->found);
    }

    public function test_accessory_completeness_aggregates_parents_and_passes_when_complete(): void
    {
        $accessories = [
            10 => [['accessory_product_id' => 11, 'accessory' => 'Clamp', 'quantity' => 1, 'type' => 'required']],
            20 => [['accessory_product_id' => 11, 'accessory' => 'Clamp', 'quantity' => 0.5, 'type' => 'required']],
        ];
        $lines = [$this->line(10, 'Lustr', 4), $this->line(20, 'Par', 3), $this->line(11, 'Clamp', 5)];

        // 4 + ceil(1.5) = 6 required
        $findings = $this->evaluator->accessoryCompleteness($lines, $accessories);
        $this->assertSame(6, $findings[0]->required);
        $this->assertSame(['Lustr', 'Par'], $findings[0]->items);

        $lines[2] = $this->line(11, 'Clamp', 6);
        $this->assertSame([], $this->evaluator->accessoryCompleteness($lines, $accessories));
    }
}
