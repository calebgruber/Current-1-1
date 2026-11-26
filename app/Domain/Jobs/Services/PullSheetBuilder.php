<?php

namespace App\Domain\Jobs\Services;

/**
 * Pure pull-sheet assembly. Input lines are plain arrays so the builder can be
 * unit tested and reused by documents, warehouse screens and reports.
 *
 * Line shape: [id, parent_id, section, product_id, product, sku, location,
 *   required, spare, picked, checked_out, returned, weight_kg, assets => list]
 */
final class PullSheetBuilder
{
    /**
     * Quantity of an accessory needed for a parent allocation. Required and
     * spare units are computed independently so that spare fixtures bring
     * their own spare accessories (5 spare Lustrs → 5 spare clamps).
     *
     * @return array{required: int, spare: int, pull: int}
     */
    public static function accessoryQuantities(float $perUnit, int $required, int $spare): array
    {
        $r = (int) ceil(round($perUnit * $required, 6));
        $s = (int) ceil(round($perUnit * $spare, 6));

        return ['required' => $r, 'spare' => $s, 'pull' => $r + $s];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{sections: list<array{name: string, lines: list<array>}>, totals: array<string, float|int>, by_product: list<array>}
     */
    public function build(array $lines): array
    {
        $normalised = array_map(fn (array $l) => $this->normalise($l), $lines);
        $children = [];
        $roots = [];
        foreach ($normalised as $line) {
            if ($line['parent_id']) {
                $children[$line['parent_id']][] = $line;
            } else {
                $roots[] = $line;
            }
        }

        $sections = [];
        foreach ($roots as $line) {
            $line['children'] = $children[$line['id']] ?? [];
            $sections[$line['section'] ?: 'General'][] = $line;
        }

        // Orphans (parent removed) are listed as top-level lines.
        $rootIds = array_column($roots, 'id');
        foreach ($children as $parentId => $orphans) {
            if (! in_array($parentId, $rootIds, true)) {
                foreach ($orphans as $line) {
                    $line['children'] = [];
                    $sections[$line['section'] ?: 'General'][] = $line;
                }
            }
        }

        $totals = ['required' => 0, 'spare' => 0, 'pull' => 0, 'picked' => 0, 'checked_out' => 0, 'returned' => 0, 'outstanding' => 0, 'weight_kg' => 0.0, 'lines' => count($normalised)];
        $byProduct = [];
        foreach ($normalised as $line) {
            foreach (['required', 'spare', 'pull', 'picked', 'checked_out', 'returned', 'outstanding'] as $k) {
                $totals[$k] += $line[$k];
            }
            $totals['weight_kg'] += $line['pull'] * $line['weight_kg'];

            $pid = $line['product_id'];
            $byProduct[$pid] ??= ['product_id' => $pid, 'product' => $line['product'], 'sku' => $line['sku'], 'location' => $line['location'], 'required' => 0, 'spare' => 0, 'pull' => 0, 'picked' => 0];
            foreach (['required', 'spare', 'pull', 'picked'] as $k) {
                $byProduct[$pid][$k] += $line[$k];
            }
        }
        $totals['weight_kg'] = round($totals['weight_kg'], 2);

        usort($byProduct, fn ($a, $b) => [$a['location'] ?? '', $a['product']] <=> [$b['location'] ?? '', $b['product']]);

        return [
            'sections' => array_map(fn ($name, $ls) => ['name' => $name, 'lines' => $ls], array_keys($sections), array_values($sections)),
            'totals' => $totals,
            'by_product' => array_values($byProduct),
        ];
    }

    private function normalise(array $l): array
    {
        $required = (int) ($l['required'] ?? 0);
        $spare = (int) ($l['spare'] ?? 0);
        $checkedOut = (int) ($l['checked_out'] ?? 0);
        $returned = (int) ($l['returned'] ?? 0);

        return [
            'id' => $l['id'] ?? null,
            'parent_id' => $l['parent_id'] ?? null,
            'section' => $l['section'] ?? null,
            'product_id' => $l['product_id'] ?? null,
            'product' => (string) ($l['product'] ?? ''),
            'sku' => $l['sku'] ?? null,
            'location' => $l['location'] ?? null,
            'required' => $required,
            'spare' => $spare,
            'pull' => $required + $spare,
            'picked' => (int) ($l['picked'] ?? 0),
            'checked_out' => $checkedOut,
            'returned' => $returned,
            'outstanding' => max(0, $checkedOut - $returned),
            'remaining_to_pick' => max(0, $required + $spare - (int) ($l['picked'] ?? 0)),
            'weight_kg' => (float) ($l['weight_kg'] ?? 0),
            'notes' => $l['notes'] ?? null,
            'assets' => $l['assets'] ?? [],
        ];
    }
}
