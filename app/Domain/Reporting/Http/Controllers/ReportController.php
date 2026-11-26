<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Authentication\Models\Role;
use App\Domain\Reporting\Models\Report;
use App\Domain\Reporting\Models\ReportSchedule;
use App\Domain\Reporting\Services\ReportDefinition;
use App\Domain\Reporting\Services\ReportExporter;
use App\Domain\Reporting\Services\ReportRunner;
use App\Domain\Reporting\Services\ReportSourceRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public const PREVIEW_ROWS = 500;

    public const SHOW_ROWS = 2000;

    public function __construct(
        private readonly ReportRunner $runner,
        private readonly ReportSourceRegistry $registry,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reporting.reports.view');
        $user = $request->user();
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'source' => ['nullable', 'string', 'max:32']]);
        $sources = collect($this->registry->all())->map(fn ($s) => $s['label']);

        $reports = Report::query()
            ->with('creator:id,name')->withCount('schedules')
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->when($filters['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->orderBy('name')->get()
            ->filter(fn (Report $r) => $r->isVisibleTo($user))
            ->map(fn (Report $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'source' => $r->source,
                'source_label' => $sources[$r->source] ?? $r->source,
                'can_run' => $this->canSource($user, $r->source),
                'mode' => $r->definition['view']['mode'] ?? (($r->definition['chart']['type'] ?? 'none') !== 'none' ? 'chart' : 'table'),
                'is_shared' => (bool) $r->is_shared,
                'show_on_dashboard' => (bool) $r->show_on_dashboard,
                'schedules_count' => $r->schedules_count,
                'owner' => $r->creator?->name,
                'is_mine' => $r->created_by === $user->id,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ])->values();

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => $filters,
            'sources' => collect($this->registry->all())
                ->filter(fn ($s) => $user->can($s['permission']))
                ->map(fn ($s, $k) => ['value' => $k, 'label' => $s['label']])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('reporting.reports.create');
        $source = $request->validate(['source' => ['nullable', 'string', 'max:32']])['source'] ?? null;
        $catalogue = $this->catalogue($request->user());
        $source = collect($catalogue)->firstWhere('key', $source)['key'] ?? ($catalogue[0]['key'] ?? null);

        return $this->builder($request, [
            'id' => null, 'name' => '', 'description' => '', 'source' => $source,
            'definition' => ReportDefinition::normalize([]), 'is_shared' => false, 'role_ids' => [], 'show_on_dashboard' => false,
        ], $catalogue);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('reporting.reports.create');
        $data = $this->validated($request);
        $report = Report::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('reports.show', $report)->with('success', 'Report saved.');
    }

    public function show(Request $request, Report $report): Response
    {
        $this->authorizeView($request, $report);
        $user = $request->user();
        $definition = ReportDefinition::normalize($report->definition ?? []);
        $result = null;
        $error = null;
        try {
            $result = $this->runner->run($report->source, ReportDefinition::forRunner($definition), min($definition['limit'], self::SHOW_ROWS));
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Reports/Show', [
            'report' => $this->reportArray($report) + [
                'owner' => $report->creator?->name,
                'source_label' => $this->registry->get($report->source)['label'] ?? $report->source,
            ],
            'result' => $result,
            'error' => $error,
            'schedules' => $user->can('reporting.schedules.view')
                ? $report->schedules()->orderBy('id')->get()->map(fn (ReportSchedule $s) => ReportScheduleController::scheduleArray($s))->values()
                : [],
            'can' => [
                'edit' => $this->canManage($user, $report, 'edit'),
                'delete' => $this->canManage($user, $report, 'delete'),
                'export' => $user->can('reporting.reports.export'),
                'schedules' => [
                    'view' => $user->can('reporting.schedules.view'),
                    'create' => $user->can('reporting.schedules.create'),
                    'edit' => $user->can('reporting.schedules.edit'),
                    'delete' => $user->can('reporting.schedules.delete'),
                ],
            ],
        ]);
    }

    public function edit(Request $request, Report $report): Response
    {
        $this->authorizeManage($request, $report, 'edit');

        return $this->builder($request, $this->reportArray($report), $this->catalogue($request->user()));
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeManage($request, $report, 'edit');
        $report->update($this->validated($request));

        return redirect()->route('reports.show', $report)->with('success', 'Report updated.');
    }

    public function destroy(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeManage($request, $report, 'delete');
        $report->delete();

        return redirect()->route('reports.index')->with('success', 'Report deleted.');
    }

    public function dashboard(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeManage($request, $report, 'edit');
        $data = $request->validate(['show_on_dashboard' => ['required', 'boolean']]);
        $report->update($data);

        return back()->with('success', $data['show_on_dashboard'] ? 'Added to the dashboard.' : 'Removed from the dashboard.');
    }

    /** Live preview of a draft definition from the builder. */
    public function run(Request $request): JsonResponse
    {
        Gate::authorize('reporting.reports.view');
        $data = $request->validate([
            'source' => ['required', 'string', 'max:32'],
            'definition' => ['present', 'array'],
        ]);
        $this->authorizeSource($request->user(), $data['source']);
        $definition = ReportDefinition::normalize($data['definition']);
        $errors = [...ReportDefinition::errors($definition), ...$this->runner->validate($data['source'], ReportDefinition::forRunner($definition))];
        if ($errors) {
            return response()->json(['errors' => $errors, 'result' => null], 422);
        }
        $result = $this->runner->run($data['source'], ReportDefinition::forRunner($definition), min($definition['limit'], self::PREVIEW_ROWS));

        return response()->json(['errors' => [], 'result' => $result]);
    }

    public function data(Request $request, Report $report): JsonResponse
    {
        $this->authorizeView($request, $report);
        $definition = ReportDefinition::normalize($report->definition ?? []);

        try {
            $result = $this->runner->run($report->source, ReportDefinition::forRunner($definition), min($definition['limit'], self::SHOW_ROWS));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [$e->getMessage()], 'result' => null], 422);
        }

        return response()->json(['errors' => [], 'result' => $result, 'view' => $definition['view']]);
    }

    public function export(Request $request, Report $report, string $format, ReportExporter $exporter): HttpResponse
    {
        $this->authorizeView($request, $report);
        Gate::authorize('reporting.reports.export');
        abort_unless(isset(ReportExporter::FORMATS[$format]), 404);
        try {
            $file = $exporter->export($report, $format);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', 'This report could not be run: '.$e->getMessage());
        }

        return response($file['content'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function builder(Request $request, array $report, array $catalogue): Response
    {
        $user = $request->user();

        return Inertia::render('Reports/Builder', [
            'report' => $report,
            'sources' => $catalogue,
            'operators' => collect(ReportRunner::OPERATORS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'aggregates' => ReportRunner::AGGREGATES,
            'chartTypes' => ReportDefinition::CHART_TYPES,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->name]),
            'canShare' => $user->can('reporting.reports.admin') || $user->can('reporting.reports.create'),
        ]);
    }

    private function catalogue(User $user): array
    {
        $custom = [];
        foreach ($this->registry->all() as $source) {
            if (! empty($source['entity']) && ! isset($custom[$source['entity']])) {
                $custom[$source['entity']] = $this->runner->customFieldDefinitions($source['entity']);
            }
        }

        return $this->registry->catalogue(fn ($permission) => $user->can($permission), $custom);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source' => ['required', 'string', 'max:32'],
            'definition' => ['required', 'array'],
            'is_shared' => ['boolean'],
            'role_ids' => ['nullable', 'array', 'max:50'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'show_on_dashboard' => ['boolean'],
        ]);
        $this->authorizeSource($request->user(), $data['source']);
        $definition = ReportDefinition::normalize($data['definition']);
        $errors = [...ReportDefinition::errors($definition), ...$this->runner->validate($data['source'], ReportDefinition::forRunner($definition))];
        if ($errors) {
            throw ValidationException::withMessages(['definition' => $errors]);
        }

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'source' => $data['source'],
            'definition' => $definition,
            'is_shared' => (bool) ($data['is_shared'] ?? false),
            'role_ids' => array_values(array_map('intval', $data['role_ids'] ?? [])),
            'show_on_dashboard' => (bool) ($data['show_on_dashboard'] ?? false),
        ];
    }

    private function reportArray(Report $report): array
    {
        return [
            'id' => $report->id,
            'name' => $report->name,
            'description' => $report->description,
            'source' => $report->source,
            'definition' => ReportDefinition::normalize($report->definition ?? []),
            'is_shared' => (bool) $report->is_shared,
            'role_ids' => $report->role_ids ?? [],
            'show_on_dashboard' => (bool) $report->show_on_dashboard,
        ];
    }

    private function canSource(User $user, string $source): bool
    {
        return $this->registry->has($source) && $user->can($this->registry->get($source)['permission']);
    }

    private function authorizeSource(User $user, string $source): void
    {
        if (! $this->registry->has($source)) {
            throw ValidationException::withMessages(['source' => 'Unknown data source.']);
        }
        abort_unless($user->can($this->registry->get($source)['permission']), 403, 'You do not have access to this data source.');
    }

    private function authorizeView(Request $request, Report $report): void
    {
        Gate::authorize('reporting.reports.view');
        abort_unless($report->isVisibleTo($request->user()), 403);
        $this->authorizeSource($request->user(), $report->source);
    }

    private function canManage(User $user, Report $report, string $action): bool
    {
        return $user->can('reporting.reports.'.$action)
            && ($report->created_by === $user->id || $user->can('reporting.reports.admin'));
    }

    private function authorizeManage(Request $request, Report $report, string $action): void
    {
        Gate::authorize('reporting.reports.'.$action);
        abort_unless($this->canManage($request->user(), $report, $action), 403, 'Only the owner or a reporting admin can change this report.');
        $this->authorizeSource($request->user(), $report->source);
    }
}
