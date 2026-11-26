<?php

namespace App\Domain\Maintenance\Http\Controllers;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Http\Requests\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Maintenance\Services\MaintenanceService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('maintenance.records.view');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'string', 'in:'.implode(',', array_column(MaintenanceStatus::cases(), 'value'))]]);
        $records = MaintenanceRecord::query()->with(['product:id,name', 'asset:id,asset_number', 'assignee:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();

        return view('maintenance.index', ['records' => $records, 'filters' => $filters, 'statuses' => $this->statuses()]);
    }

    public function create(): View
    {
        Gate::authorize('maintenance.records.create');

        return view('maintenance.form', $this->formProps(null));
    }

    public function lookupAsset(Request $request): JsonResponse
    {
        Gate::authorize('maintenance.records.create');
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:128'],
        ]);
        $code = trim($data['code']);
        $product = Product::query()->find($data['product_id']);
        if ($code === '' || ! $product || ! $product->isSerialized()) {
            return response()->json(['message' => 'Select a serialized product and enter a valid code.'], 422);
        }

        // Verify exact case-sensitive matches in PHP so database collations cannot change scan results.
        $matches = [];
        foreach ($product->assets()->where(fn ($q) => $q->where('barcode', $code)->orWhere('asset_number', $code)->orWhere('serial_number', $code))
            ->cursor() as $asset) {
            if (in_array($code, [$asset->barcode, $asset->asset_number, $asset->serial_number], true)) {
                $matches[] = $asset;
                if (count($matches) === 2) {
                    return response()->json(['message' => 'This code matches multiple assets for this product. Use a unique barcode or asset number.'], 409);
                }
            }
        }
        $asset = $matches[0] ?? null;
        if (! $asset) {
            return response()->json(['message' => 'No asset found for this product and code.'], 404);
        }
        if (! in_array($asset->status, [AssetStatus::Available, AssetStatus::Damaged, AssetStatus::InMaintenance], true)) {
            return response()->json(['message' => 'This asset cannot enter maintenance in its current status.'], 422);
        }

        return response()->json(['asset' => ['id' => $asset->id, 'asset_number' => $asset->asset_number]]);
    }

    public function store(MaintenanceRequest $request, MaintenanceService $service): RedirectResponse
    {
        $record = $service->save(null, $request->validated(), $request->user());

        return redirect()->route('maintenance.show', $record)->with('success', 'Maintenance record created.');
    }

    public function show(MaintenanceRecord $record): View
    {
        Gate::authorize('maintenance.records.view');
        $record->load(['product:id,name', 'asset:id,asset_number', 'job:id,number,name', 'assignee:id,name', 'reporter:id,name']);

        return view('maintenance.show', ['record' => $record]);
    }

    public function edit(MaintenanceRecord $record): View
    {
        Gate::authorize('maintenance.records.edit');

        return view('maintenance.form', $this->formProps($record));
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
