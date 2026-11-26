<?php

namespace App\Domain\Jobs\Models;

use App\Domain\Assets\Models\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobItemAsset extends Model
{
    protected $fillable = [
        'job_item_id', 'asset_id', 'role', 'status', 'checked_out_by', 'checked_out_at', 'returned_by', 'returned_at',
    ];

    protected function casts(): array
    {
        return ['checked_out_at' => 'datetime', 'returned_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(JobItem::class, 'job_item_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
