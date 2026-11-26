<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Exceptions\TemplateException;
use App\Domain\Documents\Services\Template\ExpressionParser;

/**
 * Compiles the visual designer's JSON blocks into template-language source.
 * The visual and code editors therefore share one renderer, and a visual
 * template can be "ejected" to code at any time.
 */
final class BlockCompiler
{
    public const TYPES = [
        'heading', 'text', 'image', 'logo', 'fields', 'items_table', 'totals', 'signature',
        'spacer', 'divider', 'columns', 'conditional', 'page_break', 'html',
    ];

    private ExpressionParser $parser;

    public function __construct()
    {
        $this->parser = new ExpressionParser;
    }

    /** @param list<array> $blocks */
    public function compile(array $blocks): string
    {
        return implode("\n", array_map(fn (array $b) => $this->block($b), $blocks));
    }

    private function block(array $b): string
    {
        $type = $b['type'] ?? '';
        $p = $b['props'] ?? [];
        $style = $this->style($b['style'] ?? []);
        $attrs = 'class="st-block st-'.$this->attr($type).'" data-block="'.$this->attr((string) ($b['id'] ?? '')).'"'.($style ? ' style="'.$style.'"' : '');

        return match ($type) {
            'heading' => sprintf('<h%1$d %2$s>%3$s</h%1$d>', min(4, max(1, (int) ($p['level'] ?? 1))), $attrs, $this->inline((string) ($p['text'] ?? ''))),
            'text' => sprintf('<div %s>%s</div>', $attrs, $this->decodeTags((string) ($p['html'] ?? ''))),
            'logo' => sprintf('<div %s>{%% if organization.logo_url %%}<img src="{{ organization.logo_url }}" alt="{{ organization.name }}" style="max-height:%dpx">{%% else %%}<strong class="st-org">{{ organization.name }}</strong>{%% endif %%}</div>', $attrs, (int) ($p['height'] ?? 64)),
            'image' => sprintf('<div %s><img src="%s" alt="%s" style="max-width:100%%;width:%s"></div>', $attrs, $this->imageSource((string) ($p['src'] ?? '')), $this->attr((string) ($p['alt'] ?? '')), preg_match('/^\d{1,4}(\.\d+)?(%|px|mm)$/', (string) ($p['width'] ?? '')) ? $p['width'] : 'auto'),
            'fields' => $this->fields($attrs, $p),
            'items_table' => $this->itemsTable($attrs, $p),
            'totals' => $this->totals($attrs, $p),
            'signature' => $this->signature($attrs, $p),
            'spacer' => sprintf('<div %s style="height:%dpx"></div>', $attrs, max(0, (int) ($p['height'] ?? 16))),
            'divider' => sprintf('<hr %s>', $attrs),
            'page_break' => '<div class="st-page-break" style="page-break-after:always"></div>',
            'columns' => $this->columns($attrs, $p),
            'conditional' => sprintf("{%% if %s %%}<div %s>%s</div>{%% endif %%}", $this->expr($p['condition'] ?? 'true'), $attrs, $this->compile($p['blocks'] ?? [])),
            'html' => $this->decodeTags((string) ($p['html'] ?? '')),
            default => '',
        };
    }

    private function fields(string $attrs, array $p): string
    {
        $cols = max(1, min(4, (int) ($p['columns'] ?? 2)));
        $cells = [];
        foreach ($p['fields'] ?? [] as $f) {
            $cells[] = sprintf(
                '<td class="st-field" style="width:%d%%"><div class="st-field-label">%s</div><div class="st-field-value">{{ %s%s }}</div></td>',
                intdiv(100, $cols), $this->text((string) ($f['label'] ?? '')), $this->expr($f['value'] ?? "''"), $this->filterSuffix($f['format'] ?? null),
            );
        }
        $rows = '';
        foreach (array_chunk($cells, $cols) as $chunk) {
            $rows .= '<tr>'.implode('', $chunk).str_repeat('<td></td>', $cols - count($chunk)).'</tr>';
        }

        // Tables rather than CSS grid: dompdf does not support grid layouts.
        return sprintf('<div %s><table class="st-fields" style="width:100%%;border-collapse:collapse">%s</table></div>', $attrs, $rows);
    }

    private function itemsTable(string $attrs, array $p): string
    {
        $source = $this->expr($p['source'] ?? 'items');
        $columns = $p['columns'] ?? [
            ['label' => 'Item', 'value' => 'item.name'],
            ['label' => 'Required', 'value' => 'item.required', 'align' => 'right'],
            ['label' => 'Spare', 'value' => 'item.spare', 'align' => 'right'],
            ['label' => 'Pull', 'value' => 'item.pull', 'align' => 'right'],
        ];
        $head = '';
        $cells = '';
        foreach ($columns as $c) {
            $align = in_array($c['align'] ?? 'left', ['left', 'right', 'center'], true) ? ($c['align'] ?? 'left') : 'left';
            $width = isset($c['width']) && preg_match('/^\d{1,3}(\.\d+)?(%|px|mm)$/', (string) $c['width'])
                ? ' style="text-align:'.$align.';width:'.$c['width'].'"'
                : ' style="text-align:'.$align.'"';
            $head .= '<th'.$width.'>'.$this->text((string) ($c['label'] ?? '')).'</th>';
            $cells .= '<td style="text-align:'.$align.'">{{ '.$this->expr($c['value'] ?? "''").$this->filterSuffix($c['format'] ?? null).' }}</td>';
        }
        $rowClass = ! empty($p['indent_accessories']) ? ' class="{% if item.is_accessory %}st-accessory{% endif %}"' : '';
        $empty = $this->text((string) ($p['empty_text'] ?? 'No items.'));
        $count = count($columns);

        if (! empty($p['group_by_section'])) {
            return sprintf(
                '<div %1$s>{%% for section in sections %%}<h3 class="st-section">{{ section.name }}</h3><table class="st-table"><thead><tr>%2$s</tr></thead><tbody>{%% for item in section.items %%}<tr%5$s>%3$s</tr>{%% endfor %%}</tbody></table>{%% else %%}<p class="st-empty">%4$s</p>{%% endfor %%}</div>',
                $attrs, $head, $cells, $empty, $rowClass,
            );
        }

        return sprintf(
            '<div %1$s><table class="st-table"><thead><tr>%2$s</tr></thead><tbody>{%% for item in %3$s %%}<tr%6$s>%4$s</tr>{%% else %%}<tr><td colspan="%7$d" class="st-empty">%5$s</td></tr>{%% endfor %%}</tbody></table></div>',
            $attrs, $head, $source, $cells, $empty, $rowClass, $count,
        );
    }

    private function totals(string $attrs, array $p): string
    {
        $rows = '';
        foreach ($p['rows'] ?? [] as $r) {
            $rows .= sprintf('<tr%s><th>%s</th><td>{{ %s%s }}</td></tr>', ! empty($r['emphasis']) ? ' class="st-total-strong"' : '', $this->text((string) ($r['label'] ?? '')), $this->expr($r['value'] ?? '0'), $this->filterSuffix($r['format'] ?? 'currency'));
        }

        return sprintf('<div %s><table class="st-totals">%s</table></div>', $attrs, $rows);
    }

    private function signature(string $attrs, array $p): string
    {
        $boxes = '';
        foreach ($p['lines'] ?? [['label' => $p['label'] ?? 'Signature']] as $line) {
            $boxes .= sprintf('<div class="st-signature-box"><div class="st-signature-line"></div><div class="st-signature-label">%s</div>%s</div>', $this->inline((string) ($line['label'] ?? 'Signature')), ! empty($p['show_date']) ? '<div class="st-signature-label">Date: ____________</div>' : '');
        }

        return sprintf('<div %s><div class="st-signatures">%s</div></div>', $attrs, $boxes);
    }

    private function columns(string $attrs, array $p): string
    {
        $cols = '';
        foreach ($p['columns'] ?? [] as $c) {
            $width = preg_match('/^\d{1,3}(\.\d+)?%$/', (string) ($c['width'] ?? '')) ? $c['width'] : 'auto';
            $cols .= sprintf('<td style="vertical-align:top;width:%s;padding-right:12px">%s</td>', $width, $this->compile($c['blocks'] ?? []));
        }

        return sprintf('<div %s><table class="st-columns" style="width:100%%;border-collapse:collapse"><tr>%s</tr></table></div>', $attrs, $cols);
    }

    private function filterSuffix(?string $format): string
    {
        return match ($format) {
            'currency' => ' | currency',
            'number' => ' | number',
            'decimal' => ' | number(2)',
            'date' => ' | date',
            'datetime' => ' | date("datetime")',
            'upper' => ' | upper',
            'multiline' => ' | nl2br',
            'yesno' => ' | yesno',
            default => '',
        };
    }

    /** Validates an expression supplied in block props. */
    private function expr(string $expr): string
    {
        $expr = trim($expr) === '' ? "''" : $expr;
        if (preg_match('/\{\{|\}\}|\{%|%\}|\{#|#\}/', $expr)) {
            throw new TemplateException('Template tags are not allowed inside a field expression: '.$expr);
        }
        $this->parser->parse($expr);

        return $expr;
    }

    /** Static text that may contain {{ variables }} but no block tags. */
    private function inline(string $text): string
    {
        $parts = preg_split('/(\{\{.*?\}\})/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        return implode('', array_map(
            fn ($part) => str_starts_with($part, '{{') ? '{{ '.$this->expr(substr($part, 2, -2)).' }}' : $this->text($part),
            $parts,
        ));
    }

    /**
     * Rich-text editors serialise "{% if a > 1 %}" as "{% if a &gt; 1 %}"; decode
     * entities inside template tags so expressions written in the visual editor parse.
     */
    private function decodeTags(string $html): string
    {
        return preg_replace_callback(
            '/\{\{.*?\}\}|\{%.*?%\}/s',
            fn ($m) => str_replace("\u{00A0}", ' ', html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $html,
        ) ?? $html;
    }

    private function text(string $s): string
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
    }

    private function attr(string $s): string
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
    }

    private function imageSource(string $src): string
    {
        if (preg_match('/^\{\{([^{}]*)\}\}$/s', $src, $matches)) {
            // Escape even a value marked raw before it reaches the HTML attribute.
            return '{{ '.$this->expr($matches[1]).' | escape }}';
        }

        return $this->attr($src);
    }

    private function style(array $s): string
    {
        $map = [
            'align' => fn ($v) => in_array($v, ['left', 'center', 'right', 'justify'], true) ? "text-align:$v" : null,
            'fontSize' => fn ($v) => is_numeric($v) ? 'font-size:'.((float) $v).'pt' : null,
            'color' => fn ($v) => preg_match('/^#[0-9a-f]{3,8}$/i', (string) $v) ? "color:$v" : null,
            'background' => fn ($v) => preg_match('/^#[0-9a-f]{3,8}$/i', (string) $v) ? "background:$v" : null,
            'marginTop' => fn ($v) => is_numeric($v) ? 'margin-top:'.((int) $v).'px' : null,
            'marginBottom' => fn ($v) => is_numeric($v) ? 'margin-bottom:'.((int) $v).'px' : null,
            'padding' => fn ($v) => is_numeric($v) ? 'padding:'.((int) $v).'px' : null,
            'bold' => fn ($v) => $v ? 'font-weight:700' : null,
            'italic' => fn ($v) => $v ? 'font-style:italic' : null,
            'border' => fn ($v) => $v ? 'border:1px solid #d4d4d8' : null,
            'underline' => fn ($v) => $v ? 'text-decoration:underline' : null,
            'uppercase' => fn ($v) => $v ? 'text-transform:uppercase' : null,
            'fontFamily' => fn ($v) => match ($v) {
                'serif' => 'font-family:"DejaVu Serif",Georgia,serif',
                'mono' => 'font-family:"DejaVu Sans Mono",monospace',
                'sans' => 'font-family:"DejaVu Sans",Helvetica,Arial,sans-serif',
                default => null,
            },
        ];
        $out = [];
        foreach ($map as $key => $fn) {
            if (array_key_exists($key, $s) && ($css = $fn($s[$key]))) {
                $out[] = $css;
            }
        }

        return implode(';', $out);
    }
}
