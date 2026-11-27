<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A small, safe, Mustache-style template engine for paperwork.
 *
 *   {{ show.title }}          escaped value
 *   {{#lines}} … {{/lines}}   repeat for every entry of a list (or show once if truthy)
 *   {{^lines}} … {{/lines}}   show only when the list is empty / value is falsy
 *
 * Inside a list, names are looked up on the current entry first, then outwards.
 * Every value is HTML-escaped; templates cannot run code.
 */
final class TemplateEngine
{
    private const TAG = '/\{\{\s*([#^\/]?)\s*([A-Za-z0-9_.]+)\s*\}\}/';

    public function render(string $template, array $context): string
    {
        $tree = $this->parse($template);

        return $this->renderNodes($tree, [$context]);
    }

    /** Check a template for unbalanced sections. Returns a list of problems (empty when valid). */
    public function lint(string $template): array
    {
        try {
            $this->parse($template);

            return [];
        } catch (\InvalidArgumentException $e) {
            return [$e->getMessage()];
        }
    }

    private function parse(string $template): array
    {
        preg_match_all(self::TAG, $template, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        // Each stack frame: [name, inverted, children]. Frame 0 is the root.
        $stack = [[null, false, []]];
        $offset = 0;

        foreach ($matches as $m) {
            [$full, $pos] = $m[0];
            $type = $m[1][0];
            $name = $m[2][0];
            $top = count($stack) - 1;
            if ($pos > $offset) {
                $stack[$top][2][] = ['text', substr($template, $offset, $pos - $offset)];
            }
            $offset = $pos + strlen($full);

            if ($type === '#' || $type === '^') {
                $stack[] = [$name, $type === '^', []];
            } elseif ($type === '/') {
                if ($top === 0 || $stack[$top][0] !== $name) {
                    throw new \InvalidArgumentException('Unexpected {{/' . $name . '}}.');
                }
                [$sectionName, $inverted, $children] = array_pop($stack);
                $stack[$top - 1][2][] = ['section', $sectionName, $inverted, $children];
            } else {
                $stack[$top][2][] = ['var', $name];
            }
        }
        if (count($stack) > 1) {
            throw new \InvalidArgumentException('Missing {{/' . $stack[count($stack) - 1][0] . '}}.');
        }
        if ($offset < strlen($template)) {
            $stack[0][2][] = ['text', substr($template, $offset)];
        }

        return $stack[0][2];
    }

    private function renderNodes(array $nodes, array $scopes): string
    {
        $out = '';
        foreach ($nodes as $node) {
            switch ($node[0]) {
                case 'text':
                    $out .= $node[1];
                    break;
                case 'var':
                    $value = $this->lookup($node[1], $scopes);
                    $out .= is_array($value) ? '' : htmlspecialchars($this->stringify($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    break;
                case 'section':
                    [, $name, $inverted, $children] = $node;
                    $value = $this->lookup($name, $scopes);
                    $empty = $value === null || $value === false || $value === '' || $value === [] || $value === 0 || $value === '0';
                    if ($inverted) {
                        $out .= $empty ? $this->renderNodes($children, $scopes) : '';
                    } elseif (! $empty) {
                        if (is_array($value) && array_is_list($value)) {
                            foreach ($value as $entry) {
                                $out .= $this->renderNodes($children, [...$scopes, is_array($entry) ? $entry : ['.' => $entry]]);
                            }
                        } else {
                            $out .= $this->renderNodes($children, is_array($value) ? [...$scopes, $value] : $scopes);
                        }
                    }
                    break;
            }
        }

        return $out;
    }

    private function lookup(string $name, array $scopes): mixed
    {
        $parts = explode('.', $name);
        for ($i = count($scopes) - 1; $i >= 0; $i--) {
            $scope = $scopes[$i];
            if (! is_array($scope) || ! array_key_exists($parts[0], $scope)) {
                continue;
            }
            $value = $scope[$parts[0]];
            foreach (array_slice($parts, 1) as $part) {
                if (! is_array($value) || ! array_key_exists($part, $value)) {
                    return null;
                }
                $value = $value[$part];
            }

            return $value;
        }

        return null;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            default => (string) $value,
        };
    }
}
