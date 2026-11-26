<?php

namespace App\Domain\Setup\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Safely updates key/value pairs in the application's .env file.
 * Values are validated (no newlines / control characters) and quoted +
 * escaped so they cannot inject additional variables.
 */
class EnvironmentWriter
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= app()->environmentFilePath();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function ensureExists(): void
    {
        if (is_file($this->path)) {
            return;
        }
        $example = base_path('.env.example');
        $contents = is_file($example) ? (string) file_get_contents($example) : '';
        if (@file_put_contents($this->path, $contents) === false) {
            throw new RuntimeException('Unable to create the .env file at '.$this->path.'. Check that the directory is writable.');
        }
    }

    public function writable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    /** @param array<string, string|int|bool|null> $values */
    public function set(array $values): void
    {
        $this->ensureExists();
        $contents = (string) file_get_contents($this->path);

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException("Invalid environment key [{$key}].");
            }
            $line = $key.'='.$this->encode($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents)) {
                $contents = (string) preg_replace_callback($pattern, fn () => $line, $contents, 1);
            } elseif (preg_match('/^#\s*'.preg_quote($key, '/').'=.*$/m', $contents)) {
                $contents = (string) preg_replace_callback('/^#\s*'.preg_quote($key, '/').'=.*$/m', fn () => $line, $contents, 1);
            } else {
                $contents = rtrim($contents, "\n")."\n".$line."\n";
            }
        }

        $tmp = $this->path.'.tmp';
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false || ! @rename($tmp, $this->path)) {
            @unlink($tmp);
            if (@file_put_contents($this->path, $contents, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write to '.$this->path.'.');
            }
        }
    }

    public function get(string $key): ?string
    {
        if (! is_file($this->path)) {
            return null;
        }
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($this->path), $m)) {
            $raw = trim($m[1]);
            if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
                return stripcslashes(substr($raw, 1, -1));
            }

            return $raw;
        }

        return null;
    }

    public function encode(string|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $value = (string) $value;

        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('Environment values may not contain newlines or control characters.');
        }
        if (preg_match('/^[A-Za-z0-9_.:\/@+\-]*$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
