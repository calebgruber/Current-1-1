<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Inventory\DTOs\AllocationSource;
use App\Domain\Inventory\DTOs\AvailabilityBreakdown;
use App\Domain\Reservations\DTOs\ConflictReport;

/**
 * Pure overbooking check. The breakdown must be computed *excluding* the
 * reservation/job being evaluated so its own hold is not counted twice.
 */
final class ConflictDetector
{
    public function check(int $productId, string $productName, AvailabilityBreakdown $availability, int $requested, bool $tentativeDeducted = false): ConflictReport
    {
        $competing = array_map(
            fn (AllocationSource $s) => $s->toArray(),
            array_merge($availability->sources['reserved'] ?? [], $availability->sources['checked_out'] ?? [], $availability->sources['tentative'] ?? []),
        );

        if ($requested <= 0) {
            return new ConflictReport($productId, $productName, $requested, $availability->available, $availability->tentative, ConflictReport::OK, 'Nothing requested.');
        }

        if ($requested > $availability->available) {
            $short = $requested - max(0, $availability->available);

            return new ConflictReport(
                $productId, $productName, $requested, $availability->available, $availability->tentative, ConflictReport::SHORTAGE,
                sprintf('%s: %d requested but only %d available — short by %d.', $productName, $requested, max(0, $availability->available), $short),
                $competing,
            );
        }

        if (! $tentativeDeducted && $availability->tentative > 0 && $requested > $availability->available - $availability->tentative) {
            return new ConflictReport(
                $productId, $productName, $requested, $availability->available, $availability->tentative, ConflictReport::TENTATIVE,
                sprintf('%s: fits now, but %d unit(s) are on competing quotes. Confirming those would cause a shortage.', $productName, $availability->tentative),
                $competing,
            );
        }

        return new ConflictReport($productId, $productName, $requested, $availability->available, $availability->tentative, ConflictReport::OK, sprintf('%s: %d of %d available.', $productName, $requested, $availability->available));
    }
}
