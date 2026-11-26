<?php

namespace App\Domain\Search\Console;

use App\Domain\Search\Models\SearchEntry;
use App\Support\Concerns\Searchable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;

class ReindexSearch extends Command
{
    protected $signature = 'stagetrack:search-reindex';

    protected $description = 'Rebuild the global search index from all searchable records';

    public function handle(): int
    {
        SearchEntry::query()->delete();
        foreach (Relation::morphMap() as $alias => $class) {
            if (! in_array(Searchable::class, class_uses_recursive($class), true)) {
                continue;
            }
            $count = 0;
            $class::query()->chunkById(500, function ($models) use (&$count) {
                foreach ($models as $model) {
                    $model->syncSearchEntry();
                    $count++;
                }
            });
            $this->line("Indexed {$count} {$alias} record(s).");
        }

        return self::SUCCESS;
    }
}
