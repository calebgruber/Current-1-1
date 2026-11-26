<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Inventory\Enums\TrackingType;
use App\Domain\Inventory\Http\Requests\ProductRequest;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Manufacturer;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductAccessory;
use App\Domain\Inventory\Models\ProductImage;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\Tag;
use App\Domain\Inventory\Services\AvailabilityService;
use App\Domain\Inventory\Services\HierarchyOptions;
use App\Domain\Inventory\Services\HistoryPresenter;
use App\Domain\Inventory\Services\LabelRenderer;
use App\Domain\Inventory\Services\ProductService;
use App\Domain\Inventory\Models\Category;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    private const SORTS = ['name', 'sku', 'category', 'tracking', 'replacement_cost', 'rental_price', 'owned', 'created_at'];

    public function __construct(
        private readonly ProductService $products,
        private readonly CustomFieldService $customFields,
        private readonly HierarchyOptions $hierarchy,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('inventory.products.view');
        $filters = $this->filters($request);

        $products = $this->query($filters)
            ->with(['category:id,name,color', 'manufacturer:id,name', 'tags:id,name'])
            ->paginate(25)
            ->withQueryString();

        $values = $this->customFields->valuesForMany('product', $products->getCollection()->pluck('id')->all());

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'model' => $p->model,
                'barcode' => $p->barcode,
                'tracking' => $p->tracking->value,
                'tracking_label' => $p->tracking->label(),
                'category' => $p->category?->only(['id', 'name', 'color']),
                'manufacturer' => $p->manufacturer?->name,
                'replacement_cost' => $p->replacement_cost,
                'rental_price' => $p->rental_price,
                'owned' => (int) $p->owned,
                'is_active' => $p->is_active,
                'image_url' => ProductService::imageUrl($p->image_path),
                'tags' => $p->tags->pluck('name')->all(),
                'custom_fields' => $values[$p->id] ?? (object) [],
            ]),
            'filters' => $filters,
            'categories' => $this->hierarchy->categories(),
            'locations' => $this->hierarchy->locations(),
            'tags' => Tag::query()->orderBy('name')->pluck('name')->all(),
            'trackingOptions' => $this->trackingOptions(),
            'customFieldColumns' => $this->customFields->listColumns('product'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('inventory.products.export');
        $query = $this->query($this->filters($request))->with(['category:id,name', 'manufacturer:id,name']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'SKU', 'Barcode', 'Category', 'Manufacturer', 'Model', 'Tracking', 'Owned', 'Replacement cost', 'Rental price', 'Active']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, array_map([self::class, 'csvSafe'], [
                        $p->id, $p->name, $p->sku, $p->barcode, $p->category?->name, $p->manufacturer?->name, $p->model,
                        $p->tracking->value, (int) $p->owned, $p->replacement_cost, $p->rental_price, $p->is_active ? 'yes' : 'no',
                    ]));
                }
            });
            fclose($out);
        }, 'products-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Prevents CSV formula injection when opened in spreadsheet software. */
    public static function csvSafe(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** Combobox endpoint: [{value,label,description}] */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('inventory.products.view');
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'tracking' => ['nullable', Rule::enum(TrackingType::class)]]);
        $q = trim($data['q'] ?? '');

        $rows = Product::query()
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $this->applySearch($query, $q))
            ->when($data['tracking'] ?? null, fn ($query, $t) => $query->where('tracking', $t))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'sku', 'model']);

        return response()->json($rows->map(fn (Product $p) => [
            'value' => $p->id,
            'label' => $p->name,
            'description' => trim(implode(' · ', array_filter([$p->sku, $p->model]))),
        ]));
    }

    public function create(): Response
    {
        Gate::authorize('inventory.products.create');

        return Inertia::render('Inventory/Products/Form', $this->formProps(null));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $this->prepare($request);
        $clean = $this->customFields->validate('product', $request->input('custom_fields', []) ?? [], partial: false);

        $product = $this->products->save(new Product(['is_active' => true]), $data);
        $this->customFields->save($product, $clean);

        return redirect()->route('inventory.products.show', $product)->with('success', "Product “{$product->name}” created.");
    }

    public function show(Product $product, AvailabilityService $availability, HistoryPresenter $history): Response
    {
        Gate::authorize('inventory.products.view');
        $product->load(['category:id,name,color,parent_id', 'manufacturer:id,name,website', 'defaultLocation:id,name,parent_id', 'tags:id,name', 'images']);

        $from = CarbonImmutable::now();
        $to = $from->addDays(30);
        $breakdown = $availability->forProduct($product, $from, $to)->toArray();

        $assets = $product->assets()->with('location:id,name')->orderBy('asset_number')->limit(200)->get()
            ->map(fn (Asset $a) => [
                'id' => $a->id,
                'asset_number' => $a->asset_number,
                'serial_number' => $a->serial_number,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'condition' => $a->condition,
                'location' => $a->location?->name,
            ]);

        $stock = $product->stockLevels()->with('location')->get()->map(fn (StockLevel $s) => [
            'id' => $s->id,
            'location_id' => $s->location_id,
            'location' => $s->location?->path() ?? 'Unassigned',
            'quantity' => $s->quantity,
            'quantity_maintenance' => $s->quantity_maintenance,
            'quantity_damaged' => $s->quantity_damaged,
            'quantity_lost' => $s->quantity_lost,
        ]);

        $accessories = $product->accessories()->with('accessory:id,name,sku,tracking')->get()->map(fn (ProductAccessory $a) => [
            'id' => $a->id,
            'product_id' => $a->accessory_product_id,
            'name' => $a->accessory?->name ?? 'Deleted product',
            'sku' => $a->accessory?->sku,
            'quantity' => $a->quantity,
            'type' => $a->type,
            'auto_add' => $a->auto_add,
            'notes' => $a->notes,
        ]);

        $accessoryOf = ProductAccessory::query()->where('accessory_product_id', $product->id)->with('product:id,name,sku')->get()
            ->filter(fn ($a) => $a->product)
            ->map(fn (ProductAccessory $a) => ['id' => $a->product_id, 'name' => $a->product->name, 'sku' => $a->product->sku, 'quantity' => $a->quantity, 'type' => $a->type])
            ->values();

        $reservations = Reservation::query()
            ->where('product_id', $product->id)
            ->where('status', '!=', ReservationStatus::Released->value)
            ->where('ends_at', '>=', now())
            ->with('job:id,number,name,stage')
            ->orderBy('starts_at')->limit(15)->get()
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'label' => $r->label(),
                'quantity' => $r->totalQuantity(),
                'status' => $r->status->value,
                'starts_at' => $r->starts_at?->toIso8601String(),
                'ends_at' => $r->ends_at?->toIso8601String(),
                'url' => $r->job_id ? '/jobs/'.$r->job_id : '/reservations/'.$r->id,
            ]);

        $maintenance = MaintenanceRecord::query()->where('product_id', $product->id)->with('asset:id,asset_number')
            ->latest('id')->limit(10)->get()
            ->map(fn (MaintenanceRecord $m) => [
                'id' => $m->id,
                'reference' => $m->reference,
                'title' => $m->title,
                'type' => $m->type,
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'asset' => $m->asset?->asset_number,
                'quantity' => $m->quantity,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Inventory/Products/Show', [
            'product' => $this->present($product) + [
                'category' => $product->category?->only(['id', 'name', 'color']),
                'manufacturer' => $product->manufacturer?->only(['id', 'name', 'website']),
                'default_location' => $product->defaultLocation ? ['id' => $product->defaultLocation->id, 'name' => $product->defaultLocation->path()] : null,
                'tags' => $product->tags->pluck('name')->all(),
                'images' => $product->images->map(fn (ProductImage $i) => ['id' => $i->id, 'url' => ProductService::imageUrl($i->path), 'caption' => $i->caption])->all(),
            ],
            'availability' => $breakdown,
            'window' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'assets' => $assets,
            'assetsTotal' => $product->assets()->count(),
            'stock' => $stock,
            'accessories' => $accessories,
            'accessoryOf' => $accessoryOf,
            'reservations' => $reservations,
            'maintenance' => $maintenance,
            'history' => $history->for($product),
            'locations' => $this->hierarchy->locations(true),
            'customFieldSchema' => $this->customFields->schema('product'),
            'customFields' => (object) $product->customFields(),
        ]);
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('inventory.products.edit');
        $product->load(['tags:id,name', 'accessories.accessory:id,name,sku', 'manufacturer:id,name']);

        return Inertia::render('Inventory/Products/Form', $this->formProps($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $this->prepare($request);
        unset($data['stock']); // stock is adjusted from the product page
        $clean = $this->customFields->validate('product', $request->input('custom_fields', []) ?? [], partial: false);

        $this->products->save($product, $data);
        $this->customFields->save($product, $clean);

        return redirect()->route('inventory.products.show', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('inventory.products.delete');

        $activeReservations = Reservation::query()->where('product_id', $product->id)
            ->where('status', '!=', ReservationStatus::Released->value)->where('ends_at', '>=', now())->exists();
        if ($activeReservations) {
            return back()->with('error', 'This product has upcoming reservations. Release them before deleting it, or mark the product inactive.');
        }

        $product->delete();

        return redirect()->route('inventory.products.index')->with('success', "Product “{$product->name}” deleted.");
    }

    public function updateStock(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('inventory.stock.edit');
        abort_if($product->isSerialized(), 422, 'Serialized products are counted by their assets.');

        $row = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:10000000'],
            'quantity_maintenance' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'quantity_damaged' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'quantity_lost' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $unserviceable = (int) ($row['quantity_maintenance'] ?? 0) + (int) ($row['quantity_damaged'] ?? 0) + (int) ($row['quantity_lost'] ?? 0);
        if ($unserviceable > (int) $row['quantity']) {
            return back()->withErrors(['quantity' => 'Maintenance, damaged and lost units cannot exceed the quantity held at this location.']);
        }

        $this->products->setStock($product, $row, $row['reason'] ?? null);

        return back()->with('success', 'Stock updated.');
    }

    public function destroyStock(Product $product, StockLevel $stockLevel): RedirectResponse
    {
        Gate::authorize('inventory.stock.edit');
        abort_unless($stockLevel->product_id === $product->id, 404);
        $this->products->setStock($product, ['location_id' => $stockLevel->location_id, 'quantity' => 0], 'Location removed');
        $stockLevel->delete();

        return back()->with('success', 'Stock location removed.');
    }

    public function storeImage(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('inventory.products.edit');
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);
        $this->products->addImage($product, $request->file('image'), $data['caption'] ?? null);

        return back()->with('success', 'Image uploaded.');
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('inventory.products.edit');
        abort_unless($image->product_id === $product->id, 404);
        $this->products->removeImage($product, $image);

        return back()->with('success', 'Image removed.');
    }

    public function primaryImage(Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('inventory.products.edit');
        abort_unless($image->product_id === $product->id, 404);
        $product->forceFill(['image_path' => $image->path])->save();

        return back()->with('success', 'Primary image updated.');
    }

    /** Printable barcode / QR labels for products (?ids[]=). */
    public function labels(Request $request, LabelRenderer $renderer): Response
    {
        Gate::authorize('inventory.products.view');
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $copies = (int) ($data['copies'] ?? 1);
        $ids = $data['ids'] ?? [];

        $labels = [];
        if ($ids !== []) {
            foreach (Product::query()->whereIn('id', $ids)->orderBy('name')->get() as $p) {
                $code = $p->barcode ?: ($p->sku ?: 'P'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT));
                $label = $renderer->label($code, $p->name, trim(implode(' · ', array_filter([$p->sku, $p->model]))));
                for ($i = 0; $i < $copies; $i++) {
                    $labels[] = $label + ['key' => $p->id.'-'.$i, 'url' => route('inventory.products.show', $p, false)];
                }
            }
        }

        return Inertia::render('Inventory/Labels', [
            'title' => 'Product labels',
            'labels' => $labels,
            'copies' => $copies,
            'kind' => 'product',
            'selected' => Product::query()->whereIn('id', $ids)->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->values(),
            'backUrl' => route('inventory.products.index', [], false),
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer'],
            'location' => ['nullable', 'integer'],
            'tracking' => ['nullable', Rule::enum(TrackingType::class)],
            'tag' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,all'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        return [
            'search' => $data['search'] ?? '',
            'category' => isset($data['category']) ? (int) $data['category'] : null,
            'location' => isset($data['location']) ? (int) $data['location'] : null,
            'tracking' => $data['tracking'] ?? null,
            'tag' => $data['tag'] ?? null,
            'status' => $data['status'] ?? 'active',
            'sort' => $data['sort'] ?? 'name',
            'direction' => $data['direction'] ?? 'asc',
        ];
    }

    private function query(array $filters): Builder
    {
        $query = Product::query()
            ->select('products.*')
            ->selectRaw(
                "(case when products.tracking = ? then (select count(*) from assets where assets.product_id = products.id and assets.deleted_at is null and assets.status != ?) else coalesce((select sum(quantity) from stock_levels where stock_levels.product_id = products.id), 0) end) as owned",
                [TrackingType::Serialized->value, AssetStatus::Retired->value],
            );

        if ($filters['search'] !== '') {
            $this->applySearch($query, $filters['search']);
        }
        if ($filters['category']) {
            $query->whereIn('category_id', $this->hierarchy->descendantIds(Category::class, $filters['category']));
        }
        if ($filters['location']) {
            $locationIds = $this->hierarchy->descendantIds(Location::class, $filters['location']);
            $query->where(fn ($q) => $q
                ->whereIn('products.id', Asset::query()->whereIn('location_id', $locationIds)->select('product_id'))
                ->orWhereIn('products.id', StockLevel::query()->whereIn('location_id', $locationIds)->where('quantity', '>', 0)->select('product_id'))
                ->orWhereIn('default_location_id', $locationIds));
        }
        if ($filters['tracking']) {
            $query->where('tracking', $filters['tracking']);
        }
        if ($filters['tag']) {
            $query->whereHas('tags', fn ($q) => $q->where('name', $filters['tag']));
        }
        if ($filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        $direction = $filters['direction'];
        match ($filters['sort']) {
            'category' => $query->orderBy(Category::query()->select('name')->whereColumn('categories.id', 'products.category_id'), $direction),
            'owned' => $query->orderBy('owned', $direction),
            default => $query->orderBy('products.'.$filters['sort'], $direction),
        };

        return $query->orderBy('products.id');
    }

    private function applySearch(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($q) => $q
            ->where('products.name', 'like', $like)
            ->orWhere('products.sku', 'like', $like)
            ->orWhere('products.barcode', 'like', $like)
            ->orWhere('products.model', 'like', $like)
            ->orWhereHas('manufacturer', fn ($m) => $m->where('name', 'like', $like)));
    }

    /** @return array<string, mixed> */
    private function prepare(ProductRequest $request): array
    {
        $data = $request->validated();
        unset($data['custom_fields']);

        $name = trim((string) ($data['manufacturer_name'] ?? ''));
        if (empty($data['manufacturer_id']) && $name !== '' && Gate::allows('inventory.categories.create')) {
            $data['manufacturer_id'] = Manufacturer::query()->firstOrCreate(['name' => mb_substr($name, 0, 255)])->id;
        }
        unset($data['manufacturer_name']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['tags'] ??= [];
        $data['accessories'] ??= [];

        return $data;
    }

    private function present(Product $product): array
    {
        $meta = collect($product->metadata ?? [])->map(fn ($value, $key) => ['key' => (string) $key, 'value' => is_scalar($value) ? (string) $value : json_encode($value)])->values()->all();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'model' => $product->model,
            'description' => $product->description,
            'tracking' => $product->tracking->value,
            'tracking_label' => $product->tracking->label(),
            'category_id' => $product->category_id,
            'manufacturer_id' => $product->manufacturer_id,
            'default_location_id' => $product->default_location_id,
            'replacement_cost' => $product->replacement_cost,
            'rental_price' => $product->rental_price,
            'weight_kg' => $product->weight_kg,
            'is_active' => $product->is_active,
            'metadata' => $meta,
            'image_url' => ProductService::imageUrl($product->image_path),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    private function formProps(?Product $product): array
    {
        return [
            'product' => $product ? $this->present($product) + [
                'tags' => $product->tags->pluck('name')->all(),
                'manufacturer_name' => $product->manufacturer?->name,
                'accessories' => $product->accessories->map(fn (ProductAccessory $a) => [
                    'accessory_product_id' => $a->accessory_product_id,
                    'name' => $a->accessory?->name ?? 'Deleted product',
                    'quantity' => $a->quantity,
                    'type' => $a->type,
                    'auto_add' => $a->auto_add,
                    'notes' => $a->notes,
                ])->all(),
            ] : null,
            'categories' => $this->hierarchy->categories(),
            'manufacturers' => $this->hierarchy->manufacturers(),
            'locations' => $this->hierarchy->locations(true),
            'tags' => Tag::query()->orderBy('name')->pluck('name')->all(),
            'trackingOptions' => $this->trackingOptions(),
            'accessoryTypes' => array_map(fn ($t) => ['value' => $t, 'label' => ucfirst($t)], ProductRequest::ACCESSORY_TYPES),
            'canCreateManufacturer' => Gate::allows('inventory.categories.create'),
            'customFieldSchema' => $this->customFields->schema('product'),
            'customFields' => (object) ($product?->customFields() ?? []),
        ];
    }

    private function trackingOptions(): array
    {
        return array_map(fn (TrackingType $t) => ['value' => $t->value, 'label' => $t->label()], TrackingType::cases());
    }
}
