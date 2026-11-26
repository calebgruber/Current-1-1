<?php

namespace App\Support;

class Installation
{
    public static function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    public static function isInstalled(): bool
    {
        return (bool) config('stagetrack.assume_installed') || is_file(static::lockPath());
    }

    public static function markInstalled(string $version): void
    {
        file_put_contents(static::lockPath(), json_encode([
            'version' => $version,
            'installed_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }
}
