<?php

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetStatusHistory;
use Illuminate\Database\Eloquent\Model;

class AssetService
{
    /** Changes an asset's status and records it in the status history. */
    public function changeStatus(Asset $asset, AssetStatus $status, ?Model $context = null, ?int $userId = null, ?string $notes = null, ?int $locationId = null): Asset
    {
        $from = $asset->status;
        if ($from === $status && ($locationId === null || $locationId === $asset->location_id)) {
            return $asset;
        }

        $asset->status = $status;
        if ($locationId !== null) {
            $asset->location_id = $locationId;
        }
        $asset->save();

        AssetStatusHistory::query()->create([
            'asset_id' => $asset->id,
            'from_status' => $from?->value,
            'to_status' => $status->value,
            'location_id' => $asset->location_id,
            'context_type' => $context?->getMorphClass(),
            'context_id' => $context?->getKey(),
            'user_id' => $userId,
            'notes' => $notes ? mb_substr($notes, 0, 255) : null,
        ]);

        return $asset;
    }
}
