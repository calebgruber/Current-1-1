<?php

namespace App\Domain\Warehouse\Http\Controllers;

use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Warehouse\Enums\ScanMode;
use App\Domain\Warehouse\Services\JobProgress;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    /** Station slug => [scan mode, permission resource]. */
    public const STATIONS = [
        'picking' => ScanMode::Pick,
        'prep' => ScanMode::Prep,
        'staging' => ScanMode::Stage,
        'checkout' => ScanMode::CheckOut,
        'returns' => ScanMode::Return,
        'damage' => ScanMode::Damage,
    ];

    public const VIEW_KEYS = [
        'warehouse.picking.view', 'warehouse.prep.view', 'warehouse.staging.view',
        'warehouse.checkout.view', 'warehouse.returns.view', 'warehouse.damage.view',
    ];

    public function __construct(private readonly JobProgress $progress) {}

    public static function permissionFor(ScanMode $mode, string $action = 'view'): ?string
    {
        $slug = array_search($mode, self::STATIONS, true);

        return $slug ? "warehouse.{$slug}.{$action}" : null;
    }

    public function index(Request $request): View
    {
        Gate::any(self::VIEW_KEYS) || abort(403);

        $queueStages = [JobStage::Reserved, JobStage::Prep, JobStage::Ready, JobStage::CheckedOut, JobStage::Returned];
        $jobs = ProductionJob::query()->with(['client:id,name', 'venue:id,name'])
            ->whereIn('stage', array_map(fn ($s) => $s->value, $queueStages))
            ->withSum('items as required_total', 'required_quantity')
            ->withSum('items as spare_total', 'spare_quantity')
            ->withSum('items as picked_total', 'picked_quantity')
            ->withSum('items as checked_out_total', 'checked_out_quantity')
            ->withSum('items as returned_total', 'returned_quantity')
            ->orderByRaw('COALESCE(prep_starts_at, starts_at) IS NULL')
            ->orderByRaw('COALESCE(prep_starts_at, starts_at)')
            ->limit(200)->get();

        $queues = [];
        foreach ($queueStages as $stage) {
            $queues[$stage->value] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'jobs' => $jobs->filter(fn ($j) => $j->stage === $stage)->map(function (ProductionJob $j) {
                    $pull = (int) $j->required_total + (int) $j->spare_total;
                    $out = (int) $j->checked_out_total;

                    return [
                        'id' => $j->id,
                        'number' => $j->number,
                        'name' => $j->name,
                        'client' => $j->client?->name,
                        'venue' => $j->venue?->name,
                        'prep_starts_at' => $j->prep_starts_at?->toIso8601String(),
                        'starts_at' => $j->starts_at?->toIso8601String(),
                        'returns_at' => $j->returns_at?->toIso8601String(),
                        'pull' => $pull,
                        'picked' => min($pull, (int) $j->picked_total),
                        'checked_out' => $out,
                        'returned' => (int) $j->returned_total,
                        'outstanding' => max(0, $out - (int) $j->returned_total),
                    ];
                })->values()->all(),
            ];
        }

        $user = $request->user();

        return view('warehouse.index', [
            'queues' => array_values($queues),
            'recentScans' => $this->progress->recentScans(null, 12),
            'stations' => collect(self::STATIONS)->map(fn (ScanMode $m, string $slug) => [
                'slug' => $slug,
                'mode' => $m->value,
                'label' => $m->label(),
                'allowed' => $user->can("warehouse.{$slug}.view"),
            ])->values(),
            'canDisplay' => $user->can('warehouse.display.view'),
        ]);
    }

    public function station(Request $request): View
    {
        $station = $request->route('station');
        $mode = self::STATIONS[$station] ?? abort(404);
        Gate::authorize("warehouse.{$station}.view");
        $jobId = $request->route('job');
        $job = $jobId ? ProductionJob::query()->findOrFail($jobId) : null;
        $user = $request->user();
        $job?->load(['client:id,name', 'venue:id,name']);

        $progress = $job ? $this->progress->lines($job) : null;

        return view('warehouse.station', [
            'station' => $station,
            'mode' => $mode->value,
            'modeLabel' => $mode->label(),
            'job' => $job ? $this->progress->header($job) : null,
            'lines' => $progress['lines'] ?? [],
            'totals' => $progress['totals'] ?? null,
            'missing' => $job && in_array($mode, [ScanMode::Return, ScanMode::Damage], true) ? $this->progress->missing($job) : [],
            'recentScans' => $this->progress->recentScans($job, 15, $mode),
            'stations' => collect(self::STATIONS)->map(fn (ScanMode $m, string $slug) => [
                'slug' => $slug,
                'mode' => $m->value,
                'label' => $m->label(),
                'allowed' => $user->can("warehouse.{$slug}.view"),
            ])->values(),
            'can' => [
                'scan' => $user->can("warehouse.{$station}.edit"),
                'damage' => $user->can('warehouse.damage.edit'),
                'mark_missing' => $user->can('warehouse.returns.edit'),
            ],
        ]);
    }

    public function display(Request $request, ?ProductionJob $job = null): View
    {
        Gate::authorize('warehouse.display.view');
        $user = $request->user();
        $job?->load(['client:id,name', 'venue:id,name']);
        $progress = $job ? $this->progress->lines($job) : null;

        $mode = $request->query('mode');
        $mode = is_string($mode) ? ScanMode::tryFrom($mode) : null;

        return view('warehouse.display', [
            'job' => $job ? $this->progress->header($job) : null,
            'lines' => $progress['lines'] ?? [],
            'totals' => $progress['totals'] ?? null,
            'initialMode' => ($mode && ! in_array($mode, [ScanMode::Lookup, ScanMode::Damage], true)) ? $mode->value : ScanMode::Pick->value,
            'modes' => collect(self::STATIONS)->except('damage')->map(fn (ScanMode $m, string $slug) => [
                'value' => $m->value,
                'label' => $m->label(),
                'allowed' => $user->can("warehouse.{$slug}.edit"),
            ])->values(),
            'jobs' => $this->activeJobs(),
        ]);
    }

    public function progress(ProductionJob $job): JsonResponse
    {
        Gate::any([...self::VIEW_KEYS, 'warehouse.display.view']) || abort(403);
        $job->loadMissing(['client:id,name', 'venue:id,name']);

        return response()->json([
            'job' => $this->progress->header($job),
            ...$this->progress->lines($job),
            'recent' => $this->progress->recentScans($job, 8),
        ]);
    }

    private function activeJobs(): array
    {
        return ProductionJob::query()
            ->whereIn('stage', array_map(fn ($s) => $s->value, array_filter(JobStage::cases(), fn (JobStage $s) => $s->isWarehouseActive())))
            ->orderByRaw('COALESCE(prep_starts_at, starts_at) IS NULL')
            ->orderByRaw('COALESCE(prep_starts_at, starts_at)')
            ->limit(30)->get(['id', 'number', 'name', 'stage', 'starts_at'])
            ->map(fn (ProductionJob $j) => [
                'id' => $j->id,
                'number' => $j->number,
                'name' => $j->name,
                'stage' => $j->stage->value,
                'stage_label' => $j->stage->label(),
            ])->all();
    }
}
