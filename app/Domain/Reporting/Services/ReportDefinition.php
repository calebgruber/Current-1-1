<?php

namespace App\Domain\Reporting\Services;

/**
 * Normalises a report-builder definition into the exact shape ReportRunner expects
 * (plus the builder's presentation settings under "view").
 */
final class ReportDefinition
{
    public const VIEW_MODES = ['table', 'chart', 'dashboard'];

    public const CHART_TYPES = ['none', 'bar', 'line', 'pie', 'doughnut', 'number'];

    public const KPI_FUNCTIONS = ['count', 'sum', 'avg', 'min', 'max'];

    public static function normalize(array $d): array
    {
        $list = fn ($v) => is_array($v) ? array_values($v) : [];
        $str = fn ($v) => is_scalar($v) ? (string) $v : '';

        $chart = is_array($d['chart'] ?? null) ? $d['chart'] : [];
        $view = is_array($d['view'] ?? null) ? $d['view'] : [];

        $out = [
            'columns' => array_values(array_filter(array_map($str, $list($d['columns'] ?? [])), fn ($c) => $c !== '')),
            'calculated' => array_map(fn ($c) => [
                'key' => $str($c['key'] ?? ''),
                'label' => mb_substr($str($c['label'] ?? ($c['key'] ?? '')), 0, 80),
                'expression' => mb_substr($str($c['expression'] ?? ''), 0, 500),
                'type' => in_array($c['type'] ?? null, ['number', 'currency', 'string', 'date'], true) ? $c['type'] : 'number',
            ], array_filter($list($d['calculated'] ?? []), 'is_array')),
            'filters' => array_map(fn ($f) => [
                'column' => $str($f['column'] ?? ''),
                'operator' => $str($f['operator'] ?? 'eq'),
                'value' => is_array($f['value'] ?? null) ? array_values(array_map($str, $f['value'])) : ($f['value'] ?? null),
            ], array_filter($list($d['filters'] ?? []), 'is_array')),
            'match' => ($d['match'] ?? 'all') === 'any' ? 'any' : 'all',
            'group_by' => array_map(fn ($g) => is_array($g)
                ? array_filter(['column' => $str($g['column'] ?? ''), 'bucket' => ($g['bucket'] ?? null) ?: null], fn ($v) => $v !== null)
                : ['column' => $str($g)], $list($d['group_by'] ?? [])),
            'aggregates' => array_map(fn ($a) => array_filter([
                'fn' => $str($a['fn'] ?? 'count'),
                'column' => ($a['column'] ?? null) ? $str($a['column']) : null,
                'label' => ($a['label'] ?? null) ? mb_substr($str($a['label']), 0, 80) : null,
            ], fn ($v) => $v !== null), array_filter($list($d['aggregates'] ?? []), 'is_array')),
            'sort' => array_map(fn ($s) => [
                'column' => $str($s['column'] ?? ''),
                'direction' => ($s['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            ], array_filter($list($d['sort'] ?? []), 'is_array')),
            'limit' => max(1, min(ReportRunner::MAX_ROWS, (int) ($d['limit'] ?? 1000))),
            'chart' => [
                'type' => in_array($chart['type'] ?? 'none', self::CHART_TYPES, true) ? $chart['type'] : 'none',
                'x' => ($chart['x'] ?? null) ? $str($chart['x']) : null,
                'y' => array_values(array_filter(array_map($str, $list($chart['y'] ?? [])))),
            ],
            'view' => [
                'mode' => in_array($view['mode'] ?? 'table', self::VIEW_MODES, true) ? $view['mode'] : 'table',
                'kpis' => array_slice(array_map(fn ($k) => [
                    'label' => mb_substr($str($k['label'] ?? ''), 0, 60),
                    'fn' => in_array($k['fn'] ?? 'count', self::KPI_FUNCTIONS, true) ? $k['fn'] : 'count',
                    'column' => ($k['column'] ?? null) ? $str($k['column']) : null,
                    'format' => in_array($k['format'] ?? 'number', ['number', 'currency', 'percent'], true) ? ($k['format'] ?? 'number') : 'number',
                ], array_filter($list($view['kpis'] ?? []), 'is_array')), 0, 6),
            ],
        ];

        return $out;
    }

    /** Extra validation for builder-only settings (runner validates the rest). */
    public static function errors(array $d): array
    {
        $errors = [];
        if (count($d['columns']) > 60) {
            $errors[] = 'Reports are limited to 60 columns.';
        }
        if (count($d['filters']) > 30) {
            $errors[] = 'Reports are limited to 30 filters.';
        }
        if (count($d['group_by']) > 3) {
            $errors[] = 'Group by at most three fields.';
        }
        if (count($d['calculated']) > 20) {
            $errors[] = 'Reports are limited to 20 calculated columns.';
        }

        return $errors;
    }

    /** Runner input (strips presentation-only keys). */
    public static function forRunner(array $d): array
    {
        $r = $d;
        unset($r['view']);

        return $r;
    }
}
