<?php

declare(strict_types=1);

use App\Core\App;

/** Escape a value for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app(): App
{
    return App::instance();
}

/** Build an absolute-path URL for the app, honouring installs in a sub-folder. */
function url(string $path = '/', array $query = []): string
{
    $base = rtrim(app()->basePath(), '/');
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');

    return $base . $path . ($query ? '?' . http_build_query($query) : '');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? substr((string) filemtime($file), -6) : '1';

    return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(app()->session()->csrfToken()) . '">';
}

/** Previously submitted value after a validation failure. */
function old(string $key, mixed $default = ''): mixed
{
    $old = app()->session()->get('_old_input', []);

    return array_key_exists($key, $old) ? $old[$key] : $default;
}

function field_error(string $key): ?string
{
    $errors = app()->session()->get('_errors', []);

    return $errors[$key] ?? null;
}

function user(): ?array
{
    return app()->auth()->user();
}

function can(string $ability): bool
{
    return app()->auth()->can($ability);
}

function setting(string $key, mixed $default = null): mixed
{
    return app()->settings()->get($key, $default);
}

function fmt_date(?string $value, string $format = 'D j M Y'): string
{
    if (! $value) {
        return '—';
    }
    $ts = strtotime($value);

    return $ts ? date($format, $ts) : '—';
}

function fmt_datetime(?string $value): string
{
    return fmt_date($value, 'D j M Y, g:ia');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Render a small Tabler status badge. */
function status_badge(string $status): string
{
    $map = [
        'draft' => ['Draft', 'secondary'],
        'confirmed' => ['Confirmed', 'blue'],
        'prepping' => ['Prepping', 'orange'],
        'out' => ['Out', 'purple'],
        'returned' => ['Returned', 'green'],
        'closed' => ['Closed', 'dark'],
        'cancelled' => ['Cancelled', 'red'],
        'available' => ['Available', 'green'],
        'prepped' => ['Prepped', 'orange'],
        'repair' => ['In repair', 'red'],
        'retired' => ['Retired', 'secondary'],
        'missing' => ['Missing', 'red'],
        'serial' => ['Serial', 'indigo'],
        'bulk' => ['Bulk', 'teal'],
    ];
    [$label, $color] = $map[$status] ?? [ucfirst($status), 'secondary'];

    return '<span class="badge bg-' . $color . '-lt">' . e($label) . '</span>';
}
