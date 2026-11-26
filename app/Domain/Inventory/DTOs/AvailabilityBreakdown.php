<?php

namespace App\Domain\Inventory\DTOs;

/**
 * Result of an availability calculation for one product over a window.
 * `available` may be negative, which indicates an overbooking/shortage.
 */
final class AvailabilityBreakdown
{
    /**
     * @param  array<string, list<AllocationSource>>  $sources
     */
    public function __construct(
        public readonly int $owned,
        public readonly int $total,
        public readonly int $reserved,
        public readonly int $checkedOut,
        public readonly int $tentative,
        public readonly int $maintenance,
        public readonly int $damaged,
        public readonly int $lost,
        public readonly int $retired,
        public readonly int $available,
        public readonly ?string $peakAt,
        public readonly array $sources,
    ) {}

    public function isShort(): bool
    {
        return $this->available < 0;
    }

    public function toArray(): array
    {
        return [
            'owned' => $this->owned,
            'total' => $this->total,
            'reserved' => $this->reserved,
            'checked_out' => $this->checkedOut,
            'tentative' => $this->tentative,
            'maintenance' => $this->maintenance,
            'damaged' => $this->damaged,
            'lost' => $this->lost,
            'retired' => $this->retired,
            'available' => $this->available,
            'shortage' => max(0, -$this->available),
            'peak_at' => $this->peakAt,
            'sources' => array_map(
                fn (array $list) => array_map(fn (AllocationSource $s) => $s->toArray(), $list),
                $this->sources,
            ),
        ];
    }
}
