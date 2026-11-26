<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Assets\Models\Asset;
use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Services\HierarchyOptions;
use App\Domain\Inventory\Services\HistoryPresenter;
use App\Domain\Inventory\Services\LabelRenderer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public const TYPES = ['warehouse', 'building', 'room', 'shelf', 'bin', 'venue', 'vehicle'];

    public function __construct(
        private readonly HierarchyOptions $hierarchy,
        private readonly CustomFieldService $customFields,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('inventory.locations.view');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(self::TYPES)],
        ]);

        $locations = Location::query()->withCount(['children', 'assets'])->get()->keyBy('id');
        $stock = StockLevel::query()->whereNotNull('location_id')->selectRaw('location_id, sum(quantity) as units, count(distinct product_id) as products')
            ->groupBy('location_id')->get()->keyBy('location_id');
        $assetProducts = Asset::query()->whereNotNull('location_id')->selectRaw('location_id, count(distinct product_id) as products')
            ->groupBy('location_id')->pluck('products', 'location_id');
        $values = $this->customFields->valuesForMany('location', $locations->keys()->all());

        $rows = array_map(function (array $option) use ($locations, $stock, $assetProducts, $values) {
            $l = $locations[$option['value']];

            return [
                'id' => $l->id,
                'parent_id' => $l->parent_id,
                'name' => $l->name,
                'path' => $option['label'],
                'depth' => $option['depth'],
                'code' => $l->code,
                'type' => $l->type,
                'barcode' => $l->barcode,
                'is_active' => $l->is_active,
                'children_count' => $l->children_count,
                'assets_count' => $l->assets_count,
                'bulk_units' => (int) ($stock[$l->id]->units ?? 0),
                'products_count' => (int) ($stock[$l->id]->products ?? 0) + (int) ($assetProducts[$l->id] ?? 0),
                'custom_fields' => $values[$l->id] ?? (object) [],
            ];
        }, $this->hierarchy->locations());

        $search = mb_strtolower(trim($filters['search'] ?? ''));
        if ($search !== '' || ! empty($filters['type'])) {
            $rows = array_values(array_filter($rows, fn ($r) => ($search === '' || str_contains(mb_strtolower($r['path'].' '.$r['code'].' '.$r['barcode']), $search))
                && (empty($filters['type']) || $r['type'] === $filters['type'])));
        }

        return view('inventory.locations.index', [
            'locations' => $rows,
            'filters' => ['search' => $filters['search'] ?? '', 'type' => $filters['type'] ?? null],
            'types' => $this->typeOptions(),
            'customFieldColumns' => $this->customFields->listColumns('location'),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('inventory.locations.view');
        $q = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:120']])['q'] ?? ''));
        $options = collect($this->hierarchy->locations(true))
            ->when($q !== '', fn ($c) => $c->filter(fn ($o) => str_contains(mb_strtolower($o['label']), mb_strtolower($q))))
            ->take(20)
            ->map(fn ($o) => ['value' => $o['value'], 'label' => $o['label'], 'description' => ucfirst($o['type'])])
            ->values();

        return response()->json($options);
    }

    public function create(Request $request): View
    {
        Gate::authorize('inventory.locations.create');
        $parent = $request->integer('parent') ?: null;

        return view('inventory.locations.form', $this->formProps(null) + ['defaultParentId' => $parent]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.locations.create');
        $data = $this->validated($request);
        $clean = $this->customFields->validate('location', $request->input('custom_fields', []) ?? [], partial: false);

        $location = DB::transaction(function () use ($data, $clean) {
            $location = Location::query()->create($data);
            $this->customFields->save($location, $clean);

            return $location;
        });

        return redirect()->route('inventory.locations.show', $location)->with('success', 'Location created.');
    }

    public function show(Location $location, HistoryPresenter $history, LabelRenderer $labels): View
    {
        Gate::authorize('inventory.locations.view');
        $location->load('parent');

        $breadcrumbs = [];
        $node = $location->parent;
        $guard = 0;
        while ($node && $guard++ < 16) {
            array_unshift($breadcrumbs, ['id' => $node->id, 'name' => $node->name]);
            $node = $node->parent;
        }

        $children = $location->children()->withCount(['children', 'assets'])->orderBy('name')->get()
            ->map(fn (Location $c) => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'type' => $c->type, 'is_active' => $c->is_active, 'children_count' => $c->children_count, 'assets_count' => $c->assets_count]);

        $assets = $location->assets()->with('product:id,name')->orderBy('asset_number')->limit(200)->get()
            ->map(fn (Asset $a) => [
                'id' => $a->id,
                'asset_number' => $a->asset_number,
                'serial_number' => $a->serial_number,
                'product' => $a->product?->only(['id', 'name']),
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
            ]);

        $stock = $location->stockLevels()->with('product:id,name,sku')->where('quantity', '>', 0)->get()
            ->filter(fn (StockLevel $s) => $s->product)
            ->map(fn (StockLevel $s) => [
                'product' => $s->product->only(['id', 'name', 'sku']),
                'quantity' => $s->quantity,
                'quantity_maintenance' => $s->quantity_maintenance,
                'quantity_damaged' => $s->quantity_damaged,
                'quantity_lost' => $s->quantity_lost,
            ])->values();

        $code = $location->barcode ?: ($location->code ?: 'L'.str_pad((string) $location->id, 6, '0', STR_PAD_LEFT));

        return view('inventory.locations.show', [
            'location' => $this->present($location) + ['path' => $location->path()],
            'breadcrumbs' => $breadcrumbs,
            'children' => $children,
            'assets' => $assets,
            'assetsTotal' => $location->assets()->count(),
            'stock' => $stock,
            'label' => $labels->label($code, $location->name, ucfirst($location->type)),
            'history' => $history->for($location),
            'customFieldSchema' => $this->customFields->schema('location'),
            'customFields' => (object) $location->customFields(),
        ]);
    }

    public function edit(Location $location): View
    {
        Gate::authorize('inventory.locations.edit');

        return view('inventory.locations.form', $this->formProps($location) + ['defaultParentId' => null]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        Gate::authorize('inventory.locations.edit');
        $data = $this->validated($request, $location);
        if ($this->hierarchy->wouldCycle(Location::class, $location->id, $data['parent_id'] ?? null)) {
            return back()->withErrors(['parent_id' => 'A location cannot be placed inside itself or one of its sub-locations.']);
        }
        $clean = $this->customFields->validate('location', $request->input('custom_fields', []) ?? [], partial: false);

        DB::transaction(function () use ($location, $data, $clean) {
            $location->update($data);
            $this->customFields->save($location, $clean);
        });

        return redirect()->route('inventory.locations.show', $location)->with('success', 'Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        Gate::authorize('inventory.locations.delete');

        if ($location->children()->exists()) {
            return back()->with('error', 'Move or delete the sub-locations first.');
        }
        if ($location->assets()->exists() || $location->stockLevels()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'This location still holds equipment. Move it elsewhere first, or mark the location inactive.');
        }
        $location->stockLevels()->delete();
        $location->delete();

        return redirect()->route('inventory.locations.index')->with('success', 'Location deleted.');
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        foreach (['code', 'barcode'] as $key) {
            if (is_string($request->input($key))) {
                $request->merge([$key => trim($request->input($key)) ?: null]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:locations,id', ...($location ? [Rule::notIn([$location->id])] : [])],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('locations', 'code')->ignore($location?->id)],
            'type' => ['required', Rule::in(self::TYPES)],
            'barcode' => ['nullable', 'string', 'max:128', 'regex:/^[\x20-\x7E]+$/', Rule::unique('locations', 'barcode')->ignore($location?->id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'custom_fields' => ['nullable', 'array'],
        ], ['parent_id.not_in' => 'A location cannot be its own parent.']);
        unset($data['custom_fields']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    private function present(Location $location): array
    {
        return [
            'id' => $location->id,
            'parent_id' => $location->parent_id,
            'name' => $location->name,
            'code' => $location->code,
            'type' => $location->type,
            'barcode' => $location->barcode,
            'address' => $location->address,
            'notes' => $location->notes,
            'is_active' => (bool) $location->is_active,
        ];
    }

    private function formProps(?Location $location): array
    {
        $exclude = $location ? $this->hierarchy->descendantIds(Location::class, $location->id) : [];

        return [
            'location' => $location ? $this->present($location) : null,
            'parentOptions' => array_values(array_filter($this->hierarchy->locations(), fn ($o) => ! in_array($o['value'], $exclude, true))),
            'types' => $this->typeOptions(),
            'customFieldSchema' => $this->customFields->schema('location'),
            'customFields' => (object) ($location?->customFields() ?? []),
        ];
    }

    private function typeOptions(): array
    {
        return array_map(fn ($t) => ['value' => $t, 'label' => ucfirst($t)], self::TYPES);
    }
}
