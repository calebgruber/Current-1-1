<?php

namespace App\Domain\Documents\Models;

use App\Models\User;
use App\Support\Concerns\HasCustomFields;
use App\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeneratedDocument extends Model
{
    use HasCustomFields, Searchable;

    protected $fillable = ['number', 'template_id', 'type', 'documentable_type', 'documentable_id', 'title', 'html', 'pdf_path', 'created_by'];

    protected $hidden = ['html'];

    protected static function booted(): void
    {
        static::creating(function (GeneratedDocument $doc) {
            if (! $doc->number) {
                $next = (int) static::query()->max('id') + 1;
                $doc->number = 'D-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toSearchEntry(): ?array
    {
        return [
            'title' => $this->number.' — '.$this->title,
            'subtitle' => DocumentTemplate::TYPES[$this->type] ?? $this->type,
            'keywords' => null,
            'codes' => [$this->number],
            'url' => '/documents/'.$this->id,
        ];
    }
}
