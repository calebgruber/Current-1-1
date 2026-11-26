<?php

namespace App\Domain\Reservations\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Inventory\Models\Product;
use App\Domain\Reservations\DTOs\ConflictReport;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Exceptions\OverbookingException;
use App\Domain\Reservations\Http\Requests\ReservationRequest;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ReservationService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly CustomFieldService $customFields,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('reservations.reservations.view');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'source' => ['nullable', 'in:standalone,job'],
            'product_id' => ['nullable', 'integer'],
            'job_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'view' => ['nullable', 'in:list,timeline'],
        ]);
        $view = $filters['view'] ?? 'list';

        $query = Reservation::query()
            ->with(['product:id,name,sku', 'job:id,number,name,stage', 'creator:id,name'])
            ->when($filters['search'] ?? null, function ($q, $s) {
                $like = '%'.addcslashes($s, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                    ->orWhereHas('job', fn ($j) => $j->where('number', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when(($filters['source'] ?? null) === 'standalone', fn ($q) => $q->whereNull('job_item_id'))
            ->when(($filters['source'] ?? null) === 'job', fn ($q) => $q->whereNotNull('job_item_id'))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['job_id'] ?? null, fn ($q, $id) => $q->where('job_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('ends_at', '>=', CarbonImmutable::parse($d)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('starts_at', '<=', CarbonImmutable::parse($d)->endOfDay()));

        $timeline = null;
        $reservations = null;
        if ($view === 'timeline') {
            $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from'])->startOfDay() : CarbonImmutable::today()->subDays(3);
            $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to'])->endOfDay() : $from->addDays(30)->endOfDay();
            if ($to->diffInDays($from, true) > 120) {
                $to = $from->addDays(120)->endOfDay();
            }
            $rows = (clone $query)->overlapping($from, $to)->where('status', '!=', ReservationStatus::Released->value)
                ->orderBy('starts_at')->limit(300)->get();
            $timeline = [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'groups' => $rows->groupBy('product_id')->map(fn ($g) => [
                    'product_id' => $g->first()->product_id,
                    'product' => $g->first()->product?->name,
                    'rows' => $g->map(fn (Reservation $r) => $this->row($r))->values(),
                ])->values(),
            ];
        } else {
            $reservations = (clone $query)->orderByDesc('starts_at')->orderByDesc('id')
                ->paginate(25)->withQueryString()->through(fn (Reservation $r) => $this->row($r));
        }

        return view('reservations.index', [
            'reservations' => $reservations,
            'timeline' => $timeline,
            'view' => $view,
            'filters' => array_merge(['search' => null, 'status' => null, 'source' => null, 'from' => null, 'to' => null], array_intersect_key($filters, array_flip(['search', 'status', 'source', 'from', 'to', 'product_id', 'job_id']))),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('reservations.reservations.create');
        $defaults = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'job_id' => ['nullable', 'integer', 'exists:production_jobs,id'],
        ]);
        $product = isset($defaults['product_id']) ? Product::query()->find($defaults['product_id']) : null;
        $job = isset($defaults['job_id']) ? \App\Domain\Jobs\Models\ProductionJob::query()->find($defaults['job_id']) : null;

        return view('reservations.form', [
            'reservation' => null,
            'defaults' => [
                'product_id' => $product?->id,
                'product_label' => $product?->name,
                'job_id' => $job?->id,
                'job_label' => $job ? $job->number.' — '.$job->name : null,
                'starts_at' => $job?->holdStartsAt()?->toIso8601String(),
                'ends_at' => $job?->holdEndsAt()?->toIso8601String(),
            ],
            'statuses' => $this->statusOptions(),
            'customFieldSchema' => $this->customFields->schema('reservation'),
            'customFields' => [],
            'canOverride' => Gate::allows('reservations.overbooking.approve'),
        ]);
    }

    public function store(ReservationRequest $request): RedirectResponse
    {
        $reservation = $this->persist($request, new Reservation);

        return redirect()->route('reservations.show', $reservation)->with('success', 'Reservation saved.');
    }

    public function show(Request $request, Reservation $reservation): View
    {
        Gate::authorize('reservations.reservations.view');
        $reservation->load(['product', 'job:id,number,name,stage', 'creator:id,name']);
        $report = $reservation->status !== ReservationStatus::Released && $reservation->product
            ? $this->reservations->check($reservation->product, $reservation->totalQuantity(), $reservation->starts_at, $reservation->ends_at, $reservation)
            : null;
        $user = $request->user();

        return view('reservations.show', [
            'reservation' => $this->row($reservation) + ['notes' => $reservation->notes],
            'conflict' => $report?->toArray(),
            'customFieldSchema' => $this->customFields->schema('reservation'),
            'customFields' => $reservation->customFields(),
            'statuses' => $this->statusOptions(),
            'can' => [
                'edit' => ! $reservation->job_item_id && $user->can('reservations.reservations.edit'),
                'delete' => ! $reservation->job_item_id && $user->can('reservations.reservations.delete'),
                'approve' => ! $reservation->job_item_id && $user->can('reservations.reservations.approve'),
                'override' => $user->can('reservations.overbooking.approve'),
            ],
        ]);
    }

    public function edit(Reservation $reservation): View
    {
        Gate::authorize('reservations.reservations.edit');
        $this->assertStandalone($reservation);
        $reservation->load(['product:id,name', 'job:id,number,name']);

        return view('reservations.form', [
            'reservation' => $this->row($reservation) + ['notes' => $reservation->notes],
            'defaults' => null,
            'statuses' => $this->statusOptions(),
            'customFieldSchema' => $this->customFields->schema('reservation'),
            'customFields' => $reservation->customFields(),
            'canOverride' => Gate::allows('reservations.overbooking.approve'),
        ]);
    }

    public function update(ReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $this->assertStandalone($reservation);
        $this->persist($request, $reservation);

        return redirect()->route('reservations.show', $reservation)->with('success', 'Reservation updated.');
    }

    public function destroy(Reservation $reservation): RedirectResponse
    {
        Gate::authorize('reservations.reservations.delete');
        $this->assertStandalone($reservation);
        $reservation->delete();

        return redirect()->route('reservations.index')->with('success', 'Reservation deleted.');
    }

    /** Live availability check for the reservation form. */
    public function check(Request $request): JsonResponse
    {
        Gate::any(['reservations.reservations.create', 'reservations.reservations.edit', 'reservations.reservations.view']) || abort(403);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'spare_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reservation_id' => ['nullable', 'integer'],
        ]);
        $product = Product::query()->findOrFail($data['product_id']);
        $ignore = isset($data['reservation_id']) ? Reservation::query()->find($data['reservation_id']) : null;
        $report = $this->reservations->check(
            $product,
            (int) $data['quantity'] + (int) ($data['spare_quantity'] ?? 0),
            CarbonImmutable::parse($data['starts_at']),
            CarbonImmutable::parse($data['ends_at']),
            $ignore,
        );

        return response()->json(['conflict' => $report->toArray()]);
    }

    public function confirm(Request $request, Reservation $reservation): RedirectResponse
    {
        Gate::authorize('reservations.reservations.approve');
        $this->assertStandalone($reservation);
        $data = $request->validate([
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override,true', 'string', 'min:5', 'max:500'],
        ]);

        $this->save($request, $reservation, [
            'product_id' => $reservation->product_id,
            'job_id' => $reservation->job_id,
            'name' => $reservation->name,
            'quantity' => $reservation->quantity,
            'spare_quantity' => $reservation->spare_quantity,
            'starts_at' => $reservation->starts_at,
            'ends_at' => $reservation->ends_at,
            'status' => ReservationStatus::Confirmed->value,
            'notes' => $reservation->notes,
        ], $data['override_reason'] ?? null);

        return back()->with('success', 'Reservation confirmed.');
    }

    public function release(Reservation $reservation): RedirectResponse
    {
        Gate::any(['reservations.reservations.edit', 'reservations.reservations.approve']) || abort(403);
        $this->assertStandalone($reservation);
        $reservation->update(['status' => ReservationStatus::Released]);

        return back()->with('success', 'Reservation released — stock is available again.');
    }

    private function persist(ReservationRequest $request, Reservation $reservation): Reservation
    {
        $clean = $this->customFields->validate('reservation', $request->input('custom_fields', []) ?? [], partial: false);
        $data = collect($request->validated())->only(['product_id', 'job_id', 'name', 'quantity', 'spare_quantity', 'starts_at', 'ends_at', 'status', 'notes'])->all();
        if (! $reservation->exists) {
            $reservation->created_by = $request->user()->id;
        }

        return DB::transaction(function () use ($request, $reservation, $data, $clean) {
            $saved = $this->save($request, $reservation, $data, $request->validated('override_reason'));
            $this->customFields->save($saved, $clean);

            return $saved;
        });
    }

    private function save(Request $request, Reservation $reservation, array $data, ?string $reason): Reservation
    {
        $override = $request->boolean('override');
        if ($override) {
            Gate::authorize('reservations.overbooking.approve');
        }

        try {
            $saved = $this->reservations->save($data, $reservation, $override, $request->user()->id);
        } catch (OverbookingException $e) {
            $message = 'Overbooked: '.$e->getMessage();
            if (Gate::allows('reservations.overbooking.approve')) {
                $message .= ' Tick "Override overbooking" and give a reason to save anyway.';
            }
            throw ValidationException::withMessages(['quantity' => $message]);
        }

        if ($override && $saved->status === ReservationStatus::Confirmed) {
            $report = $this->reservations->check($saved->product, $saved->totalQuantity(), $saved->starts_at, $saved->ends_at, $saved);
            if ($report->hasConflict()) {
                AuditLog::record('reservation.overbooking_override', $saved, [], ['shortage' => $report->shortage()], 'Overbooking override: '.$reason, $request->user()->id);
            }
        }

        return $saved;
    }

    private function row(Reservation $r): array
    {
        return [
            'id' => $r->id,
            'label' => $r->label(),
            'name' => $r->name,
            'product_id' => $r->product_id,
            'product' => $r->product?->name,
            'sku' => $r->product?->sku,
            'job_id' => $r->job_id,
            'job' => $r->job ? ['id' => $r->job->id, 'number' => $r->job->number, 'name' => $r->job->name, 'stage' => $r->job->stage->value] : null,
            'job_generated' => $r->job_item_id !== null,
            'quantity' => $r->quantity,
            'spare_quantity' => $r->spare_quantity,
            'total' => $r->totalQuantity(),
            'status' => $r->status->value,
            'starts_at' => $r->starts_at?->toIso8601String(),
            'ends_at' => $r->ends_at?->toIso8601String(),
            'created_by' => $r->creator?->name,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function statusOptions(): array
    {
        return array_map(fn (ReservationStatus $s) => ['value' => $s->value, 'label' => $s->label()], ReservationStatus::cases());
    }

    private function assertStandalone(Reservation $reservation): void
    {
        if ($reservation->job_item_id !== null) {
            abort(409, 'This hold is generated from a job allocation — change the job instead.');
        }
    }
}
