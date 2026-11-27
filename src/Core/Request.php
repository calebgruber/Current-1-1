<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $files = [],
        public readonly array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = self::detectBasePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            self::normalise($uri),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    /** Folder the app is served from, e.g. "/stagetrack" when installed in a sub-folder. */
    public static function detectBasePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $dir = preg_replace('#/public$#', '', $dir) ?? '';

        return $dir === '.' ? '' : $dir;
    }

    public static function create(string $method, string $uri, array $data = [], array $files = []): self
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $method = strtoupper($method);

        return new self($method, self::normalise($path), $query, $method === 'GET' ? [] : $data, $files, []);
    }

    private static function normalise(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/index.php' ? '/' : $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), ['1', 1, true, 'on', 'yes', 'true'], true);
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (! is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    public function wantsJson(): bool
    {
        return str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($this->query['format'] ?? '') === 'json';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function referer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }
}
