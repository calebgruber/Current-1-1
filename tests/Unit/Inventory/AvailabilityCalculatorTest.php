<?php

namespace Tests\Unit\Inventory;

use App\Domain\Inventory\DTOs\AllocationSource;
use App\Domain\Inventory\DTOs\AvailabilityHold;
use App\Domain\Inventory\Services\AvailabilityCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AvailabilityCalculatorTest extends TestCase
{
    private AvailabilityCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new AvailabilityCalculator;
    }

    private function d(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new \DateTimeZone('UTC'));
    }

    private function src(string $label, int $qty, string $type = 'job', int $id = 1): AllocationSource
    {
        return new AllocationSource($type, $id, $label, $qty);
    }

    private function hold(string $kind, int $qty, string $start, string $end, string $label = 'Job', int $id = 1): AvailabilityHold
    {
        return new AvailabilityHold($kind, $qty, $this->d($start), $this->d($end), $this->src($label, $qty, 'job', $id));
    }

    public function test_no_holds_everything_available(): void
    {
        $b = $this->calc->calculate(10, [], [], $this->d('2026-01-01'), $this->d('2026-01-05'));

        $this->assertSame(10, $b->owned);
        $this->assertSame(10, $b->total);
        $this->assertSame(0, $b->reserved);
        $this->assertSame(0, $b->checkedOut);
        $this->assertSame(0, $b->tentative);
        $this->assertSame(10, $b->available);
        $this->assertNull($b->peakAt);
        $this->assertFalse($b->isShort());
        $this->assertSame([], $b->sources['reserved']);
    }

    public function test_unserviceable_buckets_reduce_total(): void
    {
        $b = $this->calc->calculate(20, [
            'maintenance' => [$this->src('M1', 2, 'maintenance')],
            'damaged' => [$this->src('D1', 1, 'asset'), $this->src('D2', 1, 'asset')],
            'lost' => [$this->src('L1', 3, 'asset')],
            'retired' => [$this->src('R1', 4, 'asset')],
        ], [], $this->d('2026-01-01'), $this->d('2026-01-02'));

        $this->assertSame(20, $b->owned);
        $this->assertSame(2, $b->maintenance);
        $this->assertSame(2, $b->damaged);
        $this->assertSame(3, $b->lost);
        $this->assertSame(4, $b->retired);
        $this->assertSame(9, $b->total);
        $this->assertSame(9, $b->available);
        $this->assertCount(2, $b->sources['damaged']);
        $this->assertSame('R1', $b->sources['retired'][0]->label);
    }

    public function test_total_never_negative(): void
    {
        $b = $this->calc->calculate(2, ['lost' => [$this->src('L', 5)]], [], $this->d('2026-01-01'), $this->d('2026-01-02'));
        $this->assertSame(0, $b->total);
        $this->assertSame(0, $b->available);
    }

    public function test_reserved_and_checked_out_holds_are_deducted(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::RESERVED, 3, '2026-01-01', '2026-01-10', 'Show A', 1),
            $this->hold(AvailabilityHold::CHECKED_OUT, 2, '2026-01-01', '2026-01-10', 'Show B', 2),
        ];
        $b = $this->calc->calculate(10, [], $holds, $this->d('2026-01-02'), $this->d('2026-01-03'));

        $this->assertSame(3, $b->reserved);
        $this->assertSame(2, $b->checkedOut);
        $this->assertSame(5, $b->available);
        $this->assertSame('Show A', $b->sources['reserved'][0]->label);
        $this->assertSame('Show B', $b->sources['checked_out'][0]->label);
    }

    public function test_holds_and_unserviceable_combine(): void
    {
        $holds = [$this->hold(AvailabilityHold::RESERVED, 6, '2026-01-01', '2026-01-10')];
        $b = $this->calc->calculate(10, ['maintenance' => [$this->src('M', 1)], 'retired' => [$this->src('R', 1)]], $holds, $this->d('2026-01-02'), $this->d('2026-01-03'));

        $this->assertSame(8, $b->total);
        $this->assertSame(2, $b->available);
    }

    public function test_holds_outside_window_are_ignored(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::RESERVED, 4, '2026-02-01', '2026-02-05'),
            $this->hold(AvailabilityHold::RESERVED, 4, '2025-12-01', '2025-12-05'),
        ];
        $b = $this->calc->calculate(10, [], $holds, $this->d('2026-01-01'), $this->d('2026-01-31'));

        $this->assertSame(10, $b->available);
        $this->assertSame(0, $b->reserved);
        $this->assertSame([], $b->sources['reserved']);
    }

    public function test_back_to_back_windows_do_not_conflict(): void
    {
        // Half-open intervals: a hold ending exactly at window start does not overlap.
        $holds = [$this->hold(AvailabilityHold::RESERVED, 5, '2026-01-01', '2026-01-05')];
        $b = $this->calc->calculate(10, [], $holds, $this->d('2026-01-05'), $this->d('2026-01-07'));
        $this->assertSame(10, $b->available);

        $holds = [$this->hold(AvailabilityHold::RESERVED, 5, '2026-01-07', '2026-01-09')];
        $b = $this->calc->calculate(10, [], $holds, $this->d('2026-01-05'), $this->d('2026-01-07'));
        $this->assertSame(10, $b->available);
    }

    public function test_consecutive_holds_are_not_summed(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::RESERVED, 10, '2026-01-01', '2026-01-08', 'Week 1', 1),
            $this->hold(AvailabilityHold::RESERVED, 10, '2026-01-08', '2026-01-15', 'Week 2', 2),
        ];
        $b = $this->calc->calculate(12, [], $holds, $this->d('2026-01-01'), $this->d('2026-01-15'));

        $this->assertSame(10, $b->reserved);
        $this->assertSame(2, $b->available);
        // Both are listed as sources even though only one contributes to the peak.
        $this->assertCount(2, $b->sources['reserved']);
    }

    public function test_overlapping_holds_are_summed_at_peak(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::RESERVED, 4, '2026-01-01', '2026-01-10', 'A', 1),
            $this->hold(AvailabilityHold::RESERVED, 3, '2026-01-05', '2026-01-12', 'B', 2),
            $this->hold(AvailabilityHold::RESERVED, 6, '2026-01-11', '2026-01-20', 'C', 3),
        ];
        $b = $this->calc->calculate(10, [], $holds, $this->d('2026-01-01'), $this->d('2026-01-31'));

        // A+B = 7 on Jan 5..10, B+C = 9 on Jan 11..12.
        $this->assertSame(9, $b->reserved);
        $this->assertSame(1, $b->available);
        $this->assertSame('2026-01-11T00:00:00+00:00', $b->peakAt);
    }

    public function test_overbooking_yields_negative_available(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::RESERVED, 8, '2026-01-01', '2026-01-10', 'A', 1),
            $this->hold(AvailabilityHold::CHECKED_OUT, 5, '2026-01-01', '2026-01-10', 'B', 2),
        ];
        $b = $this->calc->calculate(10, [], $holds, $this->d('2026-01-02'), $this->d('2026-01-03'));

        $this->assertSame(-3, $b->available);
        $this->assertTrue($b->isShort());
        $this->assertSame(3, $b->toArray()['shortage']);
    }

    public function test_hold_quantity_includes_spares(): void
    {
        // A job reserving 20 required + 5 spare produces a hold of 25 units.
        $source = new AllocationSource('job', 7, 'J-00007 — Gala', 25, '/jobs/7', meta: ['required' => 20, 'spare' => 5]);
        $hold = new AvailabilityHold(AvailabilityHold::RESERVED, 25, $this->d('2026-01-01'), $this->d('2026-01-04'), $source);
        $b = $this->calc->calculate(30, [], [$hold], $this->d('2026-01-02'), $this->d('2026-01-03'));

        $this->assertSame(25, $b->reserved);
        $this->assertSame(5, $b->available);
        $this->assertSame(5, $b->sources['reserved'][0]->meta['spare']);
    }

    public function test_tentative_holds_not_deducted_by_default(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::TENTATIVE, 4, '2026-01-01', '2026-01-10', 'Quote', 1),
            $this->hold(AvailabilityHold::RESERVED, 2, '2026-01-01', '2026-01-10', 'Firm', 2),
        ];
        $b = $this->calc->calculate(10, [], $holds, $this->d('2026-01-02'), $this->d('2026-01-03'));

        $this->assertSame(4, $b->tentative);
        $this->assertSame(2, $b->reserved);
        $this->assertSame(8, $b->available);
        $this->assertSame('Quote', $b->sources['tentative'][0]->label);
    }

    public function test_tentative_holds_deducted_when_requested(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::TENTATIVE, 4, '2026-01-01', '2026-01-10', 'Quote', 1),
            $this->hold(AvailabilityHold::RESERVED, 2, '2026-01-01', '2026-01-10', 'Firm', 2),
        ];
        $b = $this->calc->calculate(10, [], $holds, $this->d('2026-01-02'), $this->d('2026-01-03'), true);

        $this->assertSame(4, $b->tentative);
        $this->assertSame(2, $b->reserved);
        $this->assertSame(4, $b->available);
    }

    public function test_zero_quantity_holds_are_ignored(): void
    {
        $holds = [$this->hold(AvailabilityHold::RESERVED, 0, '2026-01-01', '2026-01-10')];
        $b = $this->calc->calculate(5, [], $holds, $this->d('2026-01-02'), $this->d('2026-01-03'));
        $this->assertSame(5, $b->available);
        $this->assertSame([], $b->sources['reserved']);
    }

    public function test_peak_clips_holds_to_window(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::RESERVED, 3, '2026-01-01', '2026-01-05'),
            $this->hold(AvailabilityHold::RESERVED, 4, '2026-01-06', '2026-01-08'),
        ];
        [$peak, $at, $set] = $this->calc->peak($holds, $this->d('2026-01-02'), $this->d('2026-01-04'));
        $this->assertSame(3, $peak);
        $this->assertSame('2026-01-02', $at->format('Y-m-d'));
        $this->assertCount(1, $set);
    }

    public function test_peak_empty_returns_zero(): void
    {
        $this->assertSame([0, null, []], $this->calc->peak([], $this->d('2026-01-01'), $this->d('2026-01-02')));
    }

    public function test_timeline_reports_daily_series(): void
    {
        $holds = [
            $this->hold(AvailabilityHold::RESERVED, 3, '2026-01-02', '2026-01-04'),
            $this->hold(AvailabilityHold::TENTATIVE, 2, '2026-01-03', '2026-01-05'),
        ];
        $series = $this->calc->timeline(10, $holds, $this->d('2026-01-01 15:00'), 5);

        $this->assertCount(5, $series);
        $this->assertSame(['date' => '2026-01-01', 'committed' => 0, 'tentative' => 0, 'available' => 10], $series[0]);
        $this->assertSame(['date' => '2026-01-02', 'committed' => 3, 'tentative' => 0, 'available' => 7], $series[1]);
        $this->assertSame(['date' => '2026-01-03', 'committed' => 3, 'tentative' => 2, 'available' => 7], $series[2]);
        $this->assertSame(['date' => '2026-01-04', 'committed' => 0, 'tentative' => 2, 'available' => 10], $series[3]);
        $this->assertSame(0, $series[4]['committed']);

        $deducted = $this->calc->timeline(10, $holds, $this->d('2026-01-01'), 5, true);
        $this->assertSame(5, $deducted[2]['committed']);
        $this->assertSame(5, $deducted[2]['available']);
    }

    public function test_to_array_serialises_sources(): void
    {
        $holds = [$this->hold(AvailabilityHold::RESERVED, 2, '2026-01-01', '2026-01-10', 'Show', 9)];
        $arr = $this->calc->calculate(5, [], $holds, $this->d('2026-01-02'), $this->d('2026-01-03'))->toArray();

        $this->assertSame(3, $arr['available']);
        $this->assertSame(0, $arr['shortage']);
        $this->assertSame('Show', $arr['sources']['reserved'][0]['label']);
        $this->assertSame(9, $arr['sources']['reserved'][0]['id']);
    }
}
