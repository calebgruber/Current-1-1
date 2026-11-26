<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\AvailabilityService;
use App\Domain\Reservations\DTOs\ConflictReport;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Exceptions\OverbookingException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Settings\Services\SettingsRepository;
use Carbon\CarbonImmutable;

/**
 * Standalone holds (class use, maintenance windows, loans). Job allocations
 * are managed by JobService::syncReservations().
 */
class ReservationService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ConflictDetector $detector,
        private readonly SettingsRepository $settings,
    ) {}

    public function check(Product $product, int $quantity, \DateTimeInterface $from, \DateTimeInterface $to, ?Reservation $ignore = null): ConflictReport
    {
        $available = $this->availability->forProduct($product, $from, $to, null, $ignore?->exists ? $ignore->id : null);

        return $this->detector->check($product->id, $product->name, $available, $quantity, (bool) $this->settings->get('reservations.count_tentative', false));
    }

    /** @throws OverbookingException */
    public function save(array $data, ?Reservation $reservation = null, bool $override = false, ?int $userId = null): Reservation
    {
        $reservation ??= new Reservation(['created_by' => $userId]);
        $product = Product::query()->findOrFail($data['product_id']);
        $from = CarbonImmutable::parse($data['starts_at']);
        $to = CarbonImmutable::parse($data['ends_at']);
        $quantity = (int) $data['quantity'] + (int) ($data['spare_quantity'] ?? 0);
        $status = ReservationStatus::from($data['status'] ?? ReservationStatus::Confirmed->value);

        if ($status === ReservationStatus::Confirmed && ! $override && $this->settings->get('reservations.block_overbooking', true)) {
            $report = $this->check($product, $quantity, $from, $to, $reservation);
            if ($report->hasConflict()) {
                throw new OverbookingException([$report]);
            }
        }

        $reservation->fill([
            'product_id' => $product->id,
            'name' => $data['name'] ?? null,
            'quantity' => (int) $data['quantity'],
            'spare_quantity' => (int) ($data['spare_quantity'] ?? 0),
            'starts_at' => $from,
            'ends_at' => $to,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ])->save();

        return $reservation;
    }
}
