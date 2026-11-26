<?php

namespace App\Domain\Jobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Note extends Model
{
    protected $fillable = ['notable_type', 'notable_id', 'user_id', 'body', 'is_pinned'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
