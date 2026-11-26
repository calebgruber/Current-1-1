<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Assets\Models\Asset;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Jobs\Models\ProductionJob;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Combobox search endpoints shared by the jobs, reservations and warehouse pages. */
class LookupController extends Controller
{
    private const VIEW_KEYS = [
        'jobs.jobs.view', 'jobs.jobs.create', 'jobs.jobs.edit', 'jobs.items.create',
        'reservations.reservations.view', 'reservations.reservations.create', 'reservations.reservations.edit',
        'warehouse.picking.view', 'warehouse.prep.view', 'warehouse.staging.view',
        'warehouse.checkout.view', 'warehouse.returns.view', 'warehouse.damage.view', 'warehouse.display.view',
    ];

    public function __invoke(Request $request, string $type): JsonResponse
    {
        Gate::any(self::VIEW_KEYS) || abort(403);
        $q = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($q, '%_\\').'%';
        $limit = 20;

        $results = match ($type) {
            'products' => Product::query()->where('is_active', true)
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like)))
                ->orderBy('name')->limit($limit)->get()
                ->map(fn (Product $p) => ['value' => $p->id, 'label' => $p->name, 'description' => trim(($p->sku ?? '').' · '.$p->tracking->value, ' ·')]),
            'contacts' => Contact::query()
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orderBy('name')->limit($limit)->get()
                ->map(fn (Contact $c) => ['value' => $c->id, 'label' => $c->name, 'description' => $c->email]),
            'locations' => Location::query()->where('is_active', true)
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('code', 'like', $like)))
                ->orderBy('name')->limit($limit)->get()
                ->map(fn (Location $l) => ['value' => $l->id, 'label' => $l->name, 'description' => $l->path()]),
            'users' => User::query()
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orderBy('name')->limit($limit)->get(['id', 'name', 'email'])
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name, 'description' => $u->email]),
            'jobs' => ProductionJob::query()
                ->when($request->query('warehouse'), fn ($w) => $w->whereIn('stage', array_map(fn (JobStage $s) => $s->value, array_filter(JobStage::cases(), fn (JobStage $s) => $s->isWarehouseActive()))))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', $like)->orWhere('name', 'like', $like)))
                ->orderByDesc('starts_at')->limit($limit)->get()
                ->map(fn (ProductionJob $j) => ['value' => $j->id, 'label' => $j->number.' — '.$j->name, 'description' => $j->stage->label().($j->starts_at ? ' · '.$j->starts_at->toFormattedDateString() : '')]),
            'assets' => Asset::query()->with('product:id,name')
                ->when($request->integer('product_id'), fn ($w, $pid) => $w->where('product_id', $pid))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('asset_number', 'like', $like)->orWhere('serial_number', 'like', $like)->orWhere('barcode', 'like', $like)))
                ->orderBy('asset_number')->limit($limit)->get()
                ->map(fn (Asset $a) => ['value' => $a->id, 'label' => $a->asset_number, 'description' => trim(($a->product?->name ?? '').' · '.$a->status->label(), ' ·')]),
            default => abort(404),
        };

        return response()->json($results->values());
    }
}
