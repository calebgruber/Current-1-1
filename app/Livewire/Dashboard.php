<?php

namespace App\Livewire;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Warehouse\Models\ScanEvent;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $canJobs = $user->hasPermission('jobs.jobs.view');
        $canAssets = $user->hasPermission('assets.assets.view');
        $canMaintenance = $user->hasPermission('maintenance.records.view');
        $canWarehouse = $user->hasPermission('warehouse.picking.view') || $user->hasPermission('warehouse.checkout.view');

        $jobRow = fn (ProductionJob $job) => [
            'id' => $job->id,
            'number' => $job->number,
            'name' => $job->name,
            'stage' => $job->stage->value,
            'stage_label' => $job->stage->label(),
            'starts_at' => $job->starts_at,
            'ends_at' => $job->ends_at,
            'prep_starts_at' => $job->prep_starts_at,
            'client' => $job->client?->name,
            'url' => Route::has('jobs.show') ? route('jobs.show', $job) : null,
        ];

        return view('livewire.dashboard', [
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
                ? collect(AssetStatus::cases())->mapWithKeys(fn ($status) => [$status->value => 0])
                    ->merge(Asset::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'))
                : null,
            'maintenanceOpen' => $canMaintenance ? MaintenanceRecord::query()->active()->count() : null,
            'recentScans' => $canWarehouse
                ? ScanEvent::query()->with('user:id,name')->latest('id')->limit(8)->get()
                    ->map(fn (ScanEvent $event) => [
                        'id' => $event->id,
                        'mode' => $event->mode,
                        'code' => $event->code,
                        'result' => $event->result,
                        'message' => $event->message,
                        'user' => $event->user?->name,
                        'at' => $event->created_at,
                    ])
                : null,
        ]);
    }
}
