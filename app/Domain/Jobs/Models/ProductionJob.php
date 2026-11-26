<?php

namespace App\Domain\Jobs\Models;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Inventory\Models\Location;
use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\HasCustomFields;
use App\Support\Concerns\HasNotesAndAttachments;
use App\Support\Concerns\Searchable;
use Database\Factories\ProductionJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A "Job" (production, show, event, rental order). Named ProductionJob to avoid
 * clashing with Laravel's queue `jobs` table on shared hosting.
 */
class ProductionJob extends Model
{
    use Auditable, HasCustomFields, HasFactory, HasNotesAndAttachments, Searchable, SoftDeletes;

    protected $table = 'production_jobs';

    protected $fillable = [
        'number', 'name', 'stage', 'workflow_status', 'client_contact_id', 'venue_location_id', 'owner_id',
        'prep_starts_at', 'starts_at', 'ends_at', 'returns_at', 'description', 'color',
    ];

    protected function casts(): array
    {
        return [
            'stage' => JobStage::class,
            'prep_starts_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'returns_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProductionJobFactory
    {
        return ProductionJobFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (ProductionJob $job) {
            if (! $job->number) {
                $next = (int) static::withTrashed()->max('id') + 1;
                $job->number = 'J-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(JobItem::class, 'job_id')->orderBy('sort_order')->orderBy('id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'job_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'client_contact_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'venue_location_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'job_contacts', 'job_id', 'contact_id')->withPivot('role')->withTimestamps();
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(JobStageHistory::class, 'job_id')->latest('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(GeneratedDocument::class, 'documentable')->latest();
    }

    /** The window during which equipment is unavailable to others (prep → return). */
    public function holdStartsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->prep_starts_at ?? $this->starts_at;
    }

    public function holdEndsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->returns_at ?? $this->ends_at;
    }

    public function toSearchEntry(): ?array
    {
        return [
            'title' => $this->number.' — '.$this->name,
            'subtitle' => $this->stage?->label().($this->starts_at ? ' · '.$this->starts_at->toFormattedDateString() : ''),
            'keywords' => $this->description ? mb_substr(strip_tags($this->description), 0, 1000) : null,
            'codes' => [$this->number],
            'url' => '/jobs/'.$this->id,
        ];
    }
}
