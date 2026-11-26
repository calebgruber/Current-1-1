<?php

namespace App\Support\Concerns;

use App\Domain\Jobs\Models\Attachment;
use App\Domain\Jobs\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotesAndAttachments
{
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }
}
