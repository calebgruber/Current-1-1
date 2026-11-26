<?php

namespace App\Domain\Jobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobStageHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'job_stage_history';

    protected $fillable = ['job_id', 'from_stage', 'to_stage', 'user_id', 'notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
