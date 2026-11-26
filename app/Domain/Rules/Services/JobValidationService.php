<?php

namespace App\Domain\Rules\Services;

use App\Domain\Inventory\Models\Category;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Rules\DTOs\RuleFinding;
use App\Domain\Rules\Models\ValidationRule;

/** Loads a job's allocations and active rules and runs the RuleEvaluator. */
class JobValidationService
{
    public function __construct(private readonly RuleEvaluator $evaluator) {}

    /** @return list<RuleFinding> */
    public function validate(ProductionJob $job, ?array $rules = null): array
    {
        $items = $job->items()->with(['product.tags', 'product.accessories.accessory', 'product.customFieldValues.definition'])->get();
        $lines = $this->lines($items);

        $accessories = [];
        foreach ($items->pluck('product')->filter()->unique('id') as $product) {
            $accessories[$product->id] = $product->accessories->filter(fn ($a) => $a->accessory)->map(fn ($a) => [
                'accessory_product_id' => $a->accessory_product_id,
                'accessory' => $a->accessory->name,
                'quantity' => (float) $a->quantity,
                'type' => $a->type,
            ])->values()->all();
        }

        $rules ??= ValidationRule::query()->where('is_active', true)->get()
            ->map(fn (ValidationRule $r) => ['id' => $r->id, 'name' => $r->name, 'severity' => $r->severity, 'definition' => $r->definition])
            ->all();

        $findings = array_merge(
            $this->evaluator->accessoryCompleteness($lines, $accessories),
            $this->evaluator->evaluate($lines, $rules),
        );

        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($findings, fn (RuleFinding $a, RuleFinding $b) => $order[$a->severity] <=> $order[$b->severity]);

        return $findings;
    }

    /** @return list<array> */
    public function lines($items): array
    {
        $ancestors = $this->categoryAncestors();

        return $items->filter(fn ($i) => $i->product)->map(fn ($i) => [
            'product_id' => $i->product_id,
            'product' => $i->product->name,
            'categories' => $i->product->category_id ? ($ancestors[$i->product->category_id] ?? [$i->product->category_id]) : [],
            'tags' => $i->product->tags->flatMap(fn ($t) => [$t->name, $t->slug])->all(),
            'custom' => $i->product->customFields(),
            'required' => (int) $i->required_quantity,
            'spare' => (int) $i->spare_quantity,
        ])->values()->all();
    }

    /** @return array<int, list<int>> category id => [self, parent, grandparent...] */
    private function categoryAncestors(): array
    {
        $parents = Category::query()->pluck('parent_id', 'id')->all();
        $map = [];
        foreach ($parents as $id => $_) {
            $chain = [];
            $cursor = $id;
            while ($cursor && ! in_array($cursor, $chain, true) && count($chain) < 32) {
                $chain[] = $cursor;
                $cursor = $parents[$cursor] ?? null;
            }
            $map[$id] = $chain;
        }

        return $map;
    }
}
