<?php

namespace App\Domain\Contacts\Models;

use App\Domain\Jobs\Models\ProductionJob;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\HasCustomFields;
use App\Support\Concerns\HasNotesAndAttachments;
use App\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use Auditable, HasCustomFields, HasNotesAndAttachments, Searchable, SoftDeletes;

    protected $fillable = ['type', 'organization_id', 'name', 'email', 'phone', 'title', 'address', 'notes'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'organization_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Contact::class, 'organization_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class, 'client_contact_id');
    }

    public function toSearchEntry(): ?array
    {
        return [
            'title' => $this->name,
            'subtitle' => trim(implode(' · ', array_filter([ucfirst($this->type), $this->email, $this->phone]))),
            'keywords' => $this->title,
            'codes' => [],
            'url' => '/contacts/'.$this->id,
        ];
    }
}
