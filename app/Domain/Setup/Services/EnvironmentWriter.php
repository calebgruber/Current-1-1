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
    private SetupLock $lock;

    public function __construct(private ?string $path = null, ?SetupLock $lock = null)
    {
        $this->path ??= app()->environmentFilePath();
        $this->lock = $lock ?? new SetupLock;
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
        $this->replace($contents);
    }

    public function writable(): bool
    {
        $directory = dirname($this->path);
        return is_dir($directory) && is_writable($directory)
            && (is_file($this->path)
                ? is_readable($this->path) && is_writable($this->path)
                : ! file_exists($this->path) && ! is_link($this->path));
    }

    /** @param array<string, string|int|bool|null> $values */
    public function set(array $values): void
    {
        $this->lock->synchronized(fn () => $this->write($values));
    }

    public function ensureAppKey(callable $generate): string
    {
        return $this->lock->synchronized(function () use ($generate): string {
            $key = $this->get('APP_KEY');
            if (! $key) {
                $key = $generate();
                $this->write(['APP_KEY' => $key]);
            }

            return $key;
        });
    }

    /** @param array<string, string|int|bool|null> $values */
    private function write(array $values): void
    {
        $this->ensureExists();
        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read '.$this->path.'.');
        }

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

        $this->replace($contents);
    }

    private function replace(string $contents): void
    {
        clearstatcache(true, $this->path);
        $permissions = is_file($this->path) ? @fileperms($this->path) : 0600;
        if ($permissions === false) {
            throw new RuntimeException('Unable to determine permissions of '.$this->path.'.');
        }

        $tmp = $this->path.'.tmp.'.bin2hex(random_bytes(16));
        $mask = umask(0077);
        try {
            $file = @fopen($tmp, 'x');
        } finally {
            umask($mask);
        }
        if ($file === false) {
            throw new RuntimeException('Unable to write to '.$this->path.'.');
        }

        try {
            $length = strlen($contents);
            $offset = 0;
            while ($offset < $length) {
                $written = @fwrite($file, substr($contents, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to write to '.$this->path.'.');
                }
                $offset += $written;
            }
            if (! @fflush($file) || ! @chmod($tmp, $permissions & 0777)) {
                throw new RuntimeException('Unable to write to '.$this->path.'.');
            }
            if (! @fclose($file)) {
                $file = null;
                throw new RuntimeException('Unable to write to '.$this->path.'.');
            }
            $file = null;
            if (! @rename($tmp, $this->path)) {
                throw new RuntimeException('Unable to write to '.$this->path.'.');
            }
        } finally {
            if (is_resource($file)) {
                @fclose($file);
            }
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function get(string $key): ?string
    {
        if (! is_file($this->path)) {
            return null;
        }
        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read '.$this->path.'.');
        }
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $m)) {
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
