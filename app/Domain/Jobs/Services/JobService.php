<?php

namespace App\Domain\Jobs\Services;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\AvailabilityService;
use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Jobs\Models\JobStageHistory;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Reservations\DTOs\ConflictReport;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Exceptions\OverbookingException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ConflictDetector;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JobService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ConflictDetector $detector,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Adds an allocation and (optionally) its auto-add accessories as child lines.
     */
    public function addItem(ProductionJob $job, Product $product, int $required, int $spare = 0, array $extra = [], bool $withAccessories = true): JobItem
    {
        return DB::transaction(function () use ($job, $product, $required, $spare, $extra, $withAccessories) {
            $item = $job->items()->create([
                'product_id' => $product->id,
                'required_quantity' => max(0, $required),
                'spare_quantity' => max(0, $spare),
                'section' => $extra['section'] ?? null,
                'notes' => $extra['notes'] ?? null,
                'unit_price' => $extra['unit_price'] ?? $product->rental_price,
                'sort_order' => (int) $job->items()->max('sort_order') + 1,
            ]);

            if ($withAccessories) {
                foreach ($product->accessories()->where('auto_add', true)->with('accessory')->get() as $acc) {
                    if (! $acc->accessory) {
                        continue;
                    }
                    $q = PullSheetBuilder::accessoryQuantities((float) $acc->quantity, $item->required_quantity, $item->spare_quantity);
                    $job->items()->create([
                        'product_id' => $acc->accessory_product_id,
                        'parent_item_id' => $item->id,
                        'section' => $item->section,
                        'required_quantity' => $q['required'],
                        'spare_quantity' => $q['spare'],
                        'unit_price' => 0,
                        'notes' => ucfirst((string) $acc->type).' accessory',
                        'sort_order' => $item->sort_order,
                    ]);
                }
            }

            $this->syncReservations($job->fresh());

            return $item;
        });
    }

    /** Updates quantities; auto-added accessory lines are rescaled to match. */
    public function updateItem(JobItem $item, array $data): JobItem
    {
        return DB::transaction(function () use ($item, $data) {
            $item->fill(array_intersect_key($data, array_flip(['required_quantity', 'spare_quantity', 'section', 'notes', 'unit_price', 'sort_order'])));
            $item->save();

            if ($item->wasChanged(['required_quantity', 'spare_quantity'])) {
                $perUnit = $item->product->accessories()->where('auto_add', true)->pluck('quantity', 'accessory_product_id');
                foreach ($item->children as $child) {
                    if (! isset($perUnit[$child->product_id])) {
                        continue;
                    }
                    $q = PullSheetBuilder::accessoryQuantities((float) $perUnit[$child->product_id], $item->required_quantity, $item->spare_quantity);
                    $child->update(['required_quantity' => $q['required'], 'spare_quantity' => $q['spare']]);
                }
            }

            $this->syncReservations($item->job);

            return $item->fresh();
        });
    }

    public function removeItem(JobItem $item): void
    {
        DB::transaction(function () use ($item) {
            $job = $item->job;
            $item->children()->each(fn (JobItem $c) => $c->delete());
            $item->delete();
            $this->syncReservations($job);
        });
    }

    /**
     * Moves a job to a new stage. Entering a confirmed-hold stage runs the
     * overbooking check; `$override` skips it (requires the
     * reservations.overbooking.approve permission, checked by the caller).
     */
    public function transition(ProductionJob $job, JobStage $target, ?User $user = null, ?string $notes = null, bool $override = false): ProductionJob
    {
        $from = $job->stage;
        if ($from === $target) {
            return $job;
        }
        if (! $from->canTransitionTo($target)) {
            throw new InvalidArgumentException("A job cannot move from {$from->label()} to {$target->label()}.");
        }
        if ($target->reservationStatus() !== null && $target->reservationStatus() !== ReservationStatus::Released && (! $job->holdStartsAt() || ! $job->holdEndsAt())) {
            throw new InvalidArgumentException('Set the job dates before moving it to '.$target->label().'.');
        }

        $becomesConfirmed = $target->reservationStatus() === ReservationStatus::Confirmed && $from->reservationStatus() !== ReservationStatus::Confirmed;
        if ($becomesConfirmed && ! $override && $this->settings->get('reservations.block_overbooking', true)) {
            $conflicts = array_values(array_filter($this->conflicts($job), fn (ConflictReport $c) => $c->hasConflict()));
            if ($conflicts !== []) {
                throw new OverbookingException($conflicts);
            }
        }

        return DB::transaction(function () use ($job, $from, $target, $user, $notes, $override) {
            $job->stage = $target;
            $job->save();

            JobStageHistory::query()->create([
                'job_id' => $job->id,
                'from_stage' => $from->value,
                'to_stage' => $target->value,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);

            if ($override) {
                AuditLog::record('job.overbooking_override', $job, [], ['stage' => $target->value], 'Overbooking check overridden', $user?->id);
            }

            $this->syncReservations($job);

            return $job;
        });
    }

    /**
     * Keeps one reservation per allocation in step with the job's stage and dates.
     */
    public function syncReservations(ProductionJob $job): void
    {
        $status = $job->stage->reservationStatus();
        $start = $job->holdStartsAt();
        $end = $job->holdEndsAt();

        $items = $job->items()->get();
        $keep = [];

        if ($status !== null && $start && $end && $end > $start) {
            foreach ($items as $item) {
                if ($item->pull_quantity === 0) {
                    continue;
                }
                $reservation = Reservation::query()->updateOrCreate(
                    ['job_item_id' => $item->id],
                    [
                        'product_id' => $item->product_id,
                        'job_id' => $job->id,
                        'quantity' => $item->required_quantity,
                        'spare_quantity' => $item->spare_quantity,
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'status' => $status,
                    ],
                );
                $keep[] = $reservation->id;
            }
        }

        // Only job-generated holds are managed here; standalone holds linked to the job are left alone.
        Reservation::query()->where('job_id', $job->id)->whereNotNull('job_item_id')->whereNotIn('id', $keep)->delete();
    }

    /**
     * Conflict report per product for this job (excluding the job's own holds).
     *
     * @return list<ConflictReport>
     */
    public function conflicts(ProductionJob $job): array
    {
        $start = $job->holdStartsAt();
        $end = $job->holdEndsAt();
        if (! $start || ! $end) {
            return [];
        }

        $items = $job->items()->with('product')->get();
        $requested = [];
        foreach ($items as $item) {
            $requested[$item->product_id] = ($requested[$item->product_id] ?? 0) + $item->pull_quantity;
        }
        if ($requested === []) {
            return [];
        }

        $products = $items->pluck('product')->filter()->unique('id')->values();
        $availability = $this->availability->forProducts($products, $start, $end, $job->id);
        $deductTentative = (bool) $this->settings->get('reservations.count_tentative', false);

        $reports = [];
        foreach ($products as $product) {
            $reports[] = $this->detector->check($product->id, $product->name, $availability[$product->id], $requested[$product->id], $deductTentative);
        }

        return $reports;
    }

    /** Pull sheet data for documents and warehouse pages. */
    public function pullSheet(ProductionJob $job): array
    {
        $items = $job->items()->with(['product.defaultLocation', 'assignedAssets.asset'])->orderBy('sort_order')->orderBy('id')->get();

        return app(PullSheetBuilder::class)->build($items->map(fn (JobItem $i) => [
            'id' => $i->id,
            'parent_id' => $i->parent_item_id,
            'section' => $i->section,
            'product_id' => $i->product_id,
            'product' => $i->product?->name,
            'sku' => $i->product?->sku,
            'location' => $i->product?->defaultLocation?->path(),
            'required' => $i->required_quantity,
            'spare' => $i->spare_quantity,
            'picked' => $i->picked_quantity,
            'checked_out' => $i->checked_out_quantity,
            'returned' => $i->returned_quantity,
            'weight_kg' => (float) $i->product?->weight_kg,
            'notes' => $i->notes,
            'assets' => $i->assignedAssets->map(fn ($a) => [
                'id' => $a->asset_id,
                'asset_number' => $a->asset?->asset_number,
                'serial_number' => $a->asset?->serial_number,
                'role' => $a->role,
                'status' => $a->status,
            ])->all(),
        ])->all());
    }
}
