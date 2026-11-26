<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\DTOs\AllocationSource;
use App\Domain\Inventory\DTOs\AvailabilityBreakdown;
use App\Domain\Inventory\DTOs\AvailabilityHold;
use App\Domain\Inventory\Enums\TrackingType;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Settings\Services\SettingsRepository;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Gathers inventory state from the database and feeds it to the pure
 * AvailabilityCalculator. Queries are batched per product set so the
 * explorer can evaluate a page of products in a constant number of queries.
 */
class AvailabilityService
{
    public function __construct(
        private readonly AvailabilityCalculator $calculator,
        private readonly SettingsRepository $settings,
    ) {}

    public function forProduct(Product $product, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?int $ignoreJobId = null, ?int $ignoreReservationId = null): AvailabilityBreakdown
    {
        return $this->forProducts(collect([$product]), $from, $to, $ignoreJobId, $ignoreReservationId)[$product->id];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, AvailabilityBreakdown> keyed by product id
     */
    public function forProducts(Collection $products, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?int $ignoreJobId = null, ?int $ignoreReservationId = null): array
    {
        [$from, $to] = $this->window($from, $to);
        $ids = $products->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $serializedIds = $products->filter(fn (Product $p) => $p->tracking === TrackingType::Serialized)->pluck('id')->all();
        $bulkIds = array_values(array_diff($ids, $serializedIds));

        $owned = array_fill_keys($ids, 0);
        $unserviceable = array_fill_keys($ids, ['maintenance' => [], 'damaged' => [], 'lost' => [], 'retired' => []]);

        $this->collectSerialized($serializedIds, $owned, $unserviceable);
        $this->collectBulk($bulkIds, $owned, $unserviceable);

        $holds = $this->holdsFor($ids, $from, $to, $ignoreJobId, $ignoreReservationId);
        $deductTentative = (bool) $this->settings->get('reservations.count_tentative', false);

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $this->calculator->calculate(
                $owned[$id],
                $unserviceable[$id],
                $holds[$id] ?? [],
                $from,
                $to,
                $deductTentative,
            );
        }

        return $result;
    }

    /**
     * @return list<array{date: string, committed: int, tentative: int, available: int}>
     */
    public function timeline(Product $product, DateTimeInterface $from, int $days = 30): array
    {
        $start = CarbonImmutable::instance($from)->startOfDay()->toDateTimeImmutable();
        $end = $start->modify("+{$days} days");
        $breakdown = $this->forProduct($product, $start, $end);
        $holds = $this->holdsFor([$product->id], $start, $end)[$product->id] ?? [];

        return $this->calculator->timeline(
            $breakdown->total,
            $holds,
            $start,
            $days,
            (bool) $this->settings->get('reservations.count_tentative', false),
        );
    }

    /**
     * Per-location stock summary (where the physical units currently are).
     *
     * @return list<array{location_id: ?int, location: string, quantity: int, url: ?string}>
     */
    public function locations(Product $product): array
    {
        if ($product->tracking === TrackingType::Serialized) {
            return Asset::query()
                ->where('product_id', $product->id)
                ->where('status', '!=', AssetStatus::Retired->value)
                ->with('location')
                ->get()
                ->groupBy('location_id')
                ->map(fn ($assets, $locationId) => [
                    'location_id' => $locationId ?: null,
                    'location' => $assets->first()->location?->path() ?? 'Unassigned',
                    'quantity' => $assets->count(),
                    'url' => $locationId ? '/locations/'.$locationId : null,
                ])->values()->all();
        }

        return StockLevel::query()->where('product_id', $product->id)->with('location')->get()
            ->map(fn (StockLevel $s) => [
                'location_id' => $s->location_id,
                'location' => $s->location?->path() ?? 'Unassigned',
                'quantity' => (int) $s->quantity,
                'url' => $s->location_id ? '/locations/'.$s->location_id : null,
            ])->all();
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    public function window(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $from = $from ? CarbonImmutable::instance($from) : CarbonImmutable::now();
        $to = $to ? CarbonImmutable::instance($to) : $from->addDay();
        if ($to <= $from) {
            $to = $from->addMinute();
        }

        return [$from->toDateTimeImmutable(), $to->toDateTimeImmutable()];
    }

    private function collectSerialized(array $ids, array &$owned, array &$unserviceable): void
    {
        if ($ids === []) {
            return;
        }

        foreach (Asset::query()->whereIn('product_id', $ids)
            ->selectRaw('product_id, count(*) as c')->groupBy('product_id')->pluck('c', 'product_id') as $pid => $count) {
            $owned[$pid] = (int) $count;
        }

        $bucketOf = [
            AssetStatus::InMaintenance->value => 'maintenance',
            AssetStatus::Damaged->value => 'damaged',
            AssetStatus::Lost->value => 'lost',
            AssetStatus::Retired->value => 'retired',
        ];

        $assets = Asset::query()
            ->whereIn('product_id', $ids)
            ->whereIn('status', array_keys($bucketOf))
            ->with(['location:id,name', 'maintenanceRecords' => fn ($q) => $q->active()->latest()])
            ->get(['id', 'product_id', 'asset_number', 'serial_number', 'status', 'location_id']);

        foreach ($assets as $asset) {
            $bucket = $bucketOf[$asset->status->value];
            $record = $asset->maintenanceRecords->first();
            $unserviceable[$asset->product_id][$bucket][] = $record && $bucket !== 'retired'
                ? new AllocationSource('maintenance', $record->id, $asset->asset_number.' — '.$record->reference.' '.$record->title, 1, '/maintenance/'.$record->id, meta: ['asset_id' => $asset->id, 'status' => $record->status->value])
                : new AllocationSource('asset', $asset->id, $asset->asset_number.($asset->location ? ' @ '.$asset->location->name : ''), 1, '/assets/'.$asset->id, meta: ['serial' => $asset->serial_number]);
        }
    }

    private function collectBulk(array $ids, array &$owned, array &$unserviceable): void
    {
        if ($ids === []) {
            return;
        }

        $records = MaintenanceRecord::query()->active()->whereIn('product_id', $ids)->whereNull('asset_id')->get()->groupBy('product_id');

        foreach (StockLevel::query()->whereIn('product_id', $ids)->with('location:id,name')->get()->groupBy('product_id') as $pid => $levels) {
            $owned[$pid] = (int) $levels->sum('quantity');

            foreach (['maintenance' => 'quantity_maintenance', 'damaged' => 'quantity_damaged', 'lost' => 'quantity_lost'] as $bucket => $column) {
                $bucketTotal = (int) $levels->sum($column);
                if ($bucketTotal === 0) {
                    continue;
                }
                $explained = 0;
                if ($bucket === 'maintenance' || $bucket === 'damaged') {
                    foreach ($records->get($pid, collect()) as $record) {
                        $isDamage = $record->type === 'damage';
                        if (($bucket === 'damaged') !== $isDamage) {
                            continue;
                        }
                        $qty = min((int) $record->quantity, $bucketTotal - $explained);
                        if ($qty <= 0) {
                            break;
                        }
                        $explained += $qty;
                        $unserviceable[$pid][$bucket][] = new AllocationSource('maintenance', $record->id, $record->reference.' — '.$record->title, $qty, '/maintenance/'.$record->id);
                    }
                }
                foreach ($levels as $level) {
                    $remaining = $bucketTotal - $explained;
                    $qty = min((int) $level->{$column}, $remaining);
                    if ($qty <= 0) {
                        continue;
                    }
                    $explained += $qty;
                    $unserviceable[$pid][$bucket][] = new AllocationSource('location', $level->location_id, ($level->location?->name ?? 'Unassigned').' — '.$bucket, $qty, $level->location_id ? '/locations/'.$level->location_id : null);
                }
            }
        }
    }

    /**
     * Builds holds from reservations. For job reservations, units already
     * checked out are reported as a checked_out hold (lasting until they are
     * returned, even when overdue) and only the outstanding remainder is
     * reported as reserved, so units are never double counted.
     *
     * @return array<int, list<AvailabilityHold>>
     */
    public function holdsFor(array $productIds, DateTimeImmutable $from, DateTimeImmutable $to, ?int $ignoreJobId = null, ?int $ignoreReservationId = null): array
    {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        $reservations = Reservation::query()
            ->whereIn('product_id', $productIds)
            ->where('status', '!=', ReservationStatus::Released->value)
            ->where(function ($q) use ($from, $to, $now) {
                $q->overlapping($from, $to)
                    // overdue equipment that is still out keeps holding stock
                    ->orWhere(fn ($q) => $q->where('ends_at', '<=', $now)->whereHas('jobItem', fn ($i) => $i->whereColumn('checked_out_quantity', '>', 'returned_quantity')));
            })
            ->when($ignoreJobId, fn ($q) => $q->where(fn ($q) => $q->whereNull('job_id')->orWhere('job_id', '!=', $ignoreJobId)))
            ->when($ignoreReservationId, fn ($q) => $q->where('id', '!=', $ignoreReservationId))
            ->with(['job:id,number,name,stage', 'jobItem:id,checked_out_quantity,returned_quantity'])
            ->get();

        $holds = [];
        foreach ($reservations as $r) {
            $start = CarbonImmutable::instance($r->starts_at)->toDateTimeImmutable();
            $end = CarbonImmutable::instance($r->ends_at)->toDateTimeImmutable();
            $url = $r->job_id ? '/jobs/'.$r->job_id : '/reservations/'.$r->id;
            $type = $r->job_id ? 'job' : 'reservation';
            $id = $r->job_id ?? $r->id;
            $meta = ['reservation_id' => $r->id, 'required' => (int) $r->quantity, 'spare' => (int) $r->spare_quantity, 'stage' => $r->job?->stage?->value];
            $total = $r->totalQuantity();

            $out = $r->jobItem ? max(0, (int) $r->jobItem->checked_out_quantity - (int) $r->jobItem->returned_quantity) : 0;
            if ($out > 0) {
                $outEnd = $end < $now ? max($to, $now->modify('+1 day')) : $end;
                $holds[$r->product_id][] = new AvailabilityHold(
                    AvailabilityHold::CHECKED_OUT, $out, $start, $outEnd,
                    new AllocationSource($type, $id, $r->label(), $out, $url, $start->format(DATE_ATOM), $outEnd->format(DATE_ATOM), $meta + ['overdue' => $end < $now]),
                );
            }

            $outstanding = max(0, $total - $out);
            if ($outstanding > 0 && $end > $from && $start < $to) {
                $kind = $r->status === ReservationStatus::Tentative ? AvailabilityHold::TENTATIVE : AvailabilityHold::RESERVED;
                $holds[$r->product_id][] = new AvailabilityHold(
                    $kind, $outstanding, $start, $end,
                    new AllocationSource($type, $id, $r->label(), $outstanding, $url, $start->format(DATE_ATOM), $end->format(DATE_ATOM), $meta),
                );
            }
        }

        return $holds;
    }
}
