<?php

namespace App\Domain\Reservations\Models;

use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\User;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\HasCustomFields;
use App\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use Auditable, HasCustomFields, Searchable;

    protected $fillable = [
        'product_id', 'job_id', 'job_item_id', 'name', 'quantity', 'spare_quantity', 'starts_at', 'ends_at',
        'status', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'quantity' => 'integer',
            'spare_quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeOverlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function scopeHolding(Builder $query): Builder
    {
        return $query->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Tentative->value]);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class, 'job_id');
    }

    public function jobItem(): BelongsTo
    {
        return $this->belongsTo(JobItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalQuantity(): int
    {
        return (int) $this->quantity + (int) $this->spare_quantity;
    }

    public function label(): string
    {
        return $this->job ? $this->job->number.' — '.$this->job->name : ($this->name ?: 'Reservation #'.$this->id);
    }

    public function toSearchEntry(): ?array
    {
        if ($this->job_id) {
            return null; // job-generated reservations are reachable through their job
        }

        return [
            'title' => $this->name ?: 'Reservation #'.$this->id,
            'subtitle' => ($this->product?->name ?? '').' · '.$this->totalQuantity().' units',
            'keywords' => $this->notes,
            'codes' => [],
            'url' => '/reservations/'.$this->id,
        ];
    }
}
