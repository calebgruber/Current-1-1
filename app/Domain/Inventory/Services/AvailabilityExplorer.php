<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Enums\TrackingType;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockLevel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Assembles the Availability Explorer payload on top of AvailabilityService:
 * the breakdown with drill-down sources for every number, a day-by-day
 * timeline and the per-location distribution of physical units.
 */
class AvailabilityExplorer
{
    public const MAX_DAYS = 180;

    private const BUCKETS = ['reserved', 'checked_out', 'tentative', 'maintenance', 'damaged', 'lost', 'retired'];

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly HierarchyOptions $hierarchy,
    ) {}

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function range(?string $from, ?string $to): array
    {
        $start = $from ? CarbonImmutable::parse($from) : CarbonImmutable::now();
        $end = $to ? CarbonImmutable::parse($to) : $start->addDays(30);
        if ($to && strlen($to) <= 10) {
            $end = $end->endOfDay();
        }
        if ($end <= $start) {
            $end = $start->addDay();
        }
        if ($start->diffInDays($end) > self::MAX_DAYS) {
            $end = $start->addDays(self::MAX_DAYS);
        }

        return [$start, $end];
    }

    public function forProduct(Product $product, CarbonImmutable $from, CarbonImmutable $to, ?int $locationId = null): array
    {
        $product->loadMissing(['category:id,name,color', 'manufacturer:id,name']);
        $breakdown = $this->availability->forProduct($product, $from, $to)->toArray();

        $distribution = $this->distribution($product);
        $breakdown['sources']['total'] = array_map(fn ($row) => [
            'type' => 'location',
            'id' => $row['location_id'],
            'label' => $row['location'],
            'quantity' => $row['owned'],
            'url' => $row['location_id'] ? '/inventory/locations/'.$row['location_id'] : null,
            'starts_at' => null,
            'ends_at' => null,
            'meta' => ['serviceable' => $row['serviceable']],
        ], array_values(array_filter($distribution, fn ($r) => $r['owned'] > 0)));

        foreach (self::BUCKETS as $bucket) {
            $breakdown['sources'][$bucket] ??= [];
        }
        $breakdown['sources']['available'] = $this->availableExplanation($breakdown);

        $required = 0;
        $spare = 0;
        foreach (['reserved', 'checked_out', 'tentative'] as $bucket) {
            foreach ($breakdown['sources'][$bucket] as $source) {
                $required += (int) ($source['meta']['required'] ?? $source['quantity']);
                $spare += (int) ($source['meta']['spare'] ?? 0);
            }
        }

        $days = max(1, min(self::MAX_DAYS, (int) ceil($from->startOfDay()->diffInDays($to))));
        $timeline = array_map(fn (array $d) => $d + ['short' => $d['available'] < 0], $this->availability->timeline($product, $from, $days));

        $locationSummary = null;
        if ($locationId && ($location = Location::query()->find($locationId))) {
            $ids = $this->hierarchy->descendantIds(Location::class, $location->id);
            $rows = array_values(array_filter($distribution, fn ($r) => $r['location_id'] && in_array($r['location_id'], $ids, true)));
            $locationSummary = ['id' => $location->id, 'name' => $location->path()];
            foreach (['owned', 'serviceable', 'maintenance', 'damaged', 'lost', 'retired', 'checked_out'] as $key) {
                $locationSummary[$key] = array_sum(array_column($rows, $key));
            }
            $distribution = $rows;
        }

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'model' => $product->model,
                'tracking' => $product->tracking->value,
                'tracking_label' => $product->tracking->label(),
                'category' => $product->category?->name,
                'manufacturer' => $product->manufacturer?->name,
                'url' => '/inventory/products/'.$product->id,
            ],
            'window' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'days' => $days],
            'breakdown' => $breakdown,
            'spares' => ['required' => $required, 'spare' => $spare],
            'timeline' => $timeline,
            'shortageDays' => count(array_filter($timeline, fn ($d) => $d['short'])),
            'locations' => $distribution,
            'locationSummary' => $locationSummary,
        ];
    }

    /**
     * Products matching a query with their availability in the window.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query, CarbonImmutable $from, CarbonImmutable $to, int $limit = 12): array
    {
        $like = '%'.addcslashes($query, '%_\\').'%';
        $products = Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('barcode', 'like', $like)
                ->orWhere('model', 'like', $like)
                ->orWhereIn('id', Asset::query()->where('asset_number', $query)->orWhere('barcode', $query)->orWhere('serial_number', $query)->select('product_id'))))
            ->orderByRaw('case when lower(name) like ? then 0 else 1 end', [mb_strtolower(addcslashes($query, '%_\\')).'%'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $results = $this->availability->forProducts($products, $from, $to);

        return $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'category' => $p->category?->name,
            'tracking' => $p->tracking->value,
            'total' => $results[$p->id]->total,
            'available' => $results[$p->id]->available,
        ])->values()->all();
    }

    /**
     * Where the physical units are, split by status bucket.
     *
     * @return list<array{location_id: ?int, location: string, owned: int, serviceable: int, checked_out: int, maintenance: int, damaged: int, lost: int, retired: int}>
     */
    public function distribution(Product $product): array
    {
        $paths = collect($this->hierarchy->locations())->pluck('label', 'value');
        $rows = [];
        $row = function (?int $id) use (&$rows, $paths): void {
            $rows[$id ?? 0] ??= [
                'location_id' => $id,
                'location' => $id ? ($paths[$id] ?? 'Location #'.$id) : 'Unassigned',
                'owned' => 0, 'serviceable' => 0, 'checked_out' => 0, 'maintenance' => 0, 'damaged' => 0, 'lost' => 0, 'retired' => 0,
            ];
        };

        if ($product->tracking === TrackingType::Serialized) {
            $counts = Asset::query()->where('product_id', $product->id)
                ->selectRaw('location_id, status, count(*) as c')->groupBy('location_id', 'status')->get();
            foreach ($counts as $c) {
                $key = $c->location_id ?? 0;
                $row($c->location_id);
                $status = $c->status instanceof AssetStatus ? $c->status : AssetStatus::from((string) $c->status);
                $rows[$key]['owned'] += (int) $c->c;
                $bucket = match ($status) {
                    AssetStatus::CheckedOut => 'checked_out',
                    AssetStatus::InMaintenance => 'maintenance',
                    AssetStatus::Damaged => 'damaged',
                    AssetStatus::Lost => 'lost',
                    AssetStatus::Retired => 'retired',
                    default => null,
                };
                if ($bucket) {
                    $rows[$key][$bucket] += (int) $c->c;
                }
                if (! $status->isUnserviceable()) {
                    $rows[$key]['serviceable'] += (int) $c->c;
                }
            }
        } else {
            foreach (StockLevel::query()->where('product_id', $product->id)->get() as $level) {
                $key = $level->location_id ?? 0;
                $row($level->location_id);
                $rows[$key]['owned'] += $level->quantity;
                $rows[$key]['maintenance'] += $level->quantity_maintenance;
                $rows[$key]['damaged'] += $level->quantity_damaged;
                $rows[$key]['lost'] += $level->quantity_lost;
                $rows[$key]['serviceable'] += max(0, $level->quantity - $level->quantity_maintenance - $level->quantity_damaged - $level->quantity_lost);
            }
        }

        $out = array_values($rows);
        usort($out, fn ($a, $b) => [$b['owned'], $a['location']] <=> [$a['owned'], $b['location']]);

        return $out;
    }

    /** The "available" tile explained as a calculation. */
    private function availableExplanation(array $b): array
    {
        $line = fn (string $label, int $qty, ?string $bucket = null) => [
            'type' => 'calculation', 'id' => $bucket, 'label' => $label, 'quantity' => $qty,
            'url' => null, 'starts_at' => null, 'ends_at' => null, 'meta' => ['bucket' => $bucket],
        ];

        $lines = [$line('Owned units', $b['owned'], 'total')];
        foreach (['retired' => 'Retired', 'lost' => 'Lost', 'damaged' => 'Damaged', 'maintenance' => 'In maintenance'] as $key => $label) {
            if ($b[$key] > 0) {
                $lines[] = $line('− '.$label, -$b[$key], $key);
            }
        }
        if ($b['reserved'] > 0) {
            $lines[] = $line('− Reserved at peak', -$b['reserved'], 'reserved');
        }
        if ($b['checked_out'] > 0) {
            $lines[] = $line('− Checked out at peak', -$b['checked_out'], 'checked_out');
        }
        $other = $b['total'] - $b['available'] - $b['reserved'] - $b['checked_out'];
        if ($other !== 0) {
            $lines[] = $line('− Tentative holds at peak', -$other, 'tentative');
        }
        $lines[] = $line('= Available at peak', $b['available'], null);

        return $lines;
    }
}
