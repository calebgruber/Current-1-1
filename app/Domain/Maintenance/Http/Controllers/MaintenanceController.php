<?php

namespace App\Domain\Maintenance\Http\Controllers;

use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Http\Requests\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Maintenance\Services\MaintenanceService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('maintenance.records.view');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'string', 'in:'.implode(',', array_column(MaintenanceStatus::cases(), 'value'))]]);
        $records = MaintenanceRecord::query()->with(['product:id,name', 'asset:id,asset_number', 'assignee:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('Maintenance/Index', ['records' => $records, 'filters' => $filters, 'statuses' => $this->statuses()]);
    }

    public function create(): Response
    {
        Gate::authorize('maintenance.records.create');

        return Inertia::render('Maintenance/Form', $this->formProps(null));
    }

    public function store(MaintenanceRequest $request, MaintenanceService $service): RedirectResponse
    {
        $record = $service->save(null, $request->validated(), $request->user());

        return redirect()->route('maintenance.show', $record)->with('success', 'Maintenance record created.');
    }

    public function show(MaintenanceRecord $record): Response
    {
        Gate::authorize('maintenance.records.view');
        $record->load(['product:id,name', 'asset:id,asset_number', 'job:id,number,name', 'assignee:id,name', 'reporter:id,name']);

        return Inertia::render('Maintenance/Show', ['record' => $record]);
    }

    public function edit(MaintenanceRecord $record): Response
    {
        Gate::authorize('maintenance.records.edit');

        return Inertia::render('Maintenance/Form', $this->formProps($record));
    }

    public function update(MaintenanceRequest $request, MaintenanceRecord $record, MaintenanceService $service): RedirectResponse
    {
        $service->save($record, $request->validated(), $request->user());

        return redirect()->route('maintenance.show', $record)->with('success', 'Maintenance record updated.');
    }

    private function statuses(): array
    {
        return array_map(fn (MaintenanceStatus $status) => ['value' => $status->value, 'label' => $status->label()], MaintenanceStatus::cases());
    }

    private function formProps(?MaintenanceRecord $record): array
    {
        return [
            'record' => $record,
            'products' => Product::withTrashed()->where(fn ($q) => $q->whereNull('deleted_at')->when($record, fn ($q) => $q->orWhere('id', $record->product_id)))->orderBy('name')->get(['id', 'name', 'tracking', 'deleted_at']),
            'assets' => Asset::withTrashed()->where(fn ($q) => $q->whereNull('deleted_at')->when($record?->asset_id, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('asset_number')->get(['id', 'product_id', 'asset_number', 'deleted_at']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'jobs' => ProductionJob::withTrashed()->where(fn ($q) => $q->whereNull('deleted_at')->when($record?->job_id, fn ($q, $id) => $q->orWhere('id', $id)))->orderByDesc('id')->get(['id', 'number', 'name', 'deleted_at']),
            'statuses' => $this->statuses(),
            'types' => MaintenanceRequest::TYPES,
            'priorities' => MaintenanceRequest::PRIORITIES,
        ];
    }
}
