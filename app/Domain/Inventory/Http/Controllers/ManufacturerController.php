<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Inventory\Models\Manufacturer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Manufacturers are reference data managed with the category permissions. */
class ManufacturerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('inventory.categories.view');
        $search = trim((string) ($request->validate(['search' => ['nullable', 'string', 'max:120']])['search'] ?? ''));

        $manufacturers = Manufacturer::query()
            ->withCount('products')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Manufacturer $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'website' => $m->website,
                'products_count' => $m->products_count,
            ]);

        return view('inventory.manufacturers.index', [
            'manufacturers' => $manufacturers,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.categories.create');
        Manufacturer::query()->create($this->validated($request));

        return back()->with('success', 'Manufacturer created.');
    }

    public function update(Request $request, Manufacturer $manufacturer): RedirectResponse
    {
        Gate::authorize('inventory.categories.edit');
        $manufacturer->update($this->validated($request, $manufacturer->id));

        return back()->with('success', 'Manufacturer updated.');
    }

    public function destroy(Manufacturer $manufacturer): RedirectResponse
    {
        Gate::authorize('inventory.categories.delete');
        if ($manufacturer->products()->exists()) {
            return back()->with('error', 'This manufacturer is used by products and cannot be deleted.');
        }
        $manufacturer->delete();

        return back()->with('success', 'Manufacturer deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('manufacturers', 'name')->ignore($ignoreId)],
            'website' => ['nullable', 'url:http,https', 'max:255'],
        ]);
    }
}
