<?php

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetStatusHistory;
use App\Domain\Inventory\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Registers new serialized assets (single or batch) with automatic numbering. */
class AssetRegistrar
{
    public function __construct(private readonly AssetService $assets) {}

    /**
     * @param  array<string, mixed>  $data  validated asset attributes
     * @return list<Asset>
     */
    public function register(Product $product, array $data, int $count = 1, ?string $prefix = null, ?int $userId = null): array
    {
        return DB::transaction(function () use ($product, $data, $count, $prefix, $userId) {
            $prefix = $this->prefixFor($product, $prefix);
            $created = [];
            for ($i = 0; $i < $count; $i++) {
                $number = ($i === 0 && ! empty($data['asset_number'])) ? $data['asset_number'] : $this->nextNumber($prefix);
                $asset = Asset::query()->create([
                    ...collect($data)->only(['serial_number', 'condition', 'location_id', 'purchased_at', 'purchase_cost', 'warranty_expires_at', 'notes'])->all(),
                    'serial_number' => $count === 1 ? ($data['serial_number'] ?? null) : null,
                    'barcode' => $count === 1 ? ($data['barcode'] ?? null) : null,
                    'product_id' => $product->id,
                    'asset_number' => $number,
                    'status' => AssetStatus::Available->value,
                ]);
                AssetStatusHistory::query()->create([
                    'asset_id' => $asset->id,
                    'from_status' => null,
                    'to_status' => AssetStatus::Available->value,
                    'location_id' => $asset->location_id,
                    'user_id' => $userId,
                    'notes' => 'Asset registered',
                ]);
                $created[] = $asset;
            }

            return $created;
        });
    }

    /** Records a location move in the status history (status unchanged). */
    public function move(Asset $asset, ?int $locationId, ?int $userId = null, ?string $notes = null): void
    {
        if ($locationId === $asset->location_id) {
            return;
        }
        if ($locationId !== null) {
            $this->assets->changeStatus($asset, $asset->status, null, $userId, $notes ?? 'Location changed', $locationId);

            return;
        }
        // AssetService cannot clear a location, so record the unassignment directly.
        $asset->forceFill(['location_id' => null])->save();
        AssetStatusHistory::query()->create([
            'asset_id' => $asset->id,
            'from_status' => $asset->status->value,
            'to_status' => $asset->status->value,
            'location_id' => null,
            'user_id' => $userId,
            'notes' => $notes ?? 'Location cleared',
        ]);
    }

    public function prefixFor(Product $product, ?string $prefix): string
    {
        $prefix = trim((string) $prefix);
        if ($prefix === '') {
            $prefix = $product->sku ? Str::upper(Str::slug($product->sku)) : 'A'.$product->id;
        }

        return mb_substr(rtrim($prefix, '-'), 0, 32);
    }

    public function nextNumber(string $prefix): string
    {
        $like = addcslashes($prefix, '%_\\').'-%';
        $max = 0;
        foreach (Asset::withTrashed()->where('asset_number', 'like', $like)->pluck('asset_number') as $number) {
            $suffix = substr($number, strlen($prefix) + 1);
            if (ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        do {
            $candidate = $prefix.'-'.str_pad((string) ++$max, 4, '0', STR_PAD_LEFT);
        } while (Asset::withTrashed()->where('asset_number', $candidate)->exists());

        return $candidate;
    }
}
