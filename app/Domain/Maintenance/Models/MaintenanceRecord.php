<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Models\User;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\HasCustomFields;
use App\Support\Concerns\HasNotesAndAttachments;
use App\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    use Auditable, HasCustomFields, HasNotesAndAttachments, Searchable;

    protected $fillable = [
        'reference', 'product_id', 'asset_id', 'quantity', 'type', 'status', 'priority', 'title', 'description',
        'resolution', 'cost', 'job_id', 'assigned_to', 'reported_by', 'due_at', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'quantity' => 'integer',
            'cost' => 'decimal:2',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MaintenanceRecord $record) {
            if (! $record->reference) {
                $next = (int) static::query()->max('id') + 1;
                $record->reference = 'MR-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', MaintenanceStatus::activeValues());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class, 'job_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function toSearchEntry(): ?array
    {
        return [
            'title' => $this->reference.' — '.$this->title,
            'subtitle' => $this->status?->label().' · '.ucfirst($this->type),
            'keywords' => $this->description,
            'codes' => [$this->reference],
            'url' => '/maintenance/'.$this->id,
        ];
    }
}
