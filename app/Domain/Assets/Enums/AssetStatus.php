<?php

namespace App\Domain\Assets\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case Allocated = 'allocated';
    case CheckedOut = 'checked_out';
    case InMaintenance = 'maintenance';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Allocated => 'Allocated',
            self::CheckedOut => 'Checked out',
            self::InMaintenance => 'In maintenance',
            self::Damaged => 'Damaged',
            self::Lost => 'Lost',
            self::Retired => 'Retired',
        };
    }

    /** Whether the unit counts toward owned stock. Retired units are excluded entirely. */
    public function countsTowardStock(): bool
    {
        return $this !== self::Retired;
    }

    /** Statuses that make a unit unusable regardless of reservations. */
    public function isUnserviceable(): bool
    {
        return in_array($this, [self::InMaintenance, self::Damaged, self::Lost, self::Retired], true);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
