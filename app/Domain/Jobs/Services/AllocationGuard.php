<?php

namespace App\Domain\Jobs\Services;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\AvailabilityService;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Reservations\DTOs\ConflictReport;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Services\ConflictDetector;
use App\Domain\Settings\Services\SettingsRepository;

/**
 * Availability checks for allocation edits and per-line availability for the
 * job page. Allocations on jobs that already hold stock firmly (Reserved and
 * later) may not overbook unless explicitly overridden.
 */
class AllocationGuard
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ConflictDetector $detector,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Conflict report for the job's total pull of $product after adding
     * $additional units (excluding $replacing, whose quantity is being edited).
     */
    public function check(ProductionJob $job, Product $product, int $additional, ?JobItem $replacing = null, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): ?ConflictReport
    {
        $from ??= $job->holdStartsAt();
        $to ??= $job->holdEndsAt();
        if (! $from || ! $to || $to <= $from) {
            return null;
        }

        $existing = $job->items()->where('product_id', $product->id)
            ->when($replacing, fn ($q) => $q->whereKeyNot($replacing->id))
            ->get()->sum(fn (JobItem $i) => $i->pull_quantity);

        $breakdown = $this->availability->forProduct($product, $from, $to, $job->id);

        return $this->detector->check($product->id, $product->name, $breakdown, $existing + max(0, $additional), $this->countTentative());
    }

    /** Whether a shortage must block the change for this job's stage. */
    public function blocks(ProductionJob $job, ?ConflictReport $report): bool
    {
        return $report !== null
            && $report->hasConflict()
            && $job->stage->reservationStatus() === ReservationStatus::Confirmed
            && (bool) $this->settings->get('reservations.block_overbooking', true);
    }

    /**
     * Per-product availability for every allocated product on the job.
     *
     * @return array<int, array<string, mixed>> keyed by product id
     */
    public function lineAvailability(ProductionJob $job): array
    {
        $from = $job->holdStartsAt();
        $to = $job->holdEndsAt();
        $items = $job->items()->with('product')->get();
        if (! $from || ! $to || $to <= $from || $items->isEmpty()) {
            return [];
        }

        $requested = [];
        foreach ($items as $item) {
            $requested[$item->product_id] = ($requested[$item->product_id] ?? 0) + $item->pull_quantity;
        }
        $products = $items->pluck('product')->filter()->unique('id')->values();
        $breakdowns = $this->availability->forProducts($products, $from, $to, $job->id);

        $out = [];
        foreach ($products as $product) {
            $b = $breakdowns[$product->id];
            $report = $this->detector->check($product->id, $product->name, $b, $requested[$product->id], $this->countTentative());
            $out[$product->id] = $report->toArray() + [
                'total' => $b->total,
                'owned' => $b->owned,
                'reserved' => $b->reserved,
                'checked_out' => $b->checkedOut,
                'maintenance' => $b->maintenance + $b->damaged + $b->lost,
            ];
        }

        return $out;
    }

    public function countTentative(): bool
    {
        return (bool) $this->settings->get('reservations.count_tentative', false);
    }

    public function blockingEnabled(): bool
    {
        return (bool) $this->settings->get('reservations.block_overbooking', true);
    }
}
