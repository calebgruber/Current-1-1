<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetService;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                $next = DB::transaction(function () {
                    DB::table('maintenance_reference_sequence')->where('id', 1)->increment('value');

                    return DB::table('maintenance_reference_sequence')->where('id', 1)->lockForUpdate()->value('value');
                });
                $record->reference = 'MR-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    protected function performInsert(Builder $query)
    {
        return DB::transaction(function () use ($query) {
            $asset = $this->asset_id ? Asset::withTrashed()->lockForUpdate()->find($this->asset_id) : null;
            if ($asset && ($this->status ?? MaintenanceStatus::Open)->isActive()) {
                if ($asset->trashed() || ! in_array($asset->status, [AssetStatus::Available, AssetStatus::Damaged, AssetStatus::InMaintenance], true)) {
                    throw ValidationException::withMessages(['asset_id' => 'This asset cannot enter maintenance in its current state.']);
                }
                if ((int) $asset->product_id !== (int) $this->product_id) {
                    throw ValidationException::withMessages(['asset_id' => 'The asset no longer belongs to this product.']);
                }
            }
            $inserted = parent::performInsert($query);
            if ($inserted && $asset && ! $asset->trashed()
                && ($this->status ?? MaintenanceStatus::Open)->isActive()
                && $asset->status === AssetStatus::Available) {
                app(AssetService::class)->changeStatus($asset, AssetStatus::InMaintenance, $this, $this->reported_by);
            }

            return $inserted;
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', MaintenanceStatus::activeValues());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class, 'job_id')->withTrashed();
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
