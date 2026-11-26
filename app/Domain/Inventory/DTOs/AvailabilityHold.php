<?php

namespace App\Domain\Inventory\DTOs;

use DateTimeImmutable;

/**
 * A time-boxed claim on stock. `kind` is one of: reserved, checked_out, tentative.
 */
final class AvailabilityHold
{
    public const RESERVED = 'reserved';

    public const CHECKED_OUT = 'checked_out';

    public const TENTATIVE = 'tentative';

    public function __construct(
        public readonly string $kind,
        public readonly int $quantity,
        public readonly DateTimeImmutable $startsAt,
        public readonly DateTimeImmutable $endsAt,
        public readonly AllocationSource $source,
    ) {}

    public function overlaps(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $this->startsAt < $to && $this->endsAt > $from;
    }
}
