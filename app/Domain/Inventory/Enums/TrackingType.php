<?php

namespace App\Domain\Inventory\Enums;

enum TrackingType: string
{
    case Serialized = 'serialized';
    case Bulk = 'bulk';

    public function label(): string
    {
        return match ($this) {
            self::Serialized => 'Serialized (individual assets)',
            self::Bulk => 'Bulk (quantity tracked)',
        };
    }
}
