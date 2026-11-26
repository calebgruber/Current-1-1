<?php

namespace App\Http\Controllers;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Warehouse\Models\ScanEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $canJobs = $user->hasPermission('jobs.jobs.view');
        $canAssets = $user->hasPermission('assets.assets.view');
        $canMaintenance = $user->hasPermission('maintenance.records.view');
        $canWarehouse = $user->hasPermission('warehouse.picking.view') || $user->hasPermission('warehouse.checkout.view');

        $jobUrl = fn (ProductionJob $job) => Route::has('jobs.show') ? route('jobs.show', $job) : null;
        $jobRow = fn (ProductionJob $job) => [
            'id' => $job->id,
            'number' => $job->number,
            'name' => $job->name,
            'stage' => $job->stage->value,
            'stage_label' => $job->stage->label(),
            'starts_at' => $job->starts_at?->toIso8601String(),
            'ends_at' => $job->ends_at?->toIso8601String(),
            'prep_starts_at' => $job->prep_starts_at?->toIso8601String(),
            'client' => $job->client?->name,
            'url' => $jobUrl($job),
        ];

        return Inertia::render('Dashboard/Index', [
            'stageCounts' => $canJobs
                ? ProductionJob::query()->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage')
                : null,
            'upcoming' => $canJobs
                ? ProductionJob::query()->with('client')
                    ->whereNotIn('stage', [JobStage::Closed->value, JobStage::Archived->value, JobStage::Returned->value])
                    ->where(fn ($q) => $q->whereBetween('starts_at', [now()->startOfDay(), now()->addDays(21)])
                        ->orWhereBetween('prep_starts_at', [now()->startOfDay(), now()->addDays(21)]))
                    ->orderBy('starts_at')->limit(8)->get()->map($jobRow)
                : null,
            'warehouseQueue' => $canWarehouse
                ? ProductionJob::query()->with('client')
                    ->whereIn('stage', [JobStage::Reserved->value, JobStage::Prep->value, JobStage::Ready->value, JobStage::CheckedOut->value])
                    ->orderBy('starts_at')->limit(8)->get()->map($jobRow)
                : null,
            'overdue' => $canJobs
                ? ProductionJob::query()->with('client')
                    ->where('stage', JobStage::CheckedOut->value)
                    ->where(fn ($q) => $q->where('returns_at', '<', now())->orWhere(fn ($q) => $q->whereNull('returns_at')->where('ends_at', '<', now())))
                    ->orderBy('ends_at')->limit(8)->get()->map($jobRow)
                : null,
            'assetCounts' => $canAssets
                ? collect(AssetStatus::cases())->mapWithKeys(fn ($s) => [$s->value => 0])
                    ->merge(Asset::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'))
                : null,
            'maintenanceOpen' => $canMaintenance ? MaintenanceRecord::query()->active()->count() : null,
            'recentScans' => $canWarehouse
                ? ScanEvent::query()->with('user:id,name')->latest('id')->limit(8)->get()
                    ->map(fn (ScanEvent $e) => [
                        'id' => $e->id, 'mode' => $e->mode, 'code' => $e->code, 'result' => $e->result,
                        'message' => $e->message, 'user' => $e->user?->name, 'at' => $e->created_at?->toIso8601String(),
                    ])
                : null,
        ]);
    }
}
