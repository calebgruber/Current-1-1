<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value organization settings stored in the `settings` table.
 * Keys are namespaced "{group}.{name}", e.g. "security.require_two_factor".
 */
class SettingsRepository
{
    private const CACHE_KEY = 'stagetrack.settings';

    /** Defaults applied when a key has never been saved. */
    public const DEFAULTS = [
        'general.organization_name' => 'StageTrack Enterprise',
        'general.currency' => 'USD',
        'general.timezone' => 'UTC',
        'general.date_format' => 'M j, Y',
        'general.logo_path' => null,
        'general.address' => null,
        'general.default_job_duration_days' => 3,
        'general.tax_rate' => 0,
        'general.tax_label' => 'Tax',
        'documents.terms' => null,
        'security.password_min_length' => 12,
        'security.password_require_mixed' => true,
        'security.password_expiry_days' => 0,
        'security.require_two_factor' => false,
        'security.require_two_factor_for_admins' => true,
        'security.max_login_attempts' => 5,
        'security.lockout_minutes' => 15,
        'security.session_lifetime_minutes' => 480,
        'security.allow_passkeys' => true,
        'warehouse.sound_enabled' => true,
        'warehouse.allow_over_pull' => false,
        'reservations.block_overbooking' => true,
        'reservations.count_tentative' => false,
    ];

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function all(): array
    {
        if ($this->loaded === null) {
            $stored = [];
            try {
                $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                    if (! Schema::hasTable('settings')) {
                        return [];
                    }

                    return Setting::query()->pluck('value', 'key')->all();
                });
            } catch (\Throwable) {
                $stored = [];
            }
            $this->loaded = array_merge(self::DEFAULTS, $stored);
        }

        return $this->loaded;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /** @return array<string, mixed> settings within a group, keyed without the group prefix */
    public function group(string $group): array
    {
        $result = [];
        foreach ($this->all() as $key => $value) {
            if (str_starts_with($key, $group.'.')) {
                $result[substr($key, strlen($group) + 1)] = $value;
            }
        }

        return $result;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['group' => explode('.', $key)[0], 'value' => $value]);
        $this->flush();
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['group' => explode('.', $key)[0], 'value' => $value]);
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }
}
