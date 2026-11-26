<?php

namespace App\Domain\Setup\Services;

use RuntimeException;

/** Serializes environment updates across PHP workers sharing storage/app. */
class SetupLock
{
    public function __construct(private ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? storage_path('app/setup.guard');
    }

    public function writable(): bool
    {
        $path = $this->path();

        return is_file($path)
            ? is_writable($path)
            : ! file_exists($path) && ! is_link($path) && is_dir(dirname($path)) && is_writable(dirname($path));
    }

    public function synchronized(callable $callback): mixed
    {
        $file = @fopen($this->path(), 'c');
        if ($file === false) {
            throw new RuntimeException('Unable to open the setup lock.');
        }

        try {
            $deadline = microtime(true) + 30;
            while (! flock($file, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Setup is busy; please retry.');
                }
                usleep(100000);
            }

            return $callback();
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
