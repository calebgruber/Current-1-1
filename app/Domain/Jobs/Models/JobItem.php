<?php

namespace App\Domain\Jobs\Models;

use App\Domain\Inventory\Models\Product;
use App\Domain\Reservations\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An equipment allocation on a job. Tracks the active (required) quantity and
 * spare/backup quantity separately; pull quantity = required + spare.
 */
class JobItem extends Model
{
    protected $fillable = [
        'job_id', 'product_id', 'parent_item_id', 'section', 'required_quantity', 'spare_quantity',
        'picked_quantity', 'checked_out_quantity', 'returned_quantity', 'unit_price', 'notes', 'sort_order',
    ];

    protected $appends = ['pull_quantity'];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'integer',
            'spare_quantity' => 'integer',
            'picked_quantity' => 'integer',
            'checked_out_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function getPullQuantityAttribute(): int
    {
        return (int) $this->required_quantity + (int) $this->spare_quantity;
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class, 'job_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(JobItem::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(JobItem::class, 'parent_item_id');
    }

    public function assignedAssets(): HasMany
    {
        return $this->hasMany(JobItemAsset::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }
}
