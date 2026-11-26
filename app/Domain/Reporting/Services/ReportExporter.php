<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Documents\Services\DocumentRenderer;
use App\Domain\Reporting\Models\Report;
use App\Domain\Reporting\Services\Export\CsvWriter;
use App\Domain\Reporting\Services\Export\XlsxWriter;
use App\Domain\Settings\Services\SettingsRepository;
use Illuminate\Support\Str;

/** Runs a saved report and serialises it as CSV, XLSX or PDF. */
final class ReportExporter
{
    public const FORMATS = [
        'csv' => ['text/csv; charset=UTF-8', 'csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'pdf' => ['application/pdf', 'pdf'],
    ];

    public function __construct(
        private readonly ReportRunner $runner,
        private readonly CsvWriter $csv,
        private readonly XlsxWriter $xlsx,
        private readonly DocumentRenderer $renderer,
        private readonly SettingsRepository $settings,
    ) {}

    /** @return array{content: string, mime: string, filename: string, rows: int} */
    public function export(Report $report, string $format): array
    {
        $definition = ReportDefinition::normalize($report->definition ?? []);
        $result = $this->runner->run($report->source, ReportDefinition::forRunner($definition), ReportRunner::MAX_ROWS);
        [$headers, $rows] = $this->runner->tabular($result);
        if ($result['totals'] !== []) {
            $keys = array_column($result['columns'], 'key');
            $totalsRow = array_map(fn ($k, $i) => $i === 0 ? 'Total' : ($result['totals'][$k] ?? null), $keys, array_keys($keys));
        }

        $content = match ($format) {
            'csv' => $this->csv->write($headers, isset($totalsRow) ? [...$rows, $totalsRow] : $rows),
            'xlsx' => $this->xlsx->write($headers, isset($totalsRow) ? [...$rows, $totalsRow] : $rows, mb_substr($report->name, 0, 31)),
            'pdf' => $this->pdf($report, $result),
        };
        [$mime, $ext] = self::FORMATS[$format];

        return [
            'content' => $content,
            'mime' => $mime,
            'filename' => (Str::slug($report->name) ?: 'report').'-'.now()->format('Y-m-d').'.'.$ext,
            'rows' => count($rows),
        ];
    }

    private function pdf(Report $report, array $result): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $currency = (string) $this->settings->get('general.currency', 'USD');
        $format = function ($value, string $type) use ($currency) {
            if ($value === null || $value === '') {
                return '';
            }

            return match ($type) {
                'currency' => $currency.' '.number_format((float) $value, 2),
                'number' => is_numeric($value) ? (floor((float) $value) == (float) $value ? number_format((float) $value) : number_format((float) $value, 2)) : $value,
                'boolean' => $value ? 'Yes' : 'No',
                default => is_array($value) ? implode(', ', $value) : $value,
            };
        };

        $head = implode('', array_map(fn ($c) => '<th class="'.(in_array($c['type'], ['number', 'currency'], true) ? 'r' : '').'">'.$e($c['label']).'</th>', $result['columns']));
        $body = '';
        foreach ($result['rows'] as $row) {
            $body .= '<tr>'.implode('', array_map(fn ($c) => '<td class="'.(in_array($c['type'], ['number', 'currency'], true) ? 'r' : '').'">'.$e($format($row[$c['key']] ?? null, $c['type'])).'</td>', $result['columns'])).'</tr>';
        }
        $foot = '';
        if ($result['totals'] !== []) {
            $foot = '<tfoot><tr>'.implode('', array_map(fn ($c, $i) => '<td class="r">'.($i === 0 && ! isset($result['totals'][$c['key']]) ? 'Total' : $e($format($result['totals'][$c['key']] ?? null, $c['type']))).'</td>', $result['columns'], array_keys($result['columns']))).'</tr></tfoot>';
        }
        $org = $e((string) $this->settings->get('general.organization_name', ''));
        $landscape = count($result['columns']) > 6;
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            .'@page{margin:12mm 10mm}body{font-family:"DejaVu Sans",sans-serif;font-size:8.5pt;color:#18181b}'
            .'h1{font-size:15pt;margin:0 0 2px}.meta{color:#71717a;margin-bottom:10px}'
            .'table{width:100%;border-collapse:collapse}th{background:#f4f4f5;text-align:left;font-weight:bold;border-bottom:1px solid #d4d4d8;padding:4px 5px}'
            .'td{border-bottom:1px solid #e4e4e7;padding:3px 5px}.r{text-align:right}tfoot td{font-weight:bold;border-top:1px solid #a1a1aa}'
            .'</style></head><body>'
            .'<h1>'.$e($report->name).'</h1><div class="meta">'.$org.' · Generated '.$e(now()->format('M j, Y g:i A')).' · '.count($result['rows']).' rows'.($result['truncated'] ? ' (truncated)' : '').'</div>'
            .($report->description ? '<p>'.$e($report->description).'</p>' : '')
            .'<table><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody>'.$foot.'</table></body></html>';

        return $this->renderer->pdf($html, 'letter', $landscape ? 'landscape' : 'portrait');
    }
}
