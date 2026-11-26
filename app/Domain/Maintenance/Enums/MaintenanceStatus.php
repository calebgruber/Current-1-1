<?php

namespace App\Domain\Maintenance\Enums;

enum MaintenanceStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case AwaitingParts = 'awaiting_parts';
    case Completed = 'completed';
    case WrittenOff = 'written_off';

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::WrittenOff], true);
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Open->value, self::InProgress->value, self::AwaitingParts->value];
    }
}
