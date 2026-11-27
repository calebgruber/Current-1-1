<?php

declare(strict_types=1);

namespace App\Core;

/** Key/value shop settings stored in the settings table. */
final class Settings
{
    public const DEFAULTS = [
        'shop_name' => 'Theatre Production Shop',
        'shop_department' => 'Lighting & Sound',
        'institution' => 'College Theatre Department',
        'shop_email' => '',
        'shop_phone' => '',
        'shop_address' => '',
        'scan_sounds' => '1',
        'calendar_months' => '4',
        'receipt_footer' => 'Please check every item on this receipt. Report anything missing or damaged to the shop straight away.',
    ];

    private ?array $values = null;

    public function __construct(private readonly ?Database $db)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function all(): array
    {
        if ($this->values === null) {
            $this->values = self::DEFAULTS;
            try {
                if ($this->db && $this->db->tableExists('settings')) {
                    foreach ($this->db->all('SELECT name, value FROM settings') as $row) {
                        $this->values[$row['name']] = $row['value'];
                    }
                }
            } catch (\PDOException) {
                // Database not ready yet (e.g. during setup) – defaults are fine.
            }
        }

        return $this->values;
    }

    public function set(array $values): void
    {
        foreach ($values as $name => $value) {
            $exists = $this->db->value('SELECT COUNT(*) FROM settings WHERE name = ?', [$name]);
            if ($exists) {
                $this->db->run('UPDATE settings SET value = ? WHERE name = ?', [(string) $value, $name]);
            } else {
                $this->db->insert('settings', ['name' => $name, 'value' => (string) $value]);
            }
        }
        $this->values = null;
    }
}
