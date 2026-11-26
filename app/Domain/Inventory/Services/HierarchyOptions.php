<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Manufacturer;
use Illuminate\Support\Collection;

/**
 * Builds option lists and descendant lookups for the category and location
 * trees in memory (trees are small), avoiding recursive queries.
 */
class HierarchyOptions
{
    /** @return list<array{value: int, label: string, depth: int}> */
    public function categories(): array
    {
        return $this->flatten(Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name']));
    }

    /** @return list<array{value: int, label: string, depth: int, type: string}> */
    public function locations(bool $activeOnly = false): array
    {
        $rows = Location::query()->when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('name')->get(['id', 'parent_id', 'name', 'type']);

        return $this->flatten($rows, fn ($row) => ['type' => $row->type]);
    }

    /** @return list<array{value: int, label: string}> */
    public function manufacturers(): array
    {
        return Manufacturer::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->all();
    }

    /** @return list<int> the id itself plus every descendant id */
    public function descendantIds(string $modelClass, int $id): array
    {
        $byParent = $modelClass::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $out = [$id];
        $queue = [$id];
        $guard = 0;
        while ($queue && $guard++ < 10000) {
            $current = array_shift($queue);
            foreach ($byParent->get($current, collect()) as $child) {
                if (! in_array($child->id, $out, true)) {
                    $out[] = $child->id;
                    $queue[] = $child->id;
                }
            }
        }

        return $out;
    }

    /** Whether making $parentId the parent of $id would create a cycle. */
    public function wouldCycle(string $modelClass, int $id, ?int $parentId): bool
    {
        return $parentId !== null && in_array($parentId, $this->descendantIds($modelClass, $id), true);
    }

    private function flatten(Collection $rows, ?callable $extra = null): array
    {
        $byParent = $rows->groupBy(fn ($r) => $r->parent_id ?? 0);
        $ids = $rows->pluck('id')->all();
        $out = [];
        $walk = function ($parentId, string $prefix, int $depth) use (&$walk, &$out, $byParent, $extra) {
            foreach ($byParent->get($parentId, collect()) as $row) {
                $label = $prefix === '' ? $row->name : $prefix.' › '.$row->name;
                $out[] = ['value' => $row->id, 'label' => $label, 'depth' => $depth] + ($extra ? $extra($row) : []);
                if ($depth < 16) {
                    $walk($row->id, $label, $depth + 1);
                }
            }
        };
        $walk(0, '', 0);

        // Orphans (parent missing) are still listed at the root.
        foreach ($rows as $row) {
            if ($row->parent_id && ! in_array($row->parent_id, $ids, true)) {
                $out[] = ['value' => $row->id, 'label' => $row->name, 'depth' => 0] + ($extra ? $extra($row) : []);
            }
        }

        return $out;
    }
}
