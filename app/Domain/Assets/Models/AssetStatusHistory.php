<?php

namespace App\Domain\Assets\Models;

use App\Domain\Inventory\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AssetStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'asset_status_history';

    protected $fillable = ['asset_id', 'from_status', 'to_status', 'location_id', 'context_type', 'context_id', 'user_id', 'notes'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}
