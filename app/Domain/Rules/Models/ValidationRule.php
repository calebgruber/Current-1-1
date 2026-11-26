<?php

namespace App\Domain\Rules\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Administrator-authored dependency/validation rule. See Rules\Services\RuleEvaluator
 * for the definition schema.
 */
class ValidationRule extends Model
{
    use Auditable;

    protected $fillable = ['name', 'description', 'severity', 'is_active', 'definition', 'created_by'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
