<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAccessory extends Model
{
    protected $fillable = ['product_id', 'accessory_product_id', 'quantity', 'type', 'auto_add', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'auto_add' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'accessory_product_id');
    }
}
