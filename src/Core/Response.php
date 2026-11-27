<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
        public ?string $file = null,
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public static function file(string $path, string $mime, string $cache = 'private, max-age=86400'): self
    {
        return new self('', 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($path),
            'Cache-Control' => $cache,
            'X-Content-Type-Options' => 'nosniff',
        ], $path);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function isRedirect(): bool
    {
        return isset($this->headers['Location']);
    }

    public function send(): void
    {
        http_response_code($this->status);
        $defaults = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
        ];
        foreach ($this->headers + $defaults as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->file !== null) {
            readfile($this->file);

            return;
        }
        echo $this->body;
    }
}
