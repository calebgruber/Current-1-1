<?php

namespace App\Domain\Documents\Services;

use App\Support\Html\HtmlSanitizer;
use Illuminate\Validation\ValidationException;

/**
 * Structural validation + sanitisation of the visual designer's block tree before it is
 * stored. Rich-text HTML is sanitised so the designer can safely load it back into a
 * contenteditable surface; everything is re-sanitised again after rendering.
 */
final class BlockSanitizer
{
    public const MAX_BLOCKS = 400;

    public const MAX_DEPTH = 4;

    public const MAX_IMAGE_BYTES = 1_500_000;

    private int $count = 0;

    public function __construct(private readonly HtmlSanitizer $html) {}

    /** @return list<array> */
    public function clean(array $blocks): array
    {
        $this->count = 0;

        return $this->list($blocks, 1, 'blocks');
    }

    private function list(array $blocks, int $depth, string $path): array
    {
        if ($depth > self::MAX_DEPTH) {
            $this->fail($path, 'Blocks are nested too deeply (max '.self::MAX_DEPTH.' levels).');
        }
        $out = [];
        foreach (array_values($blocks) as $i => $block) {
            if (! is_array($block)) {
                $this->fail("{$path}.{$i}", 'Invalid block.');
            }
            if (++$this->count > self::MAX_BLOCKS) {
                $this->fail($path, 'Templates are limited to '.self::MAX_BLOCKS.' blocks.');
            }
            $out[] = $this->block($block, $depth, "{$path}.{$i}");
        }

        return $out;
    }

    private function block(array $b, int $depth, string $path): array
    {
        $type = $b['type'] ?? null;
        if (! is_string($type) || ! in_array($type, BlockCompiler::TYPES, true)) {
            $this->fail($path, 'Unknown block type.');
        }
        $id = is_string($b['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $b['id']) ? $b['id'] : 'b'.bin2hex(random_bytes(5));
        $props = is_array($b['props'] ?? null) ? $b['props'] : [];
        $style = is_array($b['style'] ?? null) ? array_filter($b['style'], fn ($v) => is_scalar($v) || $v === null) : [];

        if ($type === 'text' && isset($props['html'])) {
            $props['html'] = $this->html->sanitize((string) $props['html']);
        }
        if ($type === 'image' && isset($props['src'])) {
            $src = (string) $props['src'];
            if (strlen($src) > self::MAX_IMAGE_BYTES * 1.4) {
                $this->fail($path, 'Images must be smaller than 1.5 MB.');
            }
            if ($src !== '' && ! preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=]+$#', $src) && ! str_starts_with($src, '{{')) {
                $this->fail($path, 'Images must be uploaded (PNG, JPEG, GIF or WebP) or use a variable.');
            }
        }
        if ($type === 'columns') {
            $cols = [];
            foreach (array_slice(array_values((array) ($props['columns'] ?? [])), 0, 4) as $c => $col) {
                $col = is_array($col) ? $col : [];
                $width = is_scalar($col['width'] ?? null) ? (string) $col['width'] : '50';
                $width = preg_match('/^\d{1,3}(?:\.\d+)?%?$/', $width) ? (float) rtrim($width, '%') : 50;
                $cols[] = [
                    'width' => max(5, min(100, $width)).'%',
                    'blocks' => $this->list((array) ($col['blocks'] ?? []), $depth + 1, "{$path}.columns.{$c}"),
                ];
            }
            $props['columns'] = $cols;
        }
        if ($type === 'conditional') {
            $props['blocks'] = $this->list((array) ($props['blocks'] ?? []), $depth + 1, "{$path}.blocks");
        }

        return ['id' => $id, 'type' => $type, 'props' => $props, 'style' => $style];
    }

    private function fail(string $path, string $message): never
    {
        throw ValidationException::withMessages(['blocks' => "{$message} ({$path})"]);
    }
}
