<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Exceptions\TemplateException;
use App\Domain\Documents\Services\Template\ExpressionParser;
use App\Domain\Documents\Services\Template\SafeString;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Sandboxed template language for user-authored documents.
 *
 *   {{ job.name }}                     escaped output
 *   {{ item.price * item.qty | currency }}
 *   {{ notes | nl2br }}  {{ logo_html | raw }}
 *   {% if job.stage == 'quote' %}…{% elseif x %}…{% else %}…{% endif %}
 *   {% for item in items %}{{ loop.index }}. {{ item.name }}{% else %}None{% endfor %}
 *   {% set total = sum(items, 'total') %}
 *   {# comment #}
 *
 * Templates can only read array data supplied by the application. There are
 * no method calls, includes or PHP execution, and iteration/output sizes are
 * capped to keep a bad template from exhausting the server.
 */
final class TemplateRenderer
{
    public const MAX_ITERATIONS = 50000;

    public const MAX_OUTPUT = 5_000_000;

    public const FILTERS = ['currency', 'number', 'date', 'upper', 'lower', 'title', 'default', 'nl2br', 'raw', 'escape', 'escape_attr', 'round', 'join', 'length', 'abs', 'truncate', 'percent', 'yesno'];

    public const FUNCTIONS = ['sum', 'count', 'round', 'min', 'max', 'now', 'concat', 'avg', 'ceil', 'floor'];

    private ExpressionParser $parser;

    private int $iterations = 0;

    private array $options = [];

    public function __construct()
    {
        $this->parser = new ExpressionParser;
    }

    /**
     * @param  array{currency?: string, locale?: string, date_format?: string, datetime_format?: string, timezone?: string}  $options
     */
    public function render(string $template, array $context, array $options = []): string
    {
        $this->options = $options + ['currency' => '$', 'date_format' => 'M j, Y', 'datetime_format' => 'M j, Y g:i A', 'timezone' => 'UTC'];
        $this->iterations = 0;
        $tree = $this->compile($template);

        return $this->renderNodes($tree, $context);
    }

    /** Parses without rendering; returns the error message or null. */
    public function lint(string $template): ?string
    {
        try {
            $this->compile($template);

            return null;
        } catch (TemplateException $e) {
            return $e->getMessage();
        }
    }

    /** @return list<array> */
    public function compile(string $template): array
    {
        $parts = preg_split('/(\{\{.*?\}\}|\{%.*?%\}|\{#.*?#\})/s', $template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
        // Iterative builder with an explicit stack of open blocks.
        $blocks = [['kind' => 'root', 'nodes' => []]];
        foreach ($parts as [$chunk, $offset]) {
            if ($chunk === '') {
                continue;
            }
            $line = substr_count($template, "\n", 0, $offset) + 1;
            if (str_starts_with($chunk, '{#')) {
                continue;
            }
            if (str_starts_with($chunk, '{{')) {
                $blocks[count($blocks) - 1]['nodes'][] = ['out', $this->parser->parse(substr($chunk, 2, -2), $line)];

                continue;
            }
            if (! str_starts_with($chunk, '{%')) {
                $blocks[count($blocks) - 1]['nodes'][] = ['text', $chunk];

                continue;
            }

            $tag = trim(substr($chunk, 2, -2));
            $word = strtolower(strtok($tag, " \t\n") ?: '');
            $rest = trim(substr($tag, strlen($word)));
            $top = count($blocks) - 1;

            switch ($word) {
                case 'if':
                    $blocks[] = ['kind' => 'if', 'branches' => [[$this->parser->parse($rest, $line), []]], 'else' => null, 'line' => $line, 'nodes' => []];
                    break;
                case 'elseif':
                case 'elif':
                    if ($blocks[$top]['kind'] !== 'if' || $blocks[$top]['else'] !== null) {
                        throw new TemplateException('Unexpected {% elseif %}', $line);
                    }
                    $this->closeBranch($blocks[$top]);
                    $blocks[$top]['branches'][] = [$this->parser->parse($rest, $line), []];
                    break;
                case 'else':
                    if ($blocks[$top]['kind'] === 'if' && $blocks[$top]['else'] === null) {
                        $this->closeBranch($blocks[$top]);
                        $blocks[$top]['else'] = [];
                    } elseif ($blocks[$top]['kind'] === 'for' && $blocks[$top]['else'] === null) {
                        $blocks[$top]['body'] = $blocks[$top]['nodes'];
                        $blocks[$top]['nodes'] = [];
                        $blocks[$top]['else'] = [];
                    } else {
                        throw new TemplateException('Unexpected {% else %}', $line);
                    }
                    break;
                case 'endif':
                    if ($blocks[$top]['kind'] !== 'if') {
                        throw new TemplateException('Unexpected {% endif %}', $line);
                    }
                    $block = array_pop($blocks);
                    if ($block['else'] === null) {
                        $this->closeBranch($block);
                    } else {
                        $block['else'] = $block['nodes'];
                    }
                    $blocks[count($blocks) - 1]['nodes'][] = ['if', $block['branches'], $block['else'] ?? []];
                    break;
                case 'for':
                    if (! preg_match('/^([A-Za-z_]\w*)(?:\s*,\s*([A-Za-z_]\w*))?\s+in\s+(.+)$/s', $rest, $m)) {
                        throw new TemplateException('Invalid for loop, expected {% for item in list %}', $line);
                    }
                    [$keyVar, $valVar] = $m[2] !== '' ? [$m[1], $m[2]] : [null, $m[1]];
                    $blocks[] = ['kind' => 'for', 'key' => $keyVar, 'var' => $valVar, 'list' => $this->parser->parse($m[3], $line), 'body' => null, 'else' => null, 'line' => $line, 'nodes' => []];
                    break;
                case 'endfor':
                    if ($blocks[$top]['kind'] !== 'for') {
                        throw new TemplateException('Unexpected {% endfor %}', $line);
                    }
                    $block = array_pop($blocks);
                    $body = $block['else'] === null ? $block['nodes'] : $block['body'];
                    $else = $block['else'] === null ? [] : $block['nodes'];
                    $blocks[count($blocks) - 1]['nodes'][] = ['for', $block['key'], $block['var'], $block['list'], $body, $else];
                    break;
                case 'set':
                    if (! preg_match('/^([A-Za-z_]\w*)\s*=\s*(.+)$/s', $rest, $m)) {
                        throw new TemplateException('Invalid set, expected {% set name = expression %}', $line);
                    }
                    $blocks[$top]['nodes'][] = ['set', $m[1], $this->parser->parse($m[2], $line)];
                    break;
                default:
                    throw new TemplateException('Unknown tag "'.$word.'"', $line);
            }
        }

        if (count($blocks) > 1) {
            $open = end($blocks);
            throw new TemplateException('Unclosed {% '.$open['kind'].' %} block', $open['line'] ?? null);
        }

        return $blocks[0]['nodes'];
    }

    private function closeBranch(array &$block): void
    {
        $last = count($block['branches']) - 1;
        $block['branches'][$last][1] = $block['nodes'];
        $block['nodes'] = [];
    }

    private function renderNodes(array $nodes, array &$ctx): string
    {
        $out = '';
        foreach ($nodes as $node) {
            switch ($node[0]) {
                case 'text':
                    $out .= $node[1];
                    break;
                case 'out':
                    $out .= $this->stringify($this->evaluate($node[1], $ctx));
                    break;
                case 'set':
                    $ctx[$node[1]] = $this->evaluate($node[2], $ctx);
                    break;
                case 'if':
                    $matched = false;
                    foreach ($node[1] as [$cond, $body]) {
                        if ($this->truthy($this->evaluate($cond, $ctx))) {
                            $out .= $this->renderNodes($body, $ctx);
                            $matched = true;
                            break;
                        }
                    }
                    if (! $matched) {
                        $out .= $this->renderNodes($node[2], $ctx);
                    }
                    break;
                case 'for':
                    $out .= $this->renderFor($node, $ctx);
                    break;
            }
            if (strlen($out) > self::MAX_OUTPUT) {
                throw new TemplateException('Document output is too large.');
            }
        }

        return $out;
    }

    private function renderFor(array $node, array &$ctx): string
    {
        [, $keyVar, $var, $listExpr, $body, $else] = $node;
        $list = $this->evaluate($listExpr, $ctx);
        if (! is_array($list) || $list === []) {
            return $this->renderNodes($else, $ctx);
        }

        $out = '';
        $count = count($list);
        $i = 0;
        $saved = [$var => $ctx[$var] ?? null, 'loop' => $ctx['loop'] ?? null];
        if ($keyVar) {
            $saved[$keyVar] = $ctx[$keyVar] ?? null;
        }
        foreach ($list as $key => $value) {
            if (++$this->iterations > self::MAX_ITERATIONS) {
                throw new TemplateException('Too many loop iterations.');
            }
            $ctx[$var] = $value;
            if ($keyVar) {
                $ctx[$keyVar] = $key;
            }
            $ctx['loop'] = ['index' => $i + 1, 'index0' => $i, 'first' => $i === 0, 'last' => $i === $count - 1, 'length' => $count, 'odd' => $i % 2 === 0, 'even' => $i % 2 === 1];
            $out .= $this->renderNodes($body, $ctx);
            $i++;
        }
        foreach ($saved as $k => $v) {
            if ($v === null) {
                unset($ctx[$k]);
            } else {
                $ctx[$k] = $v;
            }
        }

        return $out;
    }

    private function evaluate(array $n, array $ctx): mixed
    {
        return match ($n[0]) {
            'lit' => $n[1],
            'var' => $ctx[$n[1]] ?? null,
            'list' => array_map(fn ($a) => $this->evaluate($a, $ctx), $n[1]),
            'get' => $this->get($this->evaluate($n[1], $ctx), $this->evaluate($n[2], $ctx)),
            'not' => ! $this->truthy($this->evaluate($n[1], $ctx)),
            'neg' => -$this->num($this->evaluate($n[1], $ctx)),
            'bin' => $this->binary($n[1], $n[2], $n[3], $ctx),
            'call' => $this->call($n[1], array_map(fn ($a) => $this->evaluate($a, $ctx), $n[2])),
            'filter' => $this->filter($n[1], $this->evaluate($n[2], $ctx), array_map(fn ($a) => $this->evaluate($a, $ctx), $n[3])),
        };
    }

    private function get(mixed $target, mixed $key): mixed
    {
        if (is_array($target)) {
            return $target[$key] ?? null;
        }

        return null;
    }

    private function binary(string $op, array $l, array $r, array $ctx): mixed
    {
        if ($op === 'and') {
            return $this->truthy($this->evaluate($l, $ctx)) && $this->truthy($this->evaluate($r, $ctx));
        }
        if ($op === 'or') {
            $left = $this->evaluate($l, $ctx);

            return $this->truthy($left) ? $left : $this->evaluate($r, $ctx);
        }
        $a = $this->evaluate($l, $ctx);
        $b = $this->evaluate($r, $ctx);
        $a = $a instanceof SafeString ? (string) $a : $a;
        $b = $b instanceof SafeString ? (string) $b : $b;

        return match ($op) {
            '+' => $this->num($a) + $this->num($b),
            '-' => $this->num($a) - $this->num($b),
            '*' => $this->num($a) * $this->num($b),
            '/' => $this->num($b) == 0 ? 0 : $this->num($a) / $this->num($b),
            '%' => (int) $this->num($b) === 0 ? 0 : (int) $this->num($a) % (int) $this->num($b),
            '~' => $this->plain($a).$this->plain($b),
            '==' => is_numeric($a) && is_numeric($b) ? $a + 0 == $b + 0 : $a === $b || (string) $this->plain($a) === (string) $this->plain($b),
            '!=' => ! (is_numeric($a) && is_numeric($b) ? $a + 0 == $b + 0 : $a === $b || (string) $this->plain($a) === (string) $this->plain($b)),
            '<' => $this->cmp($a, $b) < 0,
            '>' => $this->cmp($a, $b) > 0,
            '<=' => $this->cmp($a, $b) <= 0,
            '>=' => $this->cmp($a, $b) >= 0,
            'in' => is_array($b) ? in_array($a, $b, false) : (is_string($b) && str_contains($b, (string) $this->plain($a))),
        };
    }

    private function cmp(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return $a + 0 <=> $b + 0;
        }

        return strcmp((string) $this->plain($a), (string) $this->plain($b));
    }

    private function call(string $name, array $args): mixed
    {
        $list = fn ($v) => is_array($v) ? $v : [];
        $pluck = function (array $items, ?string $field) {
            return array_map(fn ($i) => $this->num($field !== null && is_array($i) ? ($i[$field] ?? 0) : $i), array_values($items));
        };

        return match (strtolower($name)) {
            'sum' => array_sum($pluck($list($args[0] ?? []), isset($args[1]) ? (string) $args[1] : null)),
            'avg' => ($vals = $pluck($list($args[0] ?? []), isset($args[1]) ? (string) $args[1] : null)) ? array_sum($vals) / count($vals) : 0,
            'count' => is_array($args[0] ?? null) ? count($args[0]) : (($args[0] ?? null) === null ? 0 : 1),
            'round' => round($this->num($args[0] ?? 0), (int) ($args[1] ?? 0)),
            'ceil' => ceil($this->num($args[0] ?? 0)),
            'floor' => floor($this->num($args[0] ?? 0)),
            'min' => count($args) === 1 && is_array($args[0]) ? ($args[0] ? min(array_map(fn ($v) => $this->num($v), $args[0])) : 0) : ($args ? min(array_map(fn ($v) => $this->num($v), $args)) : 0),
            'max' => count($args) === 1 && is_array($args[0]) ? ($args[0] ? max(array_map(fn ($v) => $this->num($v), $args[0])) : 0) : ($args ? max(array_map(fn ($v) => $this->num($v), $args)) : 0),
            'now' => (new DateTimeImmutable('now', new \DateTimeZone($this->options['timezone'])))->format((string) ($args[0] ?? $this->options['datetime_format'])),
            'concat' => implode('', array_map(fn ($a) => $this->plain($a), $args)),
            default => throw new TemplateException('Unknown function "'.$name.'"'),
        };
    }

    private function filter(string $name, mixed $value, array $args): mixed
    {
        $plain = $this->plain($value);

        return match (strtolower($name)) {
            'raw' => $value instanceof SafeString ? $value : new SafeString($plain ?? ''),
            'escape' => new SafeString(htmlspecialchars((string) $plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), (string) $plain),
            // Only explicitly escaped values retain their pre-escaped text; raw values are never entity-decoded.
            'escape_attr' => $this->escapeAttribute($value),
            'nl2br' => new SafeString(nl2br(htmlspecialchars((string) $plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))),
            'upper' => mb_strtoupper((string) $plain),
            'lower' => mb_strtolower((string) $plain),
            'title' => mb_convert_case((string) $plain, MB_CASE_TITLE),
            'default' => ($plain === null || $plain === '' || $plain === []) ? ($args[0] ?? '') : $value,
            'number' => number_format($this->num($plain), (int) ($args[0] ?? 0)),
            'round' => round($this->num($plain), (int) ($args[0] ?? 0)),
            'currency' => $this->currency($this->num($plain), isset($args[0]) ? (string) $args[0] : null),
            'percent' => number_format($this->num($plain) * (($args[1] ?? false) ? 1 : 100), (int) ($args[0] ?? 0)).'%',
            'date' => $this->date($plain, isset($args[0]) ? (string) $args[0] : null),
            'join' => is_array($plain) ? implode((string) ($args[0] ?? ', '), array_map(fn ($v) => is_array($v) ? '' : (string) $v, $plain)) : (string) $plain,
            'length' => is_array($plain) ? count($plain) : mb_strlen((string) $plain),
            'abs' => abs($this->num($plain)),
            'truncate' => mb_strlen((string) $plain) > (int) ($args[0] ?? 80) ? mb_substr((string) $plain, 0, (int) ($args[0] ?? 80)).'…' : (string) $plain,
            'yesno' => $this->truthy($plain) ? (string) ($args[0] ?? 'Yes') : (string) ($args[1] ?? 'No'),
            default => throw new TemplateException('Unknown filter "'.$name.'"'),
        };
    }

    private function escapeAttribute(mixed $value): SafeString
    {
        $text = $value instanceof SafeString ? ($value->attributeText ?? $value->html) : (string) $this->plain($value);

        return new SafeString(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $text);
    }

    private function currency(float|int $amount, ?string $symbol): string
    {
        $symbol ??= $this->options['currency'];
        $formatted = number_format(abs($amount), 2);

        return ($amount < 0 ? '-' : '').$symbol.$formatted;
    }

    private function date(mixed $value, ?string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            $dt = $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : (is_numeric($value) ? (new DateTimeImmutable)->setTimestamp((int) $value) : new DateTimeImmutable((string) $value));
        } catch (\Exception) {
            return (string) $value;
        }
        $dt = $dt->setTimezone(new \DateTimeZone($this->options['timezone']));
        $format = match ($format) {
            null, 'short' => $this->options['date_format'],
            'long' => 'l, F j, Y',
            'datetime' => $this->options['datetime_format'],
            'time' => 'g:i A',
            'iso' => 'Y-m-d',
            default => $format,
        };

        return $dt->format($format);
    }

    private function plain(mixed $v): mixed
    {
        if ($v instanceof SafeString) {
            return $v->html;
        }
        if (is_bool($v)) {
            return $v ? 'true' : '';
        }
        if (is_float($v)) {
            return rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
        }

        return $v;
    }

    private function num(mixed $v): float|int
    {
        if ($v instanceof SafeString) {
            $v = $v->html;
        }
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if (is_bool($v)) {
            return (int) $v;
        }
        if (is_string($v) && is_numeric($v)) {
            return $v + 0;
        }

        return 0;
    }

    private function truthy(mixed $v): bool
    {
        if ($v instanceof SafeString) {
            return $v->html !== '';
        }
        if (is_string($v)) {
            return $v !== '' && $v !== '0';
        }

        return (bool) $v;
    }

    private function stringify(mixed $v): string
    {
        if ($v instanceof SafeString) {
            return $v->html;
        }
        if (is_array($v)) {
            return '';
        }

        return htmlspecialchars((string) $this->plain($v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
