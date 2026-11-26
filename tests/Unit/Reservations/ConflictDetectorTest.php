<?php

namespace Tests\Unit\Reservations;

use App\Domain\Inventory\DTOs\AllocationSource;
use App\Domain\Inventory\DTOs\AvailabilityBreakdown;
use App\Domain\Reservations\DTOs\ConflictReport;
use App\Domain\Reservations\Services\ConflictDetector;
use PHPUnit\Framework\TestCase;

class ConflictDetectorTest extends TestCase
{
    private ConflictDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new ConflictDetector;
    }

    private function breakdown(int $available, int $tentative = 0, array $sources = []): AvailabilityBreakdown
    {
        return new AvailabilityBreakdown(
            owned: 10, total: 10, reserved: 10 - $available, checkedOut: 0, tentative: $tentative,
            maintenance: 0, damaged: 0, lost: 0, retired: 0, available: $available, peakAt: null,
            sources: $sources + ['reserved' => [], 'checked_out' => [], 'tentative' => []],
        );
    }

    public function test_fits_returns_ok(): void
    {
        $r = $this->detector->check(1, 'Lustr', $this->breakdown(10), 6);

        $this->assertSame(ConflictReport::OK, $r->level);
        $this->assertFalse($r->hasConflict());
        $this->assertSame(0, $r->shortage());
        $this->assertSame('Lustr: 6 of 10 available.', $r->message);
    }

    public function test_exact_fit_is_ok(): void
    {
        $r = $this->detector->check(1, 'Lustr', $this->breakdown(6), 6);
        $this->assertSame(ConflictReport::OK, $r->level);
    }

    public function test_nothing_requested_is_ok(): void
    {
        $r = $this->detector->check(1, 'Lustr', $this->breakdown(-5), 0);
        $this->assertSame(ConflictReport::OK, $r->level);
        $this->assertSame('Nothing requested.', $r->message);
        $this->assertSame([], $r->competing);
    }

    public function test_overbooking_reports_shortage_and_lists_sources(): void
    {
        $sources = [
            'reserved' => [new AllocationSource('job', 3, 'J-00003 — Gala', 4, '/jobs/3')],
            'checked_out' => [new AllocationSource('job', 4, 'J-00004 — Tour', 2, '/jobs/4')],
            'tentative' => [new AllocationSource('job', 5, 'J-00005 — Quote', 1, '/jobs/5')],
        ];
        $r = $this->detector->check(9, 'Lustr', $this->breakdown(4, 1, $sources), 7);

        $this->assertSame(ConflictReport::SHORTAGE, $r->level);
        $this->assertTrue($r->hasConflict());
        $this->assertSame(3, $r->shortage());
        $this->assertSame('Lustr: 7 requested but only 4 available — short by 3.', $r->message);
        $this->assertCount(3, $r->competing);
        $this->assertSame(['J-00003 — Gala', 'J-00004 — Tour', 'J-00005 — Quote'], array_column($r->competing, 'label'));
        $this->assertSame('/jobs/3', $r->competing[0]['url']);

        $arr = $r->toArray();
        $this->assertSame(9, $arr['product_id']);
        $this->assertSame('Lustr', $arr['product']);
        $this->assertSame(3, $arr['shortage']);
        $this->assertSame('shortage', $arr['level']);
    }

    public function test_negative_availability_clamped_in_message(): void
    {
        $r = $this->detector->check(1, 'Lustr', $this->breakdown(-2), 3);

        $this->assertSame(ConflictReport::SHORTAGE, $r->level);
        $this->assertSame('Lustr: 3 requested but only 0 available — short by 3.', $r->message);
        $this->assertSame(-2, $r->available);
        // shortage() measures against raw availability
        $this->assertSame(5, $r->shortage());
    }

    public function test_tentative_competition_is_warning_not_conflict(): void
    {
        $sources = ['tentative' => [new AllocationSource('job', 5, 'Quote', 4)]];
        $r = $this->detector->check(1, 'Lustr', $this->breakdown(6, 4, $sources), 5);

        $this->assertSame(ConflictReport::TENTATIVE, $r->level);
        $this->assertFalse($r->hasConflict());
        $this->assertStringContainsString('4 unit(s) are on competing quotes', $r->message);
        $this->assertCount(1, $r->competing);
    }

    public function test_tentative_within_firm_headroom_is_ok(): void
    {
        $r = $this->detector->check(1, 'Lustr', $this->breakdown(6, 4), 2);
        $this->assertSame(ConflictReport::OK, $r->level);
    }

    public function test_no_tentative_warning_when_already_deducted(): void
    {
        $r = $this->detector->check(1, 'Lustr', $this->breakdown(6, 4), 5, true);
        $this->assertSame(ConflictReport::OK, $r->level);
    }
}
