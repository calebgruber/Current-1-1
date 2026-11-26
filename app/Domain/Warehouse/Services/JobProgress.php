<?php

namespace App\Domain\Warehouse\Services;

use App\Domain\Inventory\Enums\TrackingType;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Warehouse\Enums\ScanMode;
use App\Domain\Warehouse\Models\ScanEvent;

/** Per-line warehouse progress for station pages, the kiosk display and the hub. */
class JobProgress
{
    private const PREPPED = ['prepped', 'staged', 'checked_out', 'returned'];

    private const STAGED = ['staged', 'checked_out', 'returned'];

    public function header(ProductionJob $job): array
    {
        return [
            'id' => $job->id,
            'number' => $job->number,
            'name' => $job->name,
            'stage' => $job->stage->value,
            'stage_label' => $job->stage->label(),
            'is_warehouse_active' => $job->stage->isWarehouseActive(),
            'client' => $job->client?->name,
            'venue' => $job->venue?->name,
            'prep_starts_at' => $job->prep_starts_at?->toIso8601String(),
            'starts_at' => $job->starts_at?->toIso8601String(),
            'returns_at' => $job->returns_at?->toIso8601String(),
        ];
    }

    /** @return array{lines: list<array<string, mixed>>, totals: array<string, int>} */
    public function lines(ProductionJob $job): array
    {
        $items = $job->items()->with(['product:id,name,sku,tracking,default_location_id', 'product.defaultLocation', 'assignedAssets.asset:id,asset_number,serial_number,status'])->get();

        $lines = [];
        $totals = ['required' => 0, 'spare' => 0, 'pull' => 0, 'pick' => 0, 'prep' => 0, 'stage' => 0, 'checkout' => 0, 'return' => 0, 'outstanding' => 0];

        foreach ($items as $item) {
            /** @var JobItem $item */
            $serialized = $item->product?->tracking === TrackingType::Serialized;
            $assets = $item->assignedAssets;
            $prepped = $serialized ? $assets->whereIn('status', self::PREPPED)->count() : $item->picked_quantity;
            $staged = $serialized ? $assets->whereIn('status', self::STAGED)->count() : $item->picked_quantity;
            $outstanding = max(0, $item->checked_out_quantity - $item->returned_quantity);

            $line = [
                'id' => $item->id,
                'parent_id' => $item->parent_item_id,
                'product_id' => $item->product_id,
                'product' => $item->product?->name ?? 'Unknown product',
                'sku' => $item->product?->sku,
                'serialized' => $serialized,
                'location' => $item->product?->defaultLocation?->path(),
                'section' => $item->section,
                'required' => $item->required_quantity,
                'spare' => $item->spare_quantity,
                'pull' => $item->pull_quantity,
                'done' => [
                    'pick' => $item->picked_quantity,
                    'prep' => $prepped,
                    'stage' => $staged,
                    'checkout' => $item->checked_out_quantity,
                    'return' => $item->returned_quantity,
                ],
                'outstanding' => $outstanding,
                'assets' => $assets->map(fn ($a) => [
                    'id' => $a->asset_id,
                    'asset_number' => $a->asset?->asset_number,
                    'role' => $a->role,
                    'status' => $a->status,
                ])->values()->all(),
            ];
            $lines[] = $line;

            $totals['required'] += $item->required_quantity;
            $totals['spare'] += $item->spare_quantity;
            $totals['pull'] += $item->pull_quantity;
            $totals['pick'] += min($item->picked_quantity, $item->pull_quantity);
            $totals['prep'] += min($prepped, $item->pull_quantity);
            $totals['stage'] += min($staged, $item->pull_quantity);
            $totals['checkout'] += min($item->checked_out_quantity, $item->pull_quantity);
            $totals['return'] += $item->returned_quantity;
            $totals['outstanding'] += $outstanding;
        }

        return ['lines' => $lines, 'totals' => $totals];
    }

    /** Assets that went out and have not come back yet. */
    public function missing(ProductionJob $job): array
    {
        return $job->items()->with(['product:id,name', 'assignedAssets' => fn ($q) => $q->whereIn('status', ['checked_out', 'missing']), 'assignedAssets.asset:id,asset_number,serial_number'])
            ->get()
            ->flatMap(fn (JobItem $i) => $i->assignedAssets->map(fn ($a) => [
                'job_item_id' => $i->id,
                'asset_id' => $a->asset_id,
                'asset_number' => $a->asset?->asset_number,
                'serial_number' => $a->asset?->serial_number,
                'product' => $i->product?->name,
                'status' => $a->status,
            ]))->values()->all();
    }

    public function recentScans(?ProductionJob $job, int $limit = 15, ?ScanMode $mode = null): array
    {
        return ScanEvent::query()->with(['user:id,name', 'job:id,number'])
            ->when($job, fn ($q) => $q->where('job_id', $job->id))
            ->when($mode, fn ($q) => $q->where('mode', $mode->value))
            ->latest('id')->limit($limit)->get()
            ->map(fn (ScanEvent $e) => [
                'id' => $e->id,
                'mode' => $e->mode,
                'code' => $e->code,
                'level' => $e->result,
                'message' => $e->message,
                'quantity' => $e->quantity,
                'job' => $e->job?->number,
                'user' => $e->user?->name,
                'at' => $e->created_at?->toIso8601String(),
            ])->all();
    }

    /** Percentage for a mode's counter across the job. */
    public static function percent(array $totals, string $mode): int
    {
        $denominator = $mode === 'return' ? max(1, $totals['checkout']) : max(1, $totals['pull']);

        return $totals['pull'] === 0 ? 0 : (int) min(100, round($totals[$mode] / $denominator * 100));
    }
}
