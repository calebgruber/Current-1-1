<?php

namespace App\Domain\Warehouse\Services;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetService;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Jobs\Models\JobItemAsset;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Warehouse\DTOs\ScanResult;
use App\Domain\Warehouse\Enums\ScanMode;
use App\Domain\Warehouse\Models\ScanEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves scanned codes, runs the pure ScanProcessor and applies its effects
 * atomically. Every scan (including failures) is written to scan_events.
 */
class WarehouseService
{
    public function __construct(
        private readonly ScanProcessor $processor,
        private readonly AssetService $assets,
    ) {}

    /** Resolves a scanned code to an asset or bulk product. */
    public function resolve(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $asset = Asset::query()->with('product:id,name')
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('asset_number', $code)->orWhere('serial_number', $code))
            ->first();
        if ($asset) {
            return [
                'kind' => 'asset',
                'asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'product_id' => $asset->product_id,
                'product' => $asset->product?->name ?? 'Unknown product',
                'status' => $asset->status->value,
            ];
        }

        $product = Product::query()->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))->first(['id', 'name']);
        if ($product) {
            return ['kind' => 'product', 'product_id' => $product->id, 'product' => $product->name];
        }

        return null;
    }

    /** Job allocations in the shape ScanProcessor expects. */
    public function lines(ProductionJob $job): array
    {
        return $job->items()->with(['product:id,name', 'assignedAssets:id,job_item_id,asset_id,status'])->orderBy('id')->get()
            ->map(fn (JobItem $i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product' => $i->product?->name ?? '',
                'required' => $i->required_quantity,
                'spare' => $i->spare_quantity,
                'picked' => $i->picked_quantity,
                'checked_out' => $i->checked_out_quantity,
                'returned' => $i->returned_quantity,
                'assets' => $i->assignedAssets->pluck('status', 'asset_id')->all(),
            ])->all();
    }

    public function scan(ScanMode $mode, string $code, ?ProductionJob $job, ?User $user = null, int $quantity = 1, ?string $station = null): ScanResult
    {
        // A job number scanned in any mode is a convenient way to switch jobs.
        if ($scannedJob = ProductionJob::query()->where('number', trim($code))->first()) {
            $result = new ScanResult(ScanResult::SUCCESS, 'Job selected', $scannedJob->number.' — '.$scannedJob->name, [['type' => 'select_job', 'job_id' => $scannedJob->id]]);
            $this->log($mode, $code, $result, $scannedJob, null, $user, 1, $station);

            return $result;
        }

        if ($mode->requiresJob() && ! $job) {
            $result = ScanResult::error('Choose a job', 'Select or scan a job before scanning equipment in '.$mode->label().' mode.');
            $this->log($mode, $code, $result, null, null, $user, $quantity, $station);

            return $result;
        }

        return DB::transaction(function () use ($mode, $code, $job, $user, $quantity, $station) {
            $match = $this->resolve($code);
            if ($match && $match['kind'] === 'asset') {
                // lock to avoid two scanners grabbing the same unit concurrently
                Asset::query()->whereKey($match['asset_id'])->lockForUpdate()->first();
            }
            $lines = $job ? $this->lines($job) : [];
            $result = $this->processor->process($mode, $code, $match, $lines, $quantity);

            foreach ($result->effects as $effect) {
                $this->apply($effect, $job, $user);
            }

            $this->log($mode, $code, $result, $job, $match, $user, ($match['kind'] ?? null) === 'asset' ? 1 : $quantity, $station);

            return $result;
        });
    }

    private function apply(array $effect, ?ProductionJob $job, ?User $user): void
    {
        switch ($effect['type']) {
            case 'increment':
                JobItem::query()->whereKey($effect['job_item_id'])->increment($effect['field'], $effect['quantity']);
                break;
            case 'assign_asset':
                $assignment = JobItemAsset::query()->firstOrNew(['job_item_id' => $effect['job_item_id'], 'asset_id' => $effect['asset_id']]);
                if (! $assignment->exists) {
                    $assignment->role = $effect['role'] ?? 'active';
                }
                $assignment->status = $effect['status'];
                if ($effect['status'] === 'checked_out') {
                    $assignment->checked_out_by = $user?->id;
                    $assignment->checked_out_at = now();
                }
                if ($effect['status'] === 'returned') {
                    $assignment->returned_by = $user?->id;
                    $assignment->returned_at = now();
                }
                $assignment->save();
                break;
            case 'asset_status':
                $asset = Asset::query()->find($effect['asset_id']);
                if ($asset) {
                    $this->assets->changeStatus($asset, AssetStatus::from($effect['status']), $job, $user?->id, 'Warehouse scan');
                }
                break;
            case 'create_maintenance':
                MaintenanceRecord::query()->create([
                    'product_id' => $effect['product_id'],
                    'asset_id' => $effect['asset_id'],
                    'quantity' => 1,
                    'type' => 'damage',
                    'status' => 'open',
                    'title' => 'Damage reported from warehouse',
                    'job_id' => $job?->id,
                    'reported_by' => $user?->id,
                ]);
                break;
            case 'bulk_damage':
                $level = StockLevel::query()->where('product_id', $effect['product_id'])
                    ->whereRaw('quantity >= quantity_maintenance + quantity_damaged + quantity_lost + ?', [$effect['quantity']])
                    ->orderByDesc('quantity')->first();
                $level?->increment('quantity_damaged', $effect['quantity']);
                MaintenanceRecord::query()->create([
                    'product_id' => $effect['product_id'],
                    'quantity' => $effect['quantity'],
                    'type' => 'damage',
                    'status' => 'open',
                    'title' => 'Damage reported from warehouse',
                    'job_id' => $job?->id,
                    'reported_by' => $user?->id,
                ]);
                break;
        }
    }

    private function log(ScanMode $mode, string $code, ScanResult $result, ?ProductionJob $job, ?array $match, ?User $user, int $quantity, ?string $station): void
    {
        ScanEvent::query()->create([
            'mode' => $mode->value,
            'code' => mb_substr($code, 0, 191),
            'result' => $result->level,
            'message' => mb_substr($result->title.': '.$result->message, 0, 255),
            'job_id' => $job?->id,
            'asset_id' => $match['asset_id'] ?? null,
            'product_id' => $match['product_id'] ?? null,
            'quantity' => max(1, $quantity),
            'station' => $station ? mb_substr($station, 0, 64) : null,
            'user_id' => $user?->id,
        ]);
    }
}
