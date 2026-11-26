<?php

namespace App\Domain\Reporting\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use Auditable, Searchable;

    protected $fillable = ['name', 'description', 'source', 'definition', 'is_shared', 'role_ids', 'show_on_dashboard', 'created_by'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'role_ids' => 'array', 'is_shared' => 'boolean', 'show_on_dashboard' => 'boolean'];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ReportSchedule::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Role-restricted visibility on top of the reporting.reports.view permission. */
    public function isVisibleTo(User $user): bool
    {
        if ($user->is_super_admin || $this->created_by === $user->id) {
            return true;
        }
        if (! $this->is_shared) {
            return false;
        }
        if (empty($this->role_ids)) {
            return true;
        }

        return $user->roles()->whereIn('roles.id', $this->role_ids)->exists();
    }

    public function toSearchEntry(): ?array
    {
        return [
            'title' => $this->name,
            'subtitle' => 'Report · '.ucfirst($this->source),
            'keywords' => $this->description,
            'codes' => [],
            'url' => '/reports/'.$this->id,
        ];
    }
}
