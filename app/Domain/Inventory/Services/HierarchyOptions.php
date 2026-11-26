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
        return $this->flatten(Category::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->get(['id', 'parent_id', 'name']));
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
        $out = [$id => true];
        $queue = [$id];
        for ($i = 0; $i < count($queue); $i++) {
            $current = $queue[$i];
            foreach ($byParent->get($current, collect()) as $child) {
                if (! isset($out[$child->id])) {
                    $out[$child->id] = true;
                    $queue[] = $child->id;
                }
            }
        }

        return array_keys($out);
    }

    /** Whether making $parentId the parent of $id would create a cycle. */
    public function wouldCycle(string $modelClass, int $id, ?int $parentId): bool
    {
        return $parentId !== null && in_array($parentId, $this->descendantIds($modelClass, $id), true);
    }

    private function flatten(Collection $rows, ?callable $extra = null): array
    {
        $byParent = $rows->groupBy(fn ($r) => $r->parent_id ?? 0);
        $ids = array_fill_keys($rows->pluck('id')->all(), true);
        $out = [];
        $visited = [];
        $walk = function ($roots) use (&$out, &$visited, $byParent, $extra) {
            $stack = [];
            foreach ($roots->reverse() as $row) {
                $stack[] = [$row, '', 0];
            }
            while ($stack) {
                [$row, $prefix, $depth] = array_pop($stack);
                if (isset($visited[$row->id])) {
                    continue;
                }
                $visited[$row->id] = true;
                $label = $prefix === '' ? $row->name : $prefix.' › '.$row->name;
                $out[] = ['value' => $row->id, 'label' => $label, 'depth' => $depth] + ($extra ? $extra($row) : []);
                foreach ($byParent->get($row->id, collect())->reverse() as $child) {
                    $stack[] = [$child, $label, $depth + 1];
                }
            }
        };
        $walk($byParent->get(0, collect()));

        // Orphans (parent missing) are still listed at the root.
        $walk($rows->filter(fn ($row) => $row->parent_id && ! isset($ids[$row->parent_id])));
        // Keep corrupt cyclic components visible without looping indefinitely.
        $walk($rows->filter(fn ($row) => ! isset($visited[$row->id])));

        return $out;
    }
}
