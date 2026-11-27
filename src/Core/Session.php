<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Wraps the session array so it can be swapped for a plain array in tests.
 */
final class Session
{
    private array $data;

    public function __construct(?array &$storage = null)
    {
        if ($storage === null) {
            $storage = [];
        }
        $this->data = &$storage;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    /** Store a value for the next request only. */
    public function flash(string $key, mixed $value): void
    {
        $this->data['_flash_next'][$key] = $value;
    }

    /** Move "next request" flash data into place and clear the previous one. */
    public function ageFlash(): void
    {
        foreach ($this->data['_flash_now'] ?? [] as $key => $_) {
            unset($this->data[$key]);
        }
        $next = $this->data['_flash_next'] ?? [];
        foreach ($next as $key => $value) {
            $this->data[$key] = $value;
        }
        $this->data['_flash_now'] = $next;
        $this->data['_flash_next'] = [];
    }

    public function csrfToken(): string
    {
        if (empty($this->data['_token'])) {
            $this->data['_token'] = bin2hex(random_bytes(32));
        }

        return $this->data['_token'];
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $this->data['_token'] = bin2hex(random_bytes(32));
    }

    public function clear(): void
    {
        foreach (array_keys($this->data) as $key) {
            unset($this->data[$key]);
        }
    }
}
