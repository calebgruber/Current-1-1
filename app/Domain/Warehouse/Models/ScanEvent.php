<?php

namespace App\Domain\Warehouse\Models;

use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\ProductionJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['mode', 'code', 'result', 'message', 'job_id', 'asset_id', 'product_id', 'location_id', 'quantity', 'station', 'user_id'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class, 'job_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
