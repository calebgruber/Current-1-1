<?php

namespace App\Domain\Assets\Models;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\JobItemAsset;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\HasCustomFields;
use App\Support\Concerns\Searchable;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use Auditable, HasCustomFields, HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'product_id', 'asset_number', 'serial_number', 'barcode', 'status', 'condition', 'location_id',
        'purchased_at', 'purchase_cost', 'warranty_expires_at', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'purchased_at' => 'date',
            'warranty_expires_at' => 'date',
            'purchase_cost' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class)->latest('id');
    }

    public function conditionReports(): HasMany
    {
        return $this->hasMany(ConditionReport::class)->latest();
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class)->latest();
    }

    public function jobAssignments(): HasMany
    {
        return $this->hasMany(JobItemAsset::class);
    }

    /** All codes a scanner might produce for this asset. */
    public function scanCodes(): array
    {
        return array_values(array_filter([$this->barcode, $this->asset_number, $this->serial_number]));
    }

    public function toSearchEntry(): ?array
    {
        return [
            'title' => $this->asset_number.' — '.($this->product?->name ?? 'Asset'),
            'subtitle' => $this->status?->label().($this->serial_number ? ' · S/N '.$this->serial_number : ''),
            'keywords' => $this->notes,
            'codes' => [$this->asset_number, $this->serial_number, $this->barcode],
            'url' => '/assets/'.$this->id,
        ];
    }
}
