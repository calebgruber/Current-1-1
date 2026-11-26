<?php

namespace App\Support\Concerns;

use App\Domain\Search\Models\SearchEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the global search_index table in sync with the model.
 * Implementing models must define toSearchEntry(): ?array returning
 * ['title' => ..., 'subtitle' => ..., 'keywords' => ..., 'codes' => ..., 'url' => ...].
 */
trait Searchable
{
    public static function bootSearchable(): void
    {
        static::saved(fn (Model $model) => $model->syncSearchEntry());
        static::deleted(fn (Model $model) => SearchEntry::query()
            ->where('entity_type', $model->getMorphClass())
            ->where('entity_id', $model->getKey())
            ->delete());
    }

    public function syncSearchEntry(): void
    {
        $entry = $this->toSearchEntry();
        $keys = ['entity_type' => $this->getMorphClass(), 'entity_id' => $this->getKey()];

        if ($entry === null) {
            SearchEntry::query()->where($keys)->delete();

            return;
        }

        SearchEntry::query()->updateOrCreate($keys, [
            'title' => mb_substr((string) $entry['title'], 0, 255),
            'subtitle' => isset($entry['subtitle']) ? mb_substr((string) $entry['subtitle'], 0, 255) : null,
            'keywords' => $entry['keywords'] ?? null,
            'codes' => isset($entry['codes']) ? mb_substr(implode(' ', array_filter((array) $entry['codes'])), 0, 512) : null,
            'url' => $entry['url'],
        ]);
    }

    /** @return array{title: string, subtitle?: ?string, keywords?: ?string, codes?: array<int, ?string>, url: string}|null */
    abstract public function toSearchEntry(): ?array;
}
