<?php

namespace App\Domain\Jobs\Enums;

use App\Domain\Reservations\Enums\ReservationStatus;

/**
 * Job lifecycle. Order matters: it drives the stage rail in the UI and
 * determines how a job's allocations affect availability.
 */
enum JobStage: string
{
    case Inquiry = 'inquiry';
    case Draft = 'draft';
    case Quote = 'quote';
    case Approved = 'approved';
    case Reserved = 'reserved';
    case Prep = 'prep';
    case Ready = 'ready';
    case CheckedOut = 'checked_out';
    case Returned = 'returned';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::CheckedOut => 'Checked Out',
            default => ucfirst($this->value),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Inquiry => [self::Draft, self::Quote, self::Archived],
            self::Draft => [self::Inquiry, self::Quote, self::Archived],
            self::Quote => [self::Draft, self::Approved, self::Archived],
            self::Approved => [self::Quote, self::Reserved, self::Archived],
            self::Reserved => [self::Approved, self::Prep, self::Archived],
            self::Prep => [self::Reserved, self::Ready],
            self::Ready => [self::Prep, self::CheckedOut],
            self::CheckedOut => [self::Returned],
            self::Returned => [self::CheckedOut, self::Closed],
            self::Closed => [self::Returned, self::Archived],
            self::Archived => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * How reservations generated from this job's allocations should be held.
     * Quotes hold stock tentatively (shown as provisional, not deducted);
     * Reserved through Returned hold it firmly; terminal stages release it.
     */
    public function reservationStatus(): ?ReservationStatus
    {
        return match ($this) {
            self::Inquiry, self::Draft => null,
            self::Quote, self::Approved => ReservationStatus::Tentative,
            self::Reserved, self::Prep, self::Ready, self::CheckedOut, self::Returned => ReservationStatus::Confirmed,
            self::Closed, self::Archived => ReservationStatus::Released,
        };
    }

    /** Stages in which allocations may still be edited without approval. */
    public function allowsAllocationEdits(): bool
    {
        return in_array($this, [self::Inquiry, self::Draft, self::Quote, self::Approved, self::Reserved, self::Prep], true);
    }

    /** Stages that require the jobs.stages.approve permission to enter. */
    public function requiresApproval(): bool
    {
        return in_array($this, [self::Approved, self::Reserved], true);
    }

    public function isWarehouseActive(): bool
    {
        return in_array($this, [self::Reserved, self::Prep, self::Ready, self::CheckedOut, self::Returned], true);
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
