<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Jobs\Http\Requests\JobRequest;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Jobs\Services\AllocationGuard;
use App\Domain\Jobs\Services\JobPresenter;
use App\Domain\Jobs\Services\JobService;
use App\Domain\Administration\Models\AuditLog;
use App\Domain\Reservations\DTOs\ConflictReport;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Rules\DTOs\RuleFinding;
use App\Domain\Rules\Services\JobValidationService;
use App\Domain\Settings\Services\SettingsRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function __construct(
        private readonly JobPresenter $presenter,
        private readonly CustomFieldService $customFields,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('jobs.jobs.view');

        $filters = $request->validate([
            'stage' => ['nullable', 'string', 'in:'.implode(',', array_column(JobStage::cases(), 'value'))],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'view' => ['nullable', 'in:list,board'],
            'sort' => ['nullable', 'in:number,name,starts_at,stage,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'archived' => ['nullable', 'boolean'],
        ]);
        $view = $filters['view'] ?? 'list';

        $base = ProductionJob::query()
            ->with(['client:id,name', 'venue:id,name', 'owner:id,name'])
            ->withCount('items')
            ->withSum('items as required_total', 'required_quantity')
            ->withSum('items as spare_total', 'spare_quantity')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('name', 'like', $like)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)));
            })
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where(fn ($w) => $w
                ->where(DB::raw('COALESCE(returns_at, ends_at, starts_at)'), '>=', Carbon::parse($from)->startOfDay())
                ->orWhereNull('starts_at')))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where(DB::raw('COALESCE(prep_starts_at, starts_at)'), '<=', Carbon::parse($to)->endOfDay()));

        $map = function (ProductionJob $job) {
            $job->pull_total = (int) $job->required_total + (int) $job->spare_total;

            return $this->presenter->row($job);
        };

        $counts = (clone $base)->reorder()->select('stage', DB::raw('count(*) as aggregate'))->groupBy('stage')->pluck('aggregate', 'stage');

        $jobs = null;
        $board = null;
        if ($view === 'board') {
            $board = [];
            foreach (JobStage::cases() as $stage) {
                if ($stage === JobStage::Archived && ! ($filters['archived'] ?? false)) {
                    continue;
                }
                $board[$stage->value] = (clone $base)->where('stage', $stage->value)
                    ->orderByRaw('starts_at IS NULL')->orderBy('starts_at')->limit(50)->get()->map($map)->all();
            }
        } else {
            $sort = $filters['sort'] ?? 'starts_at';
            $direction = $filters['direction'] ?? ($sort === 'starts_at' ? 'asc' : 'desc');
            $jobs = (clone $base)
                ->when($filters['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))
                ->when(! ($filters['stage'] ?? null) && ! ($filters['archived'] ?? false), fn ($q) => $q->where('stage', '!=', JobStage::Archived->value))
                ->when($sort === 'starts_at', fn ($q) => $q->orderByRaw('starts_at IS NULL'))
                ->orderBy($sort, $direction)->orderByDesc('id')
                ->paginate(25)->withQueryString()->through($map);

            $ids = collect($jobs->items())->pluck('id')->all();
            $customValues = $this->customFields->valuesForMany('job', $ids);
            $jobs->through(fn (array $row) => $row + ['custom_fields' => $customValues[$row['id']] ?? []]);
        }

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'board' => $board,
            'view' => $view,
            'filters' => [
                'stage' => $filters['stage'] ?? null,
                'search' => $filters['search'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'archived' => (bool) ($filters['archived'] ?? false),
            ],
            'sort' => $filters['sort'] ?? 'starts_at',
            'direction' => $filters['direction'] ?? 'asc',
            'stages' => JobStage::options(),
            'stageCounts' => $counts,
            'customColumns' => $this->customFields->listColumns('job'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('jobs.jobs.create');

        return Inertia::render('Jobs/Form', [
            'job' => null,
            'contacts' => [],
            'customFieldSchema' => $this->customFields->schema('job'),
            'customFields' => [],
            'confirmedHold' => false,
        ]);
    }

    public function store(JobRequest $request): RedirectResponse
    {
        $clean = $this->customFields->validate('job', $request->input('custom_fields', []) ?? [], partial: false);

        $job = DB::transaction(function () use ($request, $clean) {
            $job = new ProductionJob($request->jobAttributes());
            $job->stage = JobStage::from($request->validated('stage') ?? JobStage::Inquiry->value);
            $job->owner_id ??= $request->user()->id;
            $job->save();
            $this->syncContacts($job, $request->validated('contacts') ?? []);
            $this->customFields->save($job, $clean);

            return $job;
        });

        return redirect()->route('jobs.show', $job)->with('success', "Job {$job->number} created.");
    }

    public function show(Request $request, ProductionJob $job, AllocationGuard $guard, JobValidationService $validation): Response
    {
        Gate::authorize('jobs.jobs.view');
        $user = $request->user();

        $job->load(['client:id,name,email,phone', 'venue:id,name', 'owner:id,name', 'contacts:id,name,email,phone']);
        $items = $job->items()->with(['product:id,name,sku,tracking', 'assignedAssets.asset:id,asset_number,serial_number,status'])->get();
        $findings = array_map(fn (RuleFinding $f) => $f->toArray(), $validation->validate($job));
        $availability = $guard->lineAvailability($job);

        $totals = ['required' => 0, 'spare' => 0, 'pull' => 0, 'picked' => 0, 'checked_out' => 0, 'returned' => 0];
        foreach ($items as $i) {
            $totals['required'] += $i->required_quantity;
            $totals['spare'] += $i->spare_quantity;
            $totals['pull'] += $i->pull_quantity;
            $totals['picked'] += $i->picked_quantity;
            $totals['checked_out'] += $i->checked_out_quantity;
            $totals['returned'] += $i->returned_quantity;
        }

        $canFilesView = $user->can('jobs.files.view') || $user->can('jobs.jobs.edit');

        return Inertia::render('Jobs/Show', [
            'job' => $this->presenter->header($job, $user) + [
                'client_detail' => $job->client ? ['id' => $job->client->id, 'name' => $job->client->name, 'email' => $job->client->email, 'phone' => $job->client->phone] : null,
            ],
            'contacts' => $job->contacts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'phone' => $c->phone, 'role' => $c->pivot->role])->values(),
            'items' => $items->map(fn ($i) => $this->presenter->item($i))->values(),
            'availability' => (object) $availability,
            'findings' => $findings,
            'totals' => $totals,
            'stageHistory' => $job->stageHistory()->with('user:id,name')->limit(50)->get()->map(fn ($h) => [
                'id' => $h->id,
                'from' => $h->from_stage,
                'to' => $h->to_stage,
                'user' => $h->user?->name,
                'notes' => $h->notes,
                'at' => $h->created_at?->toIso8601String(),
            ]),
            'notes' => $canFilesView ? $job->notes()->with('user:id,name')->limit(100)->get()->map(fn ($n) => $this->presenter->note($n, $user)) : [],
            'attachments' => $canFilesView ? $job->attachments()->with('user:id,name')->limit(100)->get()->map(fn ($a) => $this->presenter->attachment($job, $a)) : [],
            'documents' => $user->can('documents.documents.view') ? $this->presenter->documents($job) : [],
            'templates' => $user->can('documents.documents.create') ? $this->presenter->documentTemplates() : [],
            'stages' => JobStage::options(),
            'customFieldSchema' => $this->customFields->schema('job'),
            'customFields' => $job->customFields(),
            'blockOverbooking' => $guard->blockingEnabled(),
            'can' => [
                'edit' => $user->can('jobs.jobs.edit'),
                'delete' => $user->can('jobs.jobs.delete'),
                'approve' => $user->can('jobs.jobs.approve') || $user->can('jobs.stages.approve'),
                'items_create' => $user->can('jobs.items.create'),
                'items_edit' => $user->can('jobs.items.edit'),
                'items_delete' => $user->can('jobs.items.delete'),
                'files_view' => $canFilesView,
                'files_create' => $user->can('jobs.files.create'),
                'files_delete' => $user->can('jobs.files.delete'),
                'notes_create' => $user->can('jobs.files.create') || $user->can('jobs.jobs.edit'),
                'override' => $user->can('reservations.overbooking.approve'),
                'generate_documents' => $user->can('documents.documents.create'),
            ],
        ]);
    }

    public function edit(ProductionJob $job): Response
    {
        Gate::authorize('jobs.jobs.edit');
        $job->load(['client:id,name', 'venue:id,name', 'owner:id,name', 'contacts:id,name']);

        return Inertia::render('Jobs/Form', [
            'job' => $this->presenter->header($job, request()->user()) + [
                'client_label' => $job->client?->name,
                'venue_label' => $job->venue?->name,
                'owner_label' => $job->owner?->name,
            ],
            'contacts' => $job->contacts->map(fn ($c) => ['contact_id' => $c->id, 'label' => $c->name, 'role' => $c->pivot->role])->values(),
            'customFieldSchema' => $this->customFields->schema('job'),
            'customFields' => $job->customFields(),
            'confirmedHold' => $job->stage->reservationStatus() === ReservationStatus::Confirmed,
        ]);
    }

    public function update(JobRequest $request, ProductionJob $job, JobService $jobs, AllocationGuard $guard, SettingsRepository $settings): RedirectResponse
    {
        $submitted = $request->input('custom_fields', []) ?? [];
        $effective = array_replace($job->customFields(), $submitted);
        $validated = $this->customFields->validate('job', $effective, partial: false);
        $clean = array_intersect_key($validated, $submitted);
        $job->fill($request->jobAttributes());
        $datesChanged = $job->isDirty(['prep_starts_at', 'starts_at', 'ends_at', 'returns_at']);

        // Moving the dates of a firmly-held job must not silently overbook.
        if ($datesChanged && $job->stage->reservationStatus() === ReservationStatus::Confirmed) {
            if (! $job->holdStartsAt() || ! $job->holdEndsAt()) {
                throw ValidationException::withMessages(['starts_at' => 'A '.$job->stage->label().' job needs start and return dates.']);
            }
            $override = $request->boolean('override');
            if ($override) {
                Gate::authorize('reservations.overbooking.approve');
            }
            $shortages = array_filter($jobs->conflicts($job), fn (ConflictReport $r) => $r->hasConflict());
            if ($shortages && ! $override && $settings->get('reservations.block_overbooking', true)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'These dates would overbook: '.implode(' ', array_map(fn (ConflictReport $r) => $r->message, $shortages)),
                ]);
            }
            if ($shortages && $override) {
                AuditLog::record('job.overbooking_override', $job, [], ['dates' => true], 'Date change overbooking override: '.$request->validated('override_reason'));
            }
        }

        DB::transaction(function () use ($job, $request, $clean, $datesChanged, $jobs) {
            $job->save();
            $this->syncContacts($job, $request->validated('contacts') ?? []);
            $this->customFields->save($job, $clean);
            if ($datesChanged) {
                $jobs->syncReservations($job);
            }
        });

        return redirect()->route('jobs.show', $job)->with('success', 'Job updated.');
    }

    public function destroy(ProductionJob $job): RedirectResponse
    {
        Gate::authorize('jobs.jobs.delete');
        if (in_array($job->stage, [JobStage::CheckedOut, JobStage::Returned], true)) {
            return back()->with('error', 'Equipment is still out on this job. Return and close it before deleting.');
        }

        DB::transaction(function () use ($job) {
            Reservation::query()->where('job_id', $job->id)->delete();
            $job->delete();
        });

        return redirect()->route('jobs.index')->with('success', "Job {$job->number} deleted.");
    }

    private function syncContacts(ProductionJob $job, array $contacts): void
    {
        $sync = [];
        foreach ($contacts as $c) {
            $sync[(int) $c['contact_id']] = ['role' => $c['role'] ?? null];
        }
        $job->contacts()->sync($sync);
    }
}
