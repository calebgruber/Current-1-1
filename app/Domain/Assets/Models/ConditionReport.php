<?php

namespace App\Domain\Assets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionReport extends Model
{
    protected $fillable = ['asset_id', 'condition', 'notes', 'photos', 'reported_by'];

    protected function casts(): array
    {
        return ['photos' => 'array'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
