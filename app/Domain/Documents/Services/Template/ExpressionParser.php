<?php

namespace App\Domain\Documents\Services\Template;

use App\Domain\Documents\Exceptions\TemplateException;

/**
 * Recursive-descent parser for template expressions. Produces a small AST of
 * arrays; nothing is ever passed to eval() or to PHP callables chosen by the
 * template author.
 *
 * Grammar (lowest → highest precedence):
 *   filtered := or ( '|' IDENT ( '(' args ')' )? )*
 *   or       := and ( 'or' and )*
 *   and      := not ( 'and' not )*
 *   not      := 'not' not | compare
 *   compare  := concat ( ('=='|'!='|'<'|'>'|'<='|'>='|'in') concat )?
 *   concat   := add ( '~' add )*
 *   add      := mul ( ('+'|'-') mul )*
 *   mul      := unary ( ('*'|'/'|'%') unary )*
 *   unary    := '-' unary | postfix
 *   postfix  := primary ( '.' IDENT | '[' filtered ']' )*
 *   primary  := NUMBER | STRING | 'true' | 'false' | 'null' | IDENT '(' args ')' | IDENT | '(' filtered ')' | '[' args ']'
 */
final class ExpressionParser
{
    private array $tokens = [];

    private int $pos = 0;

    private ?int $line = null;

    public function parse(string $source, ?int $line = null): array
    {
        $this->tokens = $this->tokenize($source, $line);
        $this->pos = 0;
        $this->line = $line;
        $node = $this->filtered();
        if ($this->peek() !== null) {
            throw new TemplateException('Unexpected "'.$this->peek()[1].'" in expression "'.trim($source).'"', $line);
        }

        return $node;
    }

    private function tokenize(string $s, ?int $line): array
    {
        $tokens = [];
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;

                continue;
            }
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($s[$i + 1]))) {
                preg_match('/\G\d*\.?\d+/', $s, $m, 0, $i);
                $tokens[] = ['num', $m[0]];
                $i += strlen($m[0]);

                continue;
            }
            if ($c === '"' || $c === "'") {
                $j = $i + 1;
                $buf = '';
                while ($j < $len && $s[$j] !== $c) {
                    if ($s[$j] === '\\' && $j + 1 < $len) {
                        $j++;
                    }
                    $buf .= $s[$j];
                    $j++;
                }
                if ($j >= $len) {
                    throw new TemplateException('Unterminated string in expression', $line);
                }
                $tokens[] = ['str', $buf];
                $i = $j + 1;

                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $s, $m, 0, $i);
                $tokens[] = ['id', $m[0]];
                $i += strlen($m[0]);

                continue;
            }
            $two = substr($s, $i, 2);
            if (in_array($two, ['==', '!=', '<=', '>=', '&&', '||'], true)) {
                $tokens[] = ['op', match ($two) { '&&' => 'and', '||' => 'or', default => $two }];
                $i += 2;

                continue;
            }
            if (str_contains('+-*/%<>()[].,|~!', $c)) {
                $tokens[] = ['op', $c === '!' ? 'not' : $c];
                $i++;

                continue;
            }
            throw new TemplateException('Unexpected character "'.$c.'" in expression', $line);
        }

        return $tokens;
    }

    private function peek(int $o = 0): ?array
    {
        return $this->tokens[$this->pos + $o] ?? null;
    }

    private function isOp(string $op): bool
    {
        $t = $this->peek();

        return $t !== null && (($t[0] === 'op' && $t[1] === $op) || ($t[0] === 'id' && in_array($op, ['and', 'or', 'not', 'in'], true) && $t[1] === $op));
    }

    private function expect(string $op): void
    {
        if (! $this->isOp($op)) {
            throw new TemplateException('Expected "'.$op.'"'.($this->peek() ? ' but found "'.$this->peek()[1].'"' : ' at end of expression'), $this->line);
        }
        $this->pos++;
    }

    private function filtered(): array
    {
        $node = $this->or();
        while ($this->isOp('|')) {
            $this->pos++;
            $name = $this->peek();
            if (! $name || $name[0] !== 'id') {
                throw new TemplateException('Expected filter name after "|"', $this->line);
            }
            $this->pos++;
            $args = [];
            if ($this->isOp('(')) {
                $this->pos++;
                $args = $this->args(')');
            }
            $node = ['filter', $name[1], $node, $args];
        }

        return $node;
    }

    private function or(): array
    {
        $node = $this->and();
        while ($this->isOp('or')) {
            $this->pos++;
            $node = ['bin', 'or', $node, $this->and()];
        }

        return $node;
    }

    private function and(): array
    {
        $node = $this->not();
        while ($this->isOp('and')) {
            $this->pos++;
            $node = ['bin', 'and', $node, $this->not()];
        }

        return $node;
    }

    private function not(): array
    {
        if ($this->isOp('not')) {
            $this->pos++;

            return ['not', $this->not()];
        }

        return $this->compare();
    }

    private function compare(): array
    {
        $node = $this->concat();
        foreach (['==', '!=', '<=', '>=', '<', '>', 'in'] as $op) {
            if ($this->isOp($op)) {
                $this->pos++;

                return ['bin', $op, $node, $this->concat()];
            }
        }

        return $node;
    }

    private function concat(): array
    {
        $node = $this->add();
        while ($this->isOp('~')) {
            $this->pos++;
            $node = ['bin', '~', $node, $this->add()];
        }

        return $node;
    }

    private function add(): array
    {
        $node = $this->mul();
        while ($this->isOp('+') || $this->isOp('-')) {
            $op = $this->peek()[1];
            $this->pos++;
            $node = ['bin', $op, $node, $this->mul()];
        }

        return $node;
    }

    private function mul(): array
    {
        $node = $this->unary();
        while ($this->isOp('*') || $this->isOp('/') || $this->isOp('%')) {
            $op = $this->peek()[1];
            $this->pos++;
            $node = ['bin', $op, $node, $this->unary()];
        }

        return $node;
    }

    private function unary(): array
    {
        if ($this->isOp('-')) {
            $this->pos++;

            return ['neg', $this->unary()];
        }

        return $this->postfix();
    }

    private function postfix(): array
    {
        $node = $this->primary();
        while (true) {
            if ($this->isOp('.')) {
                $this->pos++;
                $t = $this->peek();
                if (! $t || ! in_array($t[0], ['id', 'num'], true)) {
                    throw new TemplateException('Expected property name after "."', $this->line);
                }
                $this->pos++;
                $node = ['get', $node, ['lit', $t[1]]];
            } elseif ($this->isOp('[')) {
                $this->pos++;
                $key = $this->filtered();
                $this->expect(']');
                $node = ['get', $node, $key];
            } else {
                return $node;
            }
        }
    }

    private function primary(): array
    {
        $t = $this->peek();
        if ($t === null) {
            throw new TemplateException('Unexpected end of expression', $this->line);
        }
        $this->pos++;

        if ($t[0] === 'num') {
            return ['lit', str_contains($t[1], '.') ? (float) $t[1] : (int) $t[1]];
        }
        if ($t[0] === 'str') {
            return ['lit', $t[1]];
        }
        if ($t[0] === 'id') {
            $lower = strtolower($t[1]);
            if (in_array($lower, ['true', 'false', 'null'], true)) {
                return ['lit', match ($lower) { 'true' => true, 'false' => false, 'null' => null }];
            }
            if ($this->isOp('(')) {
                $this->pos++;

                return ['call', $t[1], $this->args(')')];
            }

            return ['var', $t[1]];
        }
        if ($t[1] === '(') {
            $node = $this->filtered();
            $this->expect(')');

            return $node;
        }
        if ($t[1] === '[') {
            return ['list', $this->args(']')];
        }

        throw new TemplateException('Unexpected "'.$t[1].'" in expression', $this->line);
    }

    private function args(string $close): array
    {
        $args = [];
        if ($this->isOp($close)) {
            $this->pos++;

            return $args;
        }
        while (true) {
            $args[] = $this->filtered();
            if ($this->isOp(',')) {
                $this->pos++;

                continue;
            }
            $this->expect($close);

            return $args;
        }
    }
}
