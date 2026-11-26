<?php

namespace App\Domain\Assets\Http\Controllers;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Http\Requests\AssetRequest;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetStatusHistory;
use App\Domain\Assets\Models\ConditionReport;
use App\Domain\Assets\Services\AssetRegistrar;
use App\Domain\Assets\Services\AssetService;
use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\HierarchyOptions;
use App\Domain\Inventory\Services\HistoryPresenter;
use App\Domain\Inventory\Services\LabelRenderer;
use App\Domain\Jobs\Models\JobItemAsset;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    private const SORTS = ['asset_number', 'serial_number', 'product', 'status', 'condition', 'location', 'purchased_at', 'created_at'];

    /** Statuses managed by warehouse workflows; manual changes to them need assets.assets.admin. */
    private const WORKFLOW_STATUSES = [AssetStatus::Allocated, AssetStatus::CheckedOut];

    /** Statuses that require approval permission. */
    private const APPROVAL_STATUSES = [AssetStatus::Lost, AssetStatus::Retired];

    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly HierarchyOptions $hierarchy,
        private readonly AssetService $assetService,
        private readonly AssetRegistrar $registrar,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('assets.assets.view');
        $filters = $this->filters($request);

        $assets = $this->query($filters)
            ->with(['product:id,name,sku', 'location:id,name'])
            ->paginate(25)
            ->withQueryString();
        $values = $this->customFields->valuesForMany('asset', $assets->getCollection()->pluck('id')->all());

        $counts = Asset::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Assets/Index', [
            'assets' => $assets->through(fn (Asset $a) => [
                'id' => $a->id,
                'asset_number' => $a->asset_number,
                'serial_number' => $a->serial_number,
                'barcode' => $a->barcode,
                'product' => $a->product?->only(['id', 'name', 'sku']),
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'condition' => $a->condition,
                'location' => $a->location?->only(['id', 'name']),
                'purchased_at' => $a->purchased_at?->toDateString(),
                'custom_fields' => $values[$a->id] ?? (object) [],
            ]),
            'filters' => $filters,
            'statusCounts' => collect(AssetStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'statuses' => AssetStatus::options(),
            'conditions' => $this->conditionOptions(),
            'locations' => $this->hierarchy->locations(),
            'productLabel' => $filters['product'] ? Product::query()->whereKey($filters['product'])->value('name') : null,
            'customFieldColumns' => $this->customFields->listColumns('asset'),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('assets.assets.view');
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'product' => ['nullable', 'integer']]);
        $q = trim($data['q'] ?? '');

        $rows = Asset::query()->with('product:id,name')
            ->when($q !== '', fn ($query) => $this->applySearch($query, $q))
            ->when($data['product'] ?? null, fn ($query, $p) => $query->where('product_id', $p))
            ->orderBy('asset_number')->limit(20)->get();

        return response()->json($rows->map(fn (Asset $a) => [
            'value' => $a->id,
            'label' => $a->asset_number.' — '.($a->product?->name ?? 'Asset'),
            'description' => trim(implode(' · ', array_filter([$a->status->label(), $a->serial_number ? 'S/N '.$a->serial_number : null]))),
        ]));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('assets.assets.create');
        $productId = $request->integer('product') ?: null;
        $product = $productId ? Product::query()->whereKey($productId)->first(['id', 'name', 'sku', 'tracking']) : null;

        return Inertia::render('Assets/Form', $this->formProps(null) + [
            'defaultProduct' => $product && $product->isSerialized() ? ['value' => $product->id, 'label' => $product->name, 'prefix' => $this->registrar->prefixFor($product, null)] : null,
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clean = $this->customFields->validate('asset', $request->input('custom_fields', []) ?? [], partial: false);
        $count = (int) ($data['count'] ?? 1);
        if ($count > 1 && ! empty($data['barcode'])) {
            throw ValidationException::withMessages(['barcode' => 'A barcode can only be set when creating a single asset.']);
        }

        $product = Product::query()->findOrFail($data['product_id']);
        $created = DB::transaction(function () use ($product, $data, $count, $clean, $request) {
            $created = $this->registrar->register($product, $data, $count, $data['prefix'] ?? null, $request->user()->id);
            foreach ($created as $asset) {
                $this->customFields->save($asset, $clean);
            }

            return $created;
        });

        if ($count === 1) {
            return redirect()->route('assets.show', $created[0])->with('success', "Asset {$created[0]->asset_number} created.");
        }

        return redirect()->route('assets.index', ['product' => $product->id])->with('success', "{$count} assets created ({$created[0]->asset_number} – ".end($created)->asset_number.').');
    }

    public function show(Asset $asset, HistoryPresenter $history, LabelRenderer $labels): Response
    {
        Gate::authorize('assets.assets.view');
        $asset->load(['product:id,name,sku,model,category_id', 'product.category:id,name', 'location']);

        $timeline = AssetStatusHistory::query()->where('asset_id', $asset->id)
            ->with(['user:id,name', 'location:id,name'])
            ->latest('id')->limit(100)->get()
            ->map(fn (AssetStatusHistory $h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'location' => $h->location?->name,
                'location_id' => $h->location_id,
                'user' => $h->user?->name,
                'notes' => $h->notes,
                'context' => $this->contextLink($h->context_type, $h->context_id),
                'created_at' => $h->created_at ? \Illuminate\Support\Carbon::parse($h->created_at)->toIso8601String() : null,
            ]);

        $reports = $asset->conditionReports()->with('reporter:id,name')->limit(50)->get()
            ->map(fn (ConditionReport $r) => [
                'id' => $r->id,
                'condition' => $r->condition,
                'notes' => $r->notes,
                'reporter' => $r->reporter?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        $maintenance = $asset->maintenanceRecords()->with('assignee:id,name')->limit(50)->get()
            ->map(fn (MaintenanceRecord $m) => [
                'id' => $m->id,
                'reference' => $m->reference,
                'title' => $m->title,
                'type' => $m->type,
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'priority' => $m->priority,
                'assignee' => $m->assignee?->name,
                'cost' => $m->cost,
                'created_at' => $m->created_at?->toIso8601String(),
                'completed_at' => $m->completed_at?->toIso8601String(),
            ]);

        $jobs = JobItemAsset::query()->where('asset_id', $asset->id)->with('item.job:id,number,name,stage,starts_at,ends_at')
            ->latest('id')->limit(50)->get()
            ->filter(fn (JobItemAsset $j) => $j->item?->job)
            ->map(fn (JobItemAsset $j) => [
                'id' => $j->id,
                'job_id' => $j->item->job->id,
                'number' => $j->item->job->number,
                'name' => $j->item->job->name,
                'stage' => $j->item->job->stage?->value,
                'stage_label' => $j->item->job->stage?->label(),
                'starts_at' => $j->item->job->starts_at?->toIso8601String(),
                'ends_at' => $j->item->job->ends_at?->toIso8601String(),
                'role' => $j->role,
                'status' => $j->status,
                'checked_out_at' => $j->checked_out_at?->toIso8601String(),
                'returned_at' => $j->returned_at?->toIso8601String(),
            ])->values();

        $code = $asset->barcode ?: $asset->asset_number;

        return Inertia::render('Assets/Show', [
            'asset' => $this->present($asset) + [
                'product' => $asset->product ? ['id' => $asset->product->id, 'name' => $asset->product->name, 'sku' => $asset->product->sku, 'model' => $asset->product->model, 'category' => $asset->product->category?->name] : null,
                'location' => $asset->location ? ['id' => $asset->location->id, 'name' => $asset->location->name, 'path' => $asset->location->path()] : null,
            ],
            'timeline' => $timeline,
            'conditionReports' => $reports,
            'maintenance' => $maintenance,
            'jobs' => $jobs,
            'history' => $history->for($asset),
            'label' => $labels->label($code, $asset->product?->name ?? $asset->asset_number, trim($asset->asset_number.($asset->serial_number ? ' · S/N '.$asset->serial_number : ''))),
            'statuses' => $this->allowedStatuses(request()),
            'conditions' => $this->conditionOptions(),
            'locations' => $this->hierarchy->locations(true),
            'customFieldSchema' => $this->customFields->schema('asset'),
            'customFields' => (object) $asset->customFields(),
        ]);
    }

    public function edit(Asset $asset): Response
    {
        Gate::authorize('assets.assets.edit');
        $asset->load('product:id,name');

        return Inertia::render('Assets/Form', $this->formProps($asset) + ['defaultProduct' => null]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();
        $clean = $this->customFields->validate('asset', $request->input('custom_fields', []) ?? [], partial: false);
        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;

        DB::transaction(function () use ($asset, $data, $clean, $locationId, $request) {
            $asset->fill(collect($data)->only([
                'product_id', 'asset_number', 'serial_number', 'barcode', 'condition', 'purchased_at', 'purchase_cost', 'warranty_expires_at', 'notes',
            ])->all())->save();
            $this->registrar->move($asset, $locationId, $request->user()->id);
            $this->customFields->save($asset, $clean);
        });

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function updateStatus(Request $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('assets.status.edit');
        $data = $request->validate([
            'status' => ['required', Rule::enum(AssetStatus::class)],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $status = AssetStatus::from($data['status']);

        if (in_array($status, self::APPROVAL_STATUSES, true) || in_array($asset->status, self::APPROVAL_STATUSES, true)) {
            Gate::authorize('assets.status.approve');
        }
        if (in_array($status, self::WORKFLOW_STATUSES, true) && $status !== $asset->status) {
            Gate::authorize('assets.assets.admin');
        }

        $this->assetService->changeStatus($asset, $status, null, $request->user()->id, $data['notes'] ?? null, isset($data['location_id']) ? (int) $data['location_id'] : null);

        return back()->with('success', 'Status changed to '.$status->label().'.');
    }

    public function storeConditionReport(Request $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('assets.assets.edit');
        $data = $request->validate([
            'condition' => ['required', Rule::in(AssetRequest::CONDITIONS)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            $asset->conditionReports()->create($data + ['reported_by' => $request->user()->id]);
            $asset->update(['condition' => $data['condition']]);
        });

        return back()->with('success', 'Condition report added.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('assets.assets.delete');
        if (in_array($asset->status, self::WORKFLOW_STATUSES, true)) {
            return back()->with('error', 'This asset is allocated to or checked out on a job and cannot be deleted.');
        }
        $asset->delete();

        return redirect()->route('assets.index')->with('success', "Asset {$asset->asset_number} deleted.");
    }

    /** Printable labels for assets (?ids[]= or ?product=). */
    public function labels(Request $request, LabelRenderer $renderer): Response
    {
        Gate::authorize('assets.labels.view');
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'product' => ['nullable', 'integer'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $copies = (int) ($data['copies'] ?? 1);
        $query = Asset::query()->with('product:id,name')->orderBy('asset_number');
        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        } elseif (! empty($data['product'])) {
            $query->where('product_id', $data['product']);
        } else {
            $query->whereRaw('1 = 0');
        }

        $labels = [];
        foreach ($query->limit(500)->get() as $a) {
            $label = $renderer->label($a->barcode ?: $a->asset_number, $a->product?->name ?? 'Asset', trim($a->asset_number.($a->serial_number ? ' · S/N '.$a->serial_number : '')));
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = $label + ['key' => $a->id.'-'.$i, 'url' => route('assets.show', $a, false)];
            }
        }

        return Inertia::render('Inventory/Labels', [
            'title' => 'Asset labels',
            'labels' => $labels,
            'copies' => $copies,
            'kind' => 'asset',
            'selected' => [],
            'backUrl' => ! empty($data['product']) ? route('inventory.products.show', $data['product'], false) : route('assets.index', [], false),
        ]);
    }

    private function contextLink(?string $type, ?int $id): ?array
    {
        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'job' => ['label' => 'Job', 'url' => '/jobs/'.$id],
            'maintenance' => ['label' => 'Maintenance record', 'url' => '/maintenance/'.$id],
            'reservation' => ['label' => 'Reservation', 'url' => '/reservations/'.$id],
            default => ['label' => ucfirst(str_replace('_', ' ', class_basename($type))), 'url' => null],
        };
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'condition' => ['nullable', Rule::in(AssetRequest::CONDITIONS)],
            'product' => ['nullable', 'integer'],
            'location' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        return [
            'search' => $data['search'] ?? '',
            'status' => $data['status'] ?? null,
            'condition' => $data['condition'] ?? null,
            'product' => isset($data['product']) ? (int) $data['product'] : null,
            'location' => isset($data['location']) ? (int) $data['location'] : null,
            'sort' => $data['sort'] ?? 'asset_number',
            'direction' => $data['direction'] ?? 'asc',
        ];
    }

    private function query(array $filters): Builder
    {
        $query = Asset::query()->select('assets.*');
        if ($filters['search'] !== '') {
            $this->applySearch($query, $filters['search']);
        }
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }
        if ($filters['condition']) {
            $query->where('condition', $filters['condition']);
        }
        if ($filters['product']) {
            $query->where('product_id', $filters['product']);
        }
        if ($filters['location']) {
            $query->whereIn('location_id', $this->hierarchy->descendantIds(Location::class, $filters['location']));
        }

        $direction = $filters['direction'];
        match ($filters['sort']) {
            'product' => $query->orderBy(Product::query()->select('name')->whereColumn('products.id', 'assets.product_id'), $direction),
            'location' => $query->orderBy(Location::query()->select('name')->whereColumn('locations.id', 'assets.location_id'), $direction),
            default => $query->orderBy('assets.'.$filters['sort'], $direction),
        };

        return $query->orderBy('assets.id');
    }

    private function applySearch(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($q) => $q
            ->where('assets.asset_number', 'like', $like)
            ->orWhere('assets.serial_number', 'like', $like)
            ->orWhere('assets.barcode', 'like', $like)
            ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $like)));
    }

    private function present(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'product_id' => $asset->product_id,
            'asset_number' => $asset->asset_number,
            'serial_number' => $asset->serial_number,
            'barcode' => $asset->barcode,
            'status' => $asset->status->value,
            'status_label' => $asset->status->label(),
            'condition' => $asset->condition,
            'location_id' => $asset->location_id,
            'purchased_at' => $asset->purchased_at?->toDateString(),
            'purchase_cost' => $asset->purchase_cost,
            'warranty_expires_at' => $asset->warranty_expires_at?->toDateString(),
            'notes' => $asset->notes,
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }

    private function formProps(?Asset $asset): array
    {
        return [
            'asset' => $asset ? $this->present($asset) + ['product_name' => $asset->product?->name] : null,
            'conditions' => $this->conditionOptions(),
            'locations' => $this->hierarchy->locations(true),
            'customFieldSchema' => $this->customFields->schema('asset'),
            'customFields' => (object) ($asset?->customFields() ?? []),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function allowedStatuses(Request $request): array
    {
        $user = $request->user();

        return array_values(array_map(
            fn (AssetStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            array_filter(AssetStatus::cases(), function (AssetStatus $s) use ($user) {
                if (in_array($s, self::APPROVAL_STATUSES, true) && ! $user->can('assets.status.approve')) {
                    return false;
                }

                return ! in_array($s, self::WORKFLOW_STATUSES, true) || $user->can('assets.assets.admin');
            }),
        ));
    }

    private function conditionOptions(): array
    {
        return array_map(fn ($c) => ['value' => $c, 'label' => ucfirst($c)], AssetRequest::CONDITIONS);
    }
}
