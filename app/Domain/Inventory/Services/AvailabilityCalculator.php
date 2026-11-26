<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\DTOs\AllocationSource;
use App\Domain\Inventory\DTOs\AvailabilityBreakdown;
use App\Domain\Inventory\DTOs\AvailabilityHold;
use DateTimeImmutable;

/**
 * Pure availability maths. No database access: callers gather inventory
 * buckets and holds, this class turns them into an explainable breakdown.
 *
 * Holds are evaluated with a sweep line so that only *concurrent* holds are
 * summed: two shows using the same 10 units in consecutive weeks need 10, not
 * 20. Intervals are half-open [start, end), so back-to-back bookings never
 * conflict (ends are processed before starts at the same instant).
 */
final class AvailabilityCalculator
{
    /**
     * @param  int  $owned  every unit owned, including retired ones
     * @param  array{maintenance?: list<AllocationSource>, damaged?: list<AllocationSource>, lost?: list<AllocationSource>, retired?: list<AllocationSource>}  $unserviceable
     * @param  list<AvailabilityHold>  $holds
     * @param  bool  $deductTentative  whether tentative (quote) holds reduce availability
     */
    public function calculate(
        int $owned,
        array $unserviceable,
        array $holds,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        bool $deductTentative = false,
    ): AvailabilityBreakdown {
        $sum = static fn (array $list): int => array_sum(array_map(fn (AllocationSource $s) => $s->quantity, $list));

        $maintenance = $sum($unserviceable['maintenance'] ?? []);
        $damaged = $sum($unserviceable['damaged'] ?? []);
        $lost = $sum($unserviceable['lost'] ?? []);
        $retired = $sum($unserviceable['retired'] ?? []);

        $total = max(0, $owned - $retired - $maintenance - $damaged - $lost);

        $relevant = array_values(array_filter(
            $holds,
            fn (AvailabilityHold $h) => $h->quantity > 0 && $h->overlaps($from, $to),
        ));

        $deductKinds = [AvailabilityHold::RESERVED, AvailabilityHold::CHECKED_OUT];
        if ($deductTentative) {
            $deductKinds[] = AvailabilityHold::TENTATIVE;
        }

        [$peak, $peakAt, $peakHolds] = $this->peak(
            array_values(array_filter($relevant, fn (AvailabilityHold $h) => in_array($h->kind, $deductKinds, true))),
            $from,
            $to,
        );

        $reserved = 0;
        $checkedOut = 0;
        $tentativeDeducted = 0;
        foreach ($peakHolds as $hold) {
            match ($hold->kind) {
                AvailabilityHold::RESERVED => $reserved += $hold->quantity,
                AvailabilityHold::CHECKED_OUT => $checkedOut += $hold->quantity,
                AvailabilityHold::TENTATIVE => $tentativeDeducted += $hold->quantity,
            };
        }

        $tentativeHolds = array_values(array_filter($relevant, fn (AvailabilityHold $h) => $h->kind === AvailabilityHold::TENTATIVE));
        [$tentativePeak] = $deductTentative ? [$tentativeDeducted] : $this->peak($tentativeHolds, $from, $to);

        $sourcesOf = fn (string $kind) => array_values(array_map(
            fn (AvailabilityHold $h) => $h->source,
            array_filter($relevant, fn (AvailabilityHold $h) => $h->kind === $kind),
        ));

        return new AvailabilityBreakdown(
            owned: $owned,
            total: $total,
            reserved: $reserved,
            checkedOut: $checkedOut,
            tentative: $tentativePeak,
            maintenance: $maintenance,
            damaged: $damaged,
            lost: $lost,
            retired: $retired,
            available: $total - $peak,
            peakAt: $peakAt?->format(DATE_ATOM),
            sources: [
                'reserved' => $sourcesOf(AvailabilityHold::RESERVED),
                'checked_out' => $sourcesOf(AvailabilityHold::CHECKED_OUT),
                'tentative' => $sourcesOf(AvailabilityHold::TENTATIVE),
                'maintenance' => $unserviceable['maintenance'] ?? [],
                'damaged' => $unserviceable['damaged'] ?? [],
                'lost' => $unserviceable['lost'] ?? [],
                'retired' => $unserviceable['retired'] ?? [],
            ],
        );
    }

    /**
     * Maximum concurrent quantity across the window.
     *
     * @param  list<AvailabilityHold>  $holds
     * @return array{0: int, 1: ?DateTimeImmutable, 2: list<AvailabilityHold>}
     */
    public function peak(array $holds, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $events = [];
        foreach ($holds as $i => $hold) {
            $start = max($hold->startsAt, $from);
            $end = min($hold->endsAt, $to);
            if ($start >= $end) {
                continue;
            }
            $events[] = [$start->getTimestamp(), 1, $i];
            $events[] = [$end->getTimestamp(), -1, $i];
        }

        // Ends (-1) sort before starts (+1) at the same timestamp.
        usort($events, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $current = 0;
        $active = [];
        $peak = 0;
        $peakAt = null;
        $peakSet = [];
        foreach ($events as [$ts, $delta, $i]) {
            if ($delta > 0) {
                $active[$i] = true;
                $current += $holds[$i]->quantity;
                if ($current > $peak) {
                    $peak = $current;
                    $peakAt = (new DateTimeImmutable)->setTimestamp($ts);
                    $peakSet = array_keys($active);
                }
            } else {
                unset($active[$i]);
                $current -= $holds[$i]->quantity;
            }
        }

        return [$peak, $peakAt, array_map(fn ($i) => $holds[$i], $peakSet)];
    }

    /**
     * Daily availability series used by the explorer's timeline.
     *
     * @param  list<AvailabilityHold>  $holds
     * @return list<array{date: string, committed: int, tentative: int, available: int}>
     */
    public function timeline(int $total, array $holds, DateTimeImmutable $from, int $days, bool $deductTentative = false): array
    {
        $series = [];
        $day = $from->setTime(0, 0);
        for ($d = 0; $d < $days; $d++) {
            $start = $day->modify("+{$d} days");
            $end = $start->modify('+1 day');
            $committedHolds = array_values(array_filter($holds, fn (AvailabilityHold $h) => $h->kind !== AvailabilityHold::TENTATIVE || $deductTentative));
            $tentativeHolds = array_values(array_filter($holds, fn (AvailabilityHold $h) => $h->kind === AvailabilityHold::TENTATIVE));
            [$committed] = $this->peak($committedHolds, $start, $end);
            [$tentative] = $this->peak($tentativeHolds, $start, $end);
            $series[] = [
                'date' => $start->format('Y-m-d'),
                'committed' => $committed,
                'tentative' => $tentative,
                'available' => $total - $committed,
            ];
        }

        return $series;
    }
}
