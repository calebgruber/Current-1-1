<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Quantity buckets for bulk-tracked products, per location. */
class StockLevel extends Model
{
    protected $fillable = ['product_id', 'location_id', 'quantity', 'quantity_maintenance', 'quantity_damaged', 'quantity_lost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_maintenance' => 'integer',
            'quantity_damaged' => 'integer',
            'quantity_lost' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
