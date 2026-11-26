<?php

namespace App\Domain\Search\Services;

use App\Domain\Search\Models\SearchEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Global search over the denormalised search_index table.
 *
 * Ranking: exact code match (barcode / serial / asset number / job number)
 * > title prefix > FULLTEXT relevance (MySQL) or LIKE match (other drivers).
 * Results are filtered by the viewer's permissions per entity type.
 */
final class SearchService
{
    /** entity morph alias => [label, permission, icon] */
    public const TYPES = [
        'product' => ['Products', 'inventory.products.view', 'cube'],
        'asset' => ['Assets', 'assets.assets.view', 'tag'],
        'job' => ['Jobs', 'jobs.jobs.view', 'briefcase'],
        'reservation' => ['Reservations', 'reservations.reservations.view', 'calendar'],
        'contact' => ['Contacts', 'contacts.contacts.view', 'user'],
        'maintenance' => ['Maintenance', 'maintenance.records.view', 'wrench'],
        'document' => ['Documents', 'documents.documents.view', 'document'],
        'report' => ['Reports', 'reporting.reports.view', 'chart'],
        'location' => ['Locations', 'inventory.locations.view', 'map-pin'],
    ];

    /** @return list<array{type: string, type_label: string, id: int, title: string, subtitle: ?string, url: string, exact: bool, icon: string}> */
    public function search(string $term, ?User $user = null, array $types = [], int $limit = 30): array
    {
        $term = trim(mb_substr($term, 0, 120));
        if ($term === '') {
            return [];
        }

        $allowed = array_keys(array_filter(
            self::TYPES,
            fn ($meta) => $user === null || $user->hasPermission($meta[1]),
        ));
        if ($types !== []) {
            $allowed = array_values(array_intersect($allowed, $types));
        }
        if ($allowed === []) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $lower = mb_strtolower($term);
        $like = '%'.addcslashes($lower, '%_\\').'%';
        $prefix = addcslashes($lower, '%_\\').'%';
        $codeLike = '% '.addcslashes($lower, '%_\\').' %';
        $driver = DB::connection()->getDriverName();

        // SQLite lacks CONCAT() before 3.44 and MySQL treats || as OR by default.
        $padded = $driver === 'mysql'
            ? "concat(' ', coalesce(codes, ''), ' ')"
            : "(' ' || coalesce(codes, '') || ' ')";

        $query = SearchEntry::query()
            ->whereIn('entity_type', $allowed)
            ->select(['entity_type', 'entity_id', 'title', 'subtitle', 'url'])
            ->selectRaw("case when lower({$padded}) like ? then 1 else 0 end as exact_code", [$codeLike])
            ->selectRaw('case when lower(title) like ? then 1 else 0 end as title_prefix', [$prefix]);

        $words = $this->booleanQuery($term);
        if ($driver === 'mysql' && $words !== '') {
            $query->selectRaw('match(title, subtitle, keywords, codes) against (? in boolean mode) as relevance', [$words])
                ->where(function ($q) use ($words, $like) {
                    $q->whereRaw('match(title, subtitle, keywords, codes) against (? in boolean mode)', [$words])
                        ->orWhereRaw('lower(codes) like ?', [$like])
                        ->orWhereRaw('lower(title) like ?', [$like]);
                });
        } else {
            $query->selectRaw('0 as relevance')->where(function ($q) use ($like, $lower) {
                foreach (array_filter(preg_split('/\s+/', $lower) ?: []) as $word) {
                    $w = '%'.addcslashes($word, '%_\\').'%';
                    $q->where(fn ($inner) => $inner
                        ->whereRaw('lower(title) like ?', [$w])
                        ->orWhereRaw('lower(subtitle) like ?', [$w])
                        ->orWhereRaw('lower(keywords) like ?', [$w])
                        ->orWhereRaw('lower(codes) like ?', [$w]));
                }
            });
        }

        $rows = $query->orderByDesc('exact_code')
            ->orderByDesc('title_prefix')
            ->orderByDesc('relevance')
            ->orderBy('title')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'type' => $r->entity_type,
            'type_label' => self::TYPES[$r->entity_type][0],
            'icon' => self::TYPES[$r->entity_type][2],
            'id' => (int) $r->entity_id,
            'title' => $r->title,
            'subtitle' => $r->subtitle,
            'url' => $r->url,
            'exact' => (bool) $r->exact_code,
        ])->all();
    }

    /** Builds a MySQL boolean-mode query: every word required, prefix-matched. */
    public function booleanQuery(string $term): string
    {
        $words = preg_split('/[^\p{L}\p{N}_]+/u', $term) ?: [];
        $words = array_filter($words, fn ($w) => mb_strlen($w) >= 2);

        return implode(' ', array_map(fn ($w) => '+'.$w.'*', array_slice($words, 0, 8)));
    }
}
