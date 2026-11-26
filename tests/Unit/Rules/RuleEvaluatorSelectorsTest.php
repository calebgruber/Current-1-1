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

    public function test_numeric_custom_fields_use_legacy_compact_decimal_matching_only_for_numeric_types(): void
    {
        $evaluator = new RuleEvaluator;
        $numeric = $this->line(1, 'Fixture', 1, ['watts' => '1.0000', 'cost' => '10.5000', 'precise' => '99999999999999.9999', 'code' => '1.0000']);
        $numeric['custom_types'] = ['watts' => 'number', 'cost' => 'currency', 'precise' => 'number'];
        $selector = fn ($field, $value, $operator = 'is') => ['match' => 'custom_field', 'field' => $field, 'operator' => $operator, 'values' => [$value]];

        $this->assertCount(1, $evaluator->matching([$numeric], $selector('watts', '1')));
        $this->assertCount(1, $evaluator->matching([$numeric], $selector('cost', '10.5')));
        $this->assertCount(1, $evaluator->matching([$numeric], $selector('cost', '0.5', 'contains')));
        $this->assertCount(1, $evaluator->matching([$numeric], $selector('precise', '99999999999999.9999')));
        $this->assertCount(0, $evaluator->matching([$numeric], $selector('precise', '100000000000000')));
        $this->assertCount(0, $evaluator->matching([$numeric], $selector('watts', '1.0000')));
        $this->assertCount(0, $evaluator->matching([$numeric], $selector('code', '1')));
        $this->assertCount(1, $evaluator->matching([$numeric], $selector('code', '1.0000')));
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
