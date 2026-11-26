<?php

namespace App\Domain\Reporting\Services;

use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\Documents\Exceptions\TemplateException;
use App\Domain\Documents\Services\TemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Executes report definitions built in the visual report builder.
 *
 * definition = {
 *   columns:    list<column key>                      // detail mode
 *   calculated: list<{key, label, expression, type}>  // per-row formulas (template expression syntax)
 *   filters:    list<{column, operator, value}>
 *   match:      'all' | 'any'
 *   group_by:   list<{column, bucket?: day|week|month|year}>
 *   aggregates: list<{fn: count|sum|avg|min|max, column?, label?}>
 *   sort:       list<{column, direction}>
 *   limit:      int
 *   chart:      {type: none|bar|line|pie|doughnut|number, x?, y?}
 * }
 *
 * Only whitelisted column keys from ReportSourceRegistry are ever turned into SQL;
 * every user-supplied value is bound as a parameter.
 */
final class ReportRunner
{
    public const OPERATORS = [
        'eq' => 'equals', 'neq' => 'does not equal', 'gt' => 'greater than', 'gte' => 'at least',
        'lt' => 'less than', 'lte' => 'at most', 'contains' => 'contains', 'not_contains' => 'does not contain',
        'starts_with' => 'starts with', 'in' => 'is any of', 'not_in' => 'is none of',
        'empty' => 'is empty', 'not_empty' => 'is not empty', 'between' => 'is between',
        'last_days' => 'in the last N days', 'next_days' => 'in the next N days', 'is_true' => 'is yes', 'is_false' => 'is no',
    ];

    public const AGGREGATES = ['count', 'sum', 'avg', 'min', 'max'];

    public const MAX_ROWS = 50000;

    /** @var array<string, list<array>> */
    private array $customFieldCache = [];

    public function __construct(
        private readonly ReportSourceRegistry $registry,
        private readonly TemplateRenderer $renderer,
        private readonly ?ConnectionInterface $connection = null,
    ) {}

    /** @return list<string> validation errors */
    public function validate(string $sourceKey, array $definition): array
    {
        $errors = [];
        if (! $this->registry->has($sourceKey)) {
            return ["Unknown data source \"{$sourceKey}\"."];
        }
        $columns = $this->columns($this->registry->get($sourceKey));
        $known = fn ($c) => is_string($c) && isset($columns[$c]);
        $grouped = ! empty($definition['group_by']);

        if (! $grouped && empty($definition['columns']) && empty($definition['calculated'])) {
            $errors[] = 'Choose at least one column.';
        }
        foreach ($definition['columns'] ?? [] as $c) {
            if (! $known($c)) {
                $errors[] = "Unknown column \"{$c}\".";
            }
        }
        foreach ($definition['filters'] ?? [] as $i => $f) {
            if (! $known($f['column'] ?? null)) {
                $errors[] = 'Filter #'.($i + 1).' uses an unknown column.';
            }
            if (! isset(self::OPERATORS[$f['operator'] ?? ''])) {
                $errors[] = 'Filter #'.($i + 1).' uses an unknown operator.';
            }
        }
        foreach ($definition['group_by'] ?? [] as $g) {
            $col = is_array($g) ? ($g['column'] ?? null) : $g;
            if (! $known($col)) {
                $errors[] = 'Grouping uses an unknown column.';
            }
            if (is_array($g) && isset($g['bucket']) && ! in_array($g['bucket'], ['day', 'week', 'month', 'year'], true)) {
                $errors[] = 'Unknown date grouping.';
            }
        }
        foreach ($definition['aggregates'] ?? [] as $a) {
            if (! in_array($a['fn'] ?? '', self::AGGREGATES, true)) {
                $errors[] = 'Unknown calculation.';
            } elseif (($a['fn'] !== 'count' || isset($a['column'])) && ! $known($a['column'] ?? null)) {
                $errors[] = 'A calculation uses an unknown column.';
            }
        }
        foreach ($definition['calculated'] ?? [] as $c) {
            if (! preg_match('/^[a-z][a-z0-9_]{0,40}$/', (string) ($c['key'] ?? ''))) {
                $errors[] = 'Calculated column keys must be lowercase letters, numbers and underscores.';

                continue;
            }
            $expr = (string) ($c['expression'] ?? '');
            if ($expr === '' || preg_match('/[{}%]{2}|\{%|%\}/', $expr)) {
                $errors[] = "Calculated column \"{$c['key']}\" has an invalid expression.";

                continue;
            }
            if ($msg = $this->renderer->lint('{{ '.$expr.' }}')) {
                $errors[] = "Calculated column \"{$c['key']}\": {$msg}";
            }
        }

        return $errors;
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>, chart: ?array, truncated: bool, grouped: bool}
     */
    public function run(string $sourceKey, array $definition, ?int $limit = null): array
    {
        if ($errors = $this->validate($sourceKey, $definition)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $source = $this->registry->get($sourceKey);
        $columns = $this->columns($source);
        $db = $this->connection ?? DB::connection();
        $query = $db->table($source['table'].' as base');
        if ($source['soft_deletes']) {
            $query->whereNull('base.deleted_at');
        }

        $joins = [];
        $needs = function (string $key) use ($columns, &$joins) {
            if (isset($columns[$key][3])) {
                $joins[$columns[$key][3]] = true;
            }

            return $columns[$key][1];
        };

        $this->applyFilters($query, $definition['filters'] ?? [], ($definition['match'] ?? 'all') === 'any', $needs, $columns);

        $grouped = ! empty($definition['group_by']);
        $out = [];
        $aliases = [];

        if ($grouped) {
            foreach ($definition['group_by'] as $i => $g) {
                $g = is_array($g) ? $g : ['column' => $g];
                $expr = $needs($g['column']);
                if (! empty($g['bucket'])) {
                    $expr = $this->bucket($db->getDriverName(), $expr, $g['bucket']);
                }
                $alias = 'g'.$i;
                $query->selectRaw("{$expr} as {$alias}")->groupByRaw($expr);
                $label = $columns[$g['column']][0].(! empty($g['bucket']) ? ' ('.$g['bucket'].')' : '');
                $out[] = ['key' => $alias, 'label' => $label, 'type' => ! empty($g['bucket']) ? 'string' : $columns[$g['column']][2], 'source' => $g['column']];
            }
            $aggregates = $definition['aggregates'] ?? [['fn' => 'count']];
            if ($aggregates === []) {
                $aggregates = [['fn' => 'count']];
            }
            foreach ($aggregates as $i => $a) {
                $alias = 'a'.$i;
                $expr = isset($a['column']) ? $needs($a['column']) : '*';
                $sql = $a['fn'] === 'count'
                    ? ($expr === '*' ? 'count(*)' : "count({$expr})")
                    : "{$a['fn']}({$expr})";
                $query->selectRaw("{$sql} as {$alias}");
                $type = in_array($a['fn'], ['count'], true) ? 'number' : ($columns[$a['column']][2] ?? 'number');
                $label = $a['label'] ?? ($a['fn'] === 'count' && ! isset($a['column']) ? 'Count' : ucfirst($a['fn']).' of '.$columns[$a['column']][0]);
                $out[] = ['key' => $alias, 'label' => $label, 'type' => $type, 'aggregate' => $a['fn']];
            }
        } else {
            foreach ($definition['columns'] as $i => $key) {
                $alias = 'c'.$i;
                $query->selectRaw($needs($key)." as {$alias}");
                $out[] = ['key' => $alias, 'label' => $columns[$key][0], 'type' => $columns[$key][2], 'source' => $key];
                $aliases[$key] = $alias;
            }
            if (in_array('id', array_keys($columns), true) && ! isset($aliases['id'])) {
                $query->selectRaw('base.id as _id');
            }
        }

        foreach ($definition['sort'] ?? [] as $s) {
            $dir = strtolower($s['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $target = $this->sortTarget($s['column'] ?? '', $out);
            if ($target !== null) {
                $query->orderBy($target, $dir);
            } elseif (! $grouped && isset($columns[$s['column'] ?? ''])) {
                $query->orderByRaw($needs($s['column']).' '.$dir);
            }
        }

        $joined = [];
        foreach (array_keys($joins) as $alias) {
            $this->join($query, $source, $alias, $joined);
        }

        $max = min(self::MAX_ROWS, max(1, (int) ($limit ?? $definition['limit'] ?? self::MAX_ROWS)));
        $rows = $query->limit($max + 1)->get()->map(fn ($r) => (array) $r)->all();
        $truncated = count($rows) > $max;
        $rows = array_slice($rows, 0, $max);

        // Map aliases back to readable keys and cast types.
        $result = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($out as $col) {
                $item[$col['key']] = $this->cast($row[$col['key']] ?? null, $col['type']);
            }
            if (isset($row['_id'])) {
                $item['_id'] = (int) $row['_id'];
            }
            $result[] = $item;
        }

        // Calculated (formula) columns can reference any visible column by its source key.
        foreach ($definition['calculated'] ?? [] as $calc) {
            $key = 'x_'.$calc['key'];
            $out[] = ['key' => $key, 'label' => $calc['label'] ?? $calc['key'], 'type' => $calc['type'] ?? 'number', 'calculated' => true];
            foreach ($result as &$item) {
                $ctx = [];
                foreach ($out as $col) {
                    if (isset($col['source']) && ! str_starts_with($col['source'], 'cf:')) {
                        $ctx[$col['source']] = $item[$col['key']] ?? null;
                    } elseif (isset($col['aggregate'])) {
                        $ctx[$col['key']] = $item[$col['key']] ?? null;
                    } elseif (! empty($col['calculated']) && $col['key'] !== $key) {
                        $ctx[substr($col['key'], 2)] = $item[$col['key']] ?? null;
                    }
                }
                try {
                    $value = html_entity_decode($this->renderer->render('{{ '.$calc['expression'].' }}', $ctx), ENT_QUOTES);
                } catch (TemplateException) {
                    $value = null;
                }
                $item[$key] = $this->cast($value === '' ? null : $value, $calc['type'] ?? 'number');
            }
            unset($item);
        }

        return [
            'columns' => array_values(array_map(fn ($c) => array_intersect_key($c, array_flip(['key', 'label', 'type'])), $out)),
            'rows' => $result,
            'totals' => $this->totals($out, $result),
            'chart' => $this->chart($definition['chart'] ?? null, $out, $result),
            'truncated' => $truncated,
            'grouped' => $grouped,
        ];
    }

    /** Headers + list rows for export writers. */
    public function tabular(array $result): array
    {
        $headers = array_column($result['columns'], 'label');
        $keys = array_column($result['columns'], 'key');
        $rows = array_map(fn ($r) => array_map(fn ($k) => $r[$k] ?? null, $keys), $result['rows']);

        return [$headers, $rows];
    }

    /** Source columns + custom field pseudo-columns ("cf:key"). */
    public function columns(array $source): array
    {
        $columns = $source['columns'];
        if (! empty($source['entity'])) {
            foreach ($this->customFieldDefinitions($source['entity']) as $def) {
                $col = $def['column'];
                $sql = sprintf(
                    '(select cfv.%s from custom_field_values cfv where cfv.definition_id = %d and cfv.entity_type = %s and cfv.entity_id = base.id limit 1)',
                    $col, (int) $def['id'], "'".str_replace("'", "''", $source['entity'])."'",
                );
                $columns['cf:'.$def['key']] = [$def['label'], $sql, $def['report_type']];
            }
        }

        return $columns;
    }

    /** @return list<array{id:int, key:string, label:string, column:string, report_type:string}> */
    public function customFieldDefinitions(string $entity): array
    {
        if (! isset($this->customFieldCache[$entity])) {
            $this->customFieldCache[$entity] = CustomFieldDefinition::query()->where('entity_type', $entity)->orderBy('sort_order')->get()
                ->map(fn (CustomFieldDefinition $d) => [
                    'id' => $d->id,
                    'key' => $d->key,
                    'label' => $d->label,
                    'column' => $d->type->storageColumn(),
                    'report_type' => match ($d->type->storageColumn()) {
                        'value_number' => $d->type->value === 'currency' ? 'currency' : 'number',
                        'value_date' => $d->type->value === 'date' ? 'date' : 'datetime',
                        default => 'string',
                    },
                ])->all();
        }

        return $this->customFieldCache[$entity];
    }

    private function applyFilters(Builder $query, array $filters, bool $any, callable $needs, array $columns): void
    {
        if ($filters === []) {
            return;
        }
        $query->where(function (Builder $q) use ($filters, $any, $needs, $columns) {
            foreach ($filters as $f) {
                $expr = $needs($f['column']);
                $type = $columns[$f['column']][2];
                $value = $f['value'] ?? null;
                $method = $any ? 'orWhereRaw' : 'whereRaw';
                $now = CarbonImmutable::now();

                match ($f['operator']) {
                    'eq' => $q->{$method}("{$expr} = ?", [$this->value($value, $type)]),
                    'neq' => $q->{$method}("({$expr} <> ? or {$expr} is null)", [$this->value($value, $type)]),
                    'gt' => $q->{$method}("{$expr} > ?", [$this->value($value, $type)]),
                    'gte' => $q->{$method}("{$expr} >= ?", [$this->value($value, $type)]),
                    'lt' => $q->{$method}("{$expr} < ?", [$this->value($value, $type)]),
                    'lte' => $q->{$method}("{$expr} <= ?", [$this->value($value, $type)]),
                    'contains' => $q->{$method}("lower({$expr}) like ?", ['%'.$this->like($value).'%']),
                    'not_contains' => $q->{$method}("(lower({$expr}) not like ? or {$expr} is null)", ['%'.$this->like($value).'%']),
                    'starts_with' => $q->{$method}("lower({$expr}) like ?", [$this->like($value).'%']),
                    'in', 'not_in' => $this->in($q, $method, $expr, (array) $value, $type, $f['operator'] === 'not_in'),
                    'empty' => $q->{$method}("({$expr} is null or {$expr} = '')"),
                    'not_empty' => $q->{$method}("({$expr} is not null and {$expr} <> '')"),
                    'between' => $q->{$method}("{$expr} between ? and ?", [$this->value($value[0] ?? null, $type), $this->value($value[1] ?? null, $type, true)]),
                    'last_days' => $q->{$method}("{$expr} between ? and ?", [$now->subDays(max(0, (int) $value))->startOfDay()->toDateTimeString(), $now->toDateTimeString()]),
                    'next_days' => $q->{$method}("{$expr} between ? and ?", [$now->toDateTimeString(), $now->addDays(max(0, (int) $value))->endOfDay()->toDateTimeString()]),
                    'is_true' => $q->{$method}("{$expr} = ?", [1]),
                    'is_false' => $q->{$method}("({$expr} = ? or {$expr} is null)", [0]),
                };
            }
        });
    }

    private function in(Builder $q, string $method, string $expr, array $values, string $type, bool $negate): void
    {
        $values = array_values(array_map(fn ($v) => $this->value($v, $type), $values));
        if ($values === []) {
            $q->{$method}($negate ? '1 = 1' : '1 = 0');

            return;
        }
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $q->{$method}($negate ? "({$expr} not in ({$marks}) or {$expr} is null)" : "{$expr} in ({$marks})", $values);
    }

    private function value(mixed $value, string $type, bool $endOfDay = false): mixed
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return match ($type) {
            'number', 'currency' => is_numeric($value) ? $value + 0 : 0,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL) ? 1 : 0,
            'date', 'datetime' => $value ? ($endOfDay && strlen((string) $value) === 10
                ? CarbonImmutable::parse((string) $value)->endOfDay()->toDateTimeString()
                : CarbonImmutable::parse((string) $value)->toDateTimeString()) : null,
            default => is_scalar($value) ? (string) $value : '',
        };
    }

    private function like(mixed $value): string
    {
        return addcslashes(mb_strtolower(is_scalar($value) ? (string) $value : ''), '%_\\');
    }

    private function bucket(string $driver, string $expr, string $bucket): string
    {
        if ($driver === 'sqlite') {
            return match ($bucket) {
                'day' => "strftime('%Y-%m-%d', {$expr})",
                'week' => "strftime('%Y-W%W', {$expr})",
                'month' => "strftime('%Y-%m', {$expr})",
                'year' => "strftime('%Y', {$expr})",
            };
        }
        if ($driver === 'pgsql') {
            return match ($bucket) {
                'day' => "to_char({$expr}, 'YYYY-MM-DD')",
                'week' => "to_char({$expr}, 'IYYY-\"W\"IW')",
                'month' => "to_char({$expr}, 'YYYY-MM')",
                'year' => "to_char({$expr}, 'YYYY')",
            };
        }

        return match ($bucket) {
            'day' => "date_format({$expr}, '%Y-%m-%d')",
            'week' => "date_format({$expr}, '%x-W%v')",
            'month' => "date_format({$expr}, '%Y-%m')",
            'year' => "date_format({$expr}, '%Y')",
        };
    }

    /** Adds a whitelisted join (and any join it depends on) exactly once. */
    private function join(Builder $query, array $source, string $alias, array &$joined): void
    {
        if (isset($joined[$alias])) {
            return;
        }
        [$table, $local, $foreign] = $source['joins'][$alias];
        if (isset($source['joins'][$alias][3])) {
            $this->join($query, $source, $source['joins'][$alias][3], $joined);
        }
        $query->leftJoin("{$table} as {$alias}", $local, '=', "{$alias}.{$foreign}");
        $joined[$alias] = true;
    }

    private function sortTarget(string $column, array $out): ?string
    {
        foreach ($out as $col) {
            if ($col['key'] === $column || ($col['source'] ?? null) === $column) {
                return $col['key'];
            }
        }

        return null;
    }

    private function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($value) ? ((float) $value == (int) $value && ! str_contains((string) $value, 'e') ? (int) $value : round((float) $value, 4)) : null,
            'currency' => is_numeric($value) ? round((float) $value, 2) : null,
            'boolean' => (bool) $value,
            'date' => substr((string) $value, 0, 10),
            default => is_string($value) ? $value : (is_scalar($value) ? $value : json_encode($value)),
        };
    }

    private function totals(array $columns, array $rows): array
    {
        $totals = [];
        foreach ($columns as $col) {
            if (in_array($col['type'], ['number', 'currency'], true) && ($col['aggregate'] ?? 'sum') !== 'avg' && ! in_array($col['aggregate'] ?? null, ['min', 'max'], true)) {
                if (! isset($col['source']) || $col['source'] !== 'id') {
                    $totals[$col['key']] = round(array_sum(array_map(fn ($r) => (float) ($r[$col['key']] ?? 0), $rows)), 2);
                }
            }
        }

        return $totals;
    }

    private function chart(?array $chart, array $columns, array $rows): ?array
    {
        if (! $chart || ($chart['type'] ?? 'none') === 'none' || $rows === []) {
            return null;
        }
        $find = fn (?string $k) => $k === null ? null : collect($columns)->first(fn ($c) => $c['key'] === $k || ($c['source'] ?? null) === $k);
        $x = $find($chart['x'] ?? null) ?? $columns[0];
        $ys = array_values(array_filter(
            array_map($find, (array) ($chart['y'] ?? [])),
        )) ?: array_values(array_filter($columns, fn ($c) => in_array($c['type'], ['number', 'currency'], true) && $c['key'] !== $x['key']));
        if ($ys === []) {
            return null;
        }

        return [
            'type' => $chart['type'],
            'labels' => array_map(fn ($r) => (string) ($r[$x['key']] ?? '—'), array_slice($rows, 0, 200)),
            'datasets' => array_map(fn ($y) => [
                'label' => $y['label'],
                'data' => array_map(fn ($r) => (float) ($r[$y['key']] ?? 0), array_slice($rows, 0, 200)),
            ], array_slice($ys, 0, 4)),
        ];
    }
}
