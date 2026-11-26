<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetService;
use App\Domain\Inventory\Models\Product;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function __construct(private readonly AssetService $assets) {}

    public function save(?MaintenanceRecord $record, array $data, User $user): MaintenanceRecord
    {
        return DB::transaction(function () use ($record, $data, $user) {
            // All maintenance writes for an asset lock the asset before any record.
            $assetId = $record?->asset_id ?? ($data['asset_id'] ?? null);
            $asset = $assetId ? Asset::withTrashed()->lockForUpdate()->findOrFail($assetId) : null;
            if ($record) {
                $record = MaintenanceRecord::query()->lockForUpdate()->findOrFail($record->id);
                if ((int) $record->asset_id !== (int) $assetId) {
                    throw ValidationException::withMessages(['asset_id' => 'The record changed. Please reload and try again.']);
                }
            }
            $status = MaintenanceStatus::from($data['status']);
            $oldStatus = $record?->status;
            if ((! $record && ! $status->isActive()) || ($record && $status !== $oldStatus && (! $status->isActive() || ! $oldStatus->isActive()))) {
                Gate::authorize('maintenance.records.approve');
            }
            if ($oldStatus === MaintenanceStatus::WrittenOff && $status !== $oldStatus) {
                throw ValidationException::withMessages(['status' => 'A written-off record cannot be reopened.']);
            }
            if ($record && ((int) $data['product_id'] !== $record->product_id || (int) ($data['asset_id'] ?? 0) !== (int) $record->asset_id || (int) $data['quantity'] !== $record->quantity)) {
                throw ValidationException::withMessages(['product_id' => 'The product, asset and quantity cannot be changed after creation.']);
            }

            $product = Product::withTrashed()->findOrFail($data['product_id']);
            $enteringActive = $status->isActive() && (! $record || ! $oldStatus->isActive());
            if ((! $record || $enteringActive) && (($product->isSerialized() && ! $asset) || (! $product->isSerialized() && $asset))) {
                throw ValidationException::withMessages(['asset_id' => 'Choose an individual asset for serialized products only.']);
            }
            if ($asset && ((! $record || $enteringActive) && $asset->product_id !== $product->id || (int) $data['quantity'] !== 1)) {
                throw ValidationException::withMessages(['asset_id' => 'The asset must belong to this product and have quantity 1.']);
            }

            if ($enteringActive && ($product->trashed() || $asset?->trashed())) {
                throw ValidationException::withMessages(['status' => 'Deleted products or assets cannot be placed into maintenance.']);
            }
            if ($asset && $enteringActive && ! in_array($asset->status, [AssetStatus::Available, AssetStatus::Damaged, AssetStatus::InMaintenance], true)) {
                throw ValidationException::withMessages(['asset_id' => 'This asset cannot enter maintenance in its current status.']);
            }
            if ($asset && $status === MaintenanceStatus::WrittenOff && $oldStatus !== $status) {
                if ($this->hasActiveRecords($asset, $record?->id)) {
                    throw ValidationException::withMessages(['status' => 'Resolve the other active maintenance records before writing off this asset.']);
                }
                if (! in_array($asset->status, [AssetStatus::Available, AssetStatus::Damaged, AssetStatus::InMaintenance], true)) {
                    throw ValidationException::withMessages(['status' => 'This asset cannot be written off in its current status.']);
                }
            }

            $record ??= new MaintenanceRecord;
            $record->fill($data);
            if (! $record->exists) {
                $record->reported_by = $user->id;
            }
            if ($status === MaintenanceStatus::InProgress && ! $record->started_at) {
                $record->started_at = now();
            }
            if (! $status->isActive() && (! $oldStatus || $oldStatus->isActive())) {
                $record->completed_at = now();
            } elseif ($status->isActive()) {
                $record->completed_at = null;
            }
            $record->save();
            if ($asset && $record->wasRecentlyCreated) {
                $asset->refresh();
            }

            if ($asset && ! $asset->trashed() && $enteringActive && $asset->status === AssetStatus::Available) {
                $this->assets->changeStatus($asset, AssetStatus::InMaintenance, $record, $user->id);
            } elseif ($asset && ! $asset->trashed() && $status === MaintenanceStatus::WrittenOff && $oldStatus !== $status) {
                $this->assets->changeStatus($asset, AssetStatus::Retired, $record, $user->id);
            } elseif ($asset && ! $asset->trashed() && $status === MaintenanceStatus::Completed && $oldStatus !== $status
                && in_array($asset->status, [AssetStatus::InMaintenance, AssetStatus::Damaged], true)
                && ! $this->hasActiveRecords($asset)) {
                $this->assets->changeStatus($asset, AssetStatus::Available, $record, $user->id);
            }

            return $record;
        }, attempts: 3);
    }

    private function hasActiveRecords(Asset $asset, ?int $exceptId = null): bool
    {
        return $asset->maintenanceRecords()->active()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->lockForUpdate()->first(['id']) !== null;
    }
}
