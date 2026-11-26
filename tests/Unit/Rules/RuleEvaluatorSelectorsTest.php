<?php

namespace Tests\Unit\Rules;

use App\Domain\Rules\Services\RuleEvaluator;
use PHPUnit\Framework\TestCase;

class RuleEvaluatorSelectorsTest extends TestCase
{
    private function line(int $id, string $name, int $required, array $custom = [], array $tags = []): array
    {
        return ['product_id' => $id, 'product' => $name, 'categories' => [], 'tags' => $tags, 'custom' => $custom, 'required' => $required, 'spare' => 0];
    }

    public function test_custom_field_scope_and_name_requirement(): void
    {
        $evaluator = new RuleEvaluator;
        $rule = ['id' => 1, 'name' => 'Batteries', 'severity' => 'warning', 'definition' => [
            'scope' => ['match' => 'custom_field', 'field' => 'power_source', 'operator' => 'is', 'values' => ['Battery']],
            'check' => 'requires',
            'requirement' => ['match' => 'name', 'operator' => 'contains', 'values' => ['AA'], 'per' => 1, 'quantity' => 2],
        ]];
        $lines = [
            $this->line(1, 'Shure ULXD2', 4, ['power_source' => 'battery']),
            $this->line(2, 'Wired SM58', 6, ['power_source' => 'none']),
            $this->line(3, 'AA lithium battery', 5),
        ];

        $findings = $evaluator->evaluate($lines, [$rule]);

        $this->assertCount(1, $findings);
        $this->assertSame(8, $findings[0]->required);
        $this->assertSame(5, $findings[0]->found);
        $this->assertSame(['Shure ULXD2'], $findings[0]->items);
    }

    public function test_custom_field_contains_matches_multi_select_values(): void
    {
        $evaluator = new RuleEvaluator;
        $lines = [$this->line(1, 'Fixture', 1, ['features' => ['DMX', 'RDM']])];

        $this->assertCount(1, $evaluator->matching($lines, ['match' => 'custom_field', 'field' => 'features', 'operator' => 'contains', 'values' => ['rd']]));
        $this->assertCount(0, $evaluator->matching($lines, ['match' => 'custom_field', 'field' => 'features', 'operator' => 'is', 'values' => ['rd']]));
    }

    public function test_validate_definition_rejects_bad_custom_field_selector(): void
    {
        $evaluator = new RuleEvaluator;
        $errors = $evaluator->validateDefinition([
            'scope' => ['match' => 'custom_field', 'field' => 'Bad Key!', 'values' => ['x']],
            'check' => 'requires_any',
            'requirement' => ['match' => 'name', 'operator' => 'regex', 'values' => ['x']],
        ]);

        $this->assertCount(2, $errors);
    }
}
