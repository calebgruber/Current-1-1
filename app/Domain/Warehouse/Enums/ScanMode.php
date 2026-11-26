<?php

namespace App\Domain\Warehouse\Enums;

enum ScanMode: string
{
    case Pick = 'pick';
    case Prep = 'prep';
    case Stage = 'stage';
    case CheckOut = 'checkout';
    case Return = 'return';
    case Damage = 'damage';
    case Lookup = 'lookup';

    public function label(): string
    {
        return match ($this) {
            self::CheckOut => 'Check out',
            default => ucfirst($this->value),
        };
    }

    public function requiresJob(): bool
    {
        return ! in_array($this, [self::Lookup, self::Damage], true);
    }

    /** JobItem counter advanced by this mode. */
    public function counter(): ?string
    {
        return match ($this) {
            self::Pick => 'picked_quantity',
            self::CheckOut => 'checked_out_quantity',
            self::Return => 'returned_quantity',
            default => null,
        };
    }

    /** Status written to job_item_assets.status for serialized scans. */
    public function assetAssignmentStatus(): ?string
    {
        return match ($this) {
            self::Pick => 'picked',
            self::Prep => 'prepped',
            self::Stage => 'staged',
            self::CheckOut => 'checked_out',
            self::Return => 'returned',
            self::Damage => 'damaged',
            self::Lookup => null,
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
