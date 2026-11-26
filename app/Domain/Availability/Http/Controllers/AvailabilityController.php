<?php

namespace App\Domain\Availability\Http\Controllers;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\AvailabilityExplorer;
use App\Domain\Inventory\Services\HierarchyOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** The Availability Explorer: "how many can I use, and if not, why not?" */
class AvailabilityController extends Controller
{
    public const BUCKETS = ['total', 'reserved', 'checked_out', 'tentative', 'maintenance', 'damaged', 'lost', 'retired', 'available'];

    public function __construct(private readonly AvailabilityExplorer $explorer) {}

    public function index(Request $request, HierarchyOptions $hierarchy): View
    {
        Gate::authorize('inventory.availability.view');
        $data = $this->validated($request, [
            'product' => ['nullable', 'integer'],
            'bucket' => ['nullable', 'in:'.implode(',', self::BUCKETS)],
        ]);
        [$from, $to] = $this->explorer->range($data['from'] ?? null, $data['to'] ?? null);
        $locationId = isset($data['location']) ? (int) $data['location'] : null;

        $product = ! empty($data['product']) ? Product::query()->find($data['product']) : null;

        return view('availability.index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'location' => $locationId,
                'product' => $product?->id,
                'bucket' => $data['bucket'] ?? null,
            ],
            'initial' => $product ? $this->explorer->forProduct($product, $from, $to, $locationId) : null,
            'suggestions' => $product ? [] : $this->explorer->search('', $from, $to, 8),
            'locations' => $hierarchy->locations(),
            'maxDays' => AvailabilityExplorer::MAX_DAYS,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        Gate::authorize('inventory.availability.view');
        $data = $this->validated($request, ['q' => ['nullable', 'string', 'max:120']]);
        [$from, $to] = $this->explorer->range($data['from'] ?? null, $data['to'] ?? null);

        return response()->json(['data' => $this->explorer->search(trim($data['q'] ?? ''), $from, $to)]);
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('inventory.availability.view');
        $data = $this->validated($request);
        [$from, $to] = $this->explorer->range($data['from'] ?? null, $data['to'] ?? null);

        return response()->json($this->explorer->forProduct($product, $from, $to, isset($data['location']) ? (int) $data['location'] : null));
    }

    private function validated(Request $request, array $extra = []): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'location' => ['nullable', 'integer', 'exists:locations,id'],
            ...$extra,
        ]);
    }
}
