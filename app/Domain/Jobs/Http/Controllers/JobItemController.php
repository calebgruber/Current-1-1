<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Http\Requests\JobItemRequest;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Jobs\Models\JobItemAsset;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Jobs\Services\AllocationGuard;
use App\Domain\Jobs\Services\JobService;
use App\Domain\Reservations\DTOs\ConflictReport;
use App\Domain\Reservations\Exceptions\OverbookingException;
use App\Domain\Rules\DTOs\RuleFinding;
use App\Domain\Rules\Services\JobValidationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobItemController extends Controller
{
    public function __construct(
        private readonly JobService $jobs,
        private readonly AllocationGuard $guard,
    ) {}

    public function store(JobItemRequest $request, ProductionJob $job): RedirectResponse|JsonResponse
    {
        $this->assertEditable($job);
        $product = Product::query()->findOrFail($request->validated('product_id'));
        if (! $product->is_active) {
            throw ValidationException::withMessages(['product_id' => 'This product is inactive and cannot be allocated.']);
        }

        $item = $this->guarded($request, $job, fn () => $this->jobs->addItem(
            $job,
            $product,
            (int) $request->validated('required_quantity'),
            (int) ($request->validated('spare_quantity') ?? 0),
            array_filter([
                'section' => $request->validated('section'),
                'notes' => $request->validated('notes'),
                'unit_price' => $request->validated('unit_price'),
            ], fn ($v) => $v !== null),
            $request->boolean('with_accessories', true),
        ));

        return $this->respond($request, $job, "Added {$product->name}.", ['item_id' => $item->id]);
    }

    public function update(JobItemRequest $request, ProductionJob $job, JobItem $item): RedirectResponse|JsonResponse
    {
        $this->assertOwned($job, $item);
        $data = collect($request->validated())->only(['required_quantity', 'spare_quantity', 'section', 'notes', 'unit_price', 'sort_order'])->all();
        if (array_key_exists('spare_quantity', $data)) {
            $data['spare_quantity'] = (int) $data['spare_quantity'];
        }
        $quantityChange = array_key_exists('required_quantity', $data) || array_key_exists('spare_quantity', $data);
        if ($quantityChange) {
            $this->assertEditable($job);
        }

        $this->guarded($request, $job, fn () => $this->jobs->updateItem($item, $data), $quantityChange);

        return $this->respond($request, $job, 'Allocation updated.');
    }

    public function destroy(Request $request, ProductionJob $job, JobItem $item): RedirectResponse|JsonResponse
    {
        Gate::authorize('jobs.items.delete');
        $this->assertOwned($job, $item);
        $this->assertEditable($job);
        if ($item->picked_quantity > 0 || $item->checked_out_quantity > 0) {
            throw ValidationException::withMessages(['item' => 'This line has already been picked or checked out. Return the equipment first.']);
        }

        $this->jobs->removeItem($item);

        return $this->respond($request, $job, 'Allocation removed.');
    }

    /** Live availability preview for the allocation editor. */
    public function check(Request $request, ProductionJob $job): JsonResponse
    {
        Gate::authorize('jobs.jobs.view');
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'item_id' => ['nullable', 'integer'],
        ]);
        $product = Product::query()->findOrFail($data['product_id']);
        $replacing = isset($data['item_id']) ? $job->items()->whereKey($data['item_id'])->first() : null;
        $report = $this->guard->check($job, $product, (int) $data['quantity'], $replacing);

        return response()->json([
            'conflict' => $report?->toArray(),
            'blocks' => $this->guard->blocks($job, $report),
            'has_dates' => $report !== null,
        ]);
    }

    /** Candidate assets (serialized stock) for a line. */
    public function assetOptions(Request $request, ProductionJob $job, JobItem $item): JsonResponse
    {
        Gate::authorize('jobs.jobs.view');
        $this->assertOwned($job, $item);
        $search = (string) $request->query('q', '');

        $assignedElsewhere = JobItemAsset::query()
            ->whereNotIn('status', ['returned', 'missing'])
            ->where('job_item_id', '!=', $item->id)
            ->whereHas('item', fn ($q) => $q->whereHas('job', fn ($j) => $j->where('stage', '!=', 'archived')->where('stage', '!=', 'closed')))
            ->pluck('asset_id');

        $assets = Asset::query()
            ->where('product_id', $item->product_id)
            ->whereNotIn('status', [AssetStatus::Retired->value, AssetStatus::Lost->value])
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('asset_number', 'like', $like)->orWhere('serial_number', 'like', $like)->orWhere('barcode', 'like', $like));
            })
            ->orderBy('asset_number')->limit(200)->get();

        $current = $item->assignedAssets()->get()->keyBy('asset_id');

        return response()->json($assets->map(fn (Asset $a) => [
            'id' => $a->id,
            'asset_number' => $a->asset_number,
            'serial_number' => $a->serial_number,
            'status' => $a->status->value,
            'condition' => $a->condition,
            'assigned' => $current->has($a->id),
            'role' => $current->get($a->id)?->role,
            'busy' => $assignedElsewhere->contains($a->id),
            'unavailable' => in_array($a->status, [AssetStatus::InMaintenance, AssetStatus::Damaged], true),
        ])->values());
    }

    public function assignAssets(Request $request, ProductionJob $job, JobItem $item): RedirectResponse|JsonResponse
    {
        Gate::authorize('jobs.items.edit');
        $this->assertOwned($job, $item);
        $data = $request->validate([
            'assets' => ['present', 'array', 'max:1000'],
            'assets.*.asset_id' => ['required', 'integer', 'distinct', Rule::exists('assets', 'id')->where('product_id', $item->product_id)],
            'assets.*.role' => ['required', Rule::in(['active', 'spare'])],
        ]);
        if (count($data['assets']) > $item->pull_quantity) {
            throw ValidationException::withMessages(['assets' => "At most {$item->pull_quantity} assets can be assigned to this line (required + spare)."]);
        }
        $spares = collect($data['assets'])->where('role', 'spare')->count();
        if ($spares > $item->spare_quantity) {
            throw ValidationException::withMessages(['assets' => "Only {$item->spare_quantity} spare assets are planned for this line."]);
        }

        DB::transaction(function () use ($item, $data) {
            $wanted = collect($data['assets'])->keyBy('asset_id');
            $existing = $item->assignedAssets()->get()->keyBy('asset_id');
            foreach ($existing as $assetId => $row) {
                if (! $wanted->has($assetId) && $row->status === 'allocated') {
                    $row->delete();
                }
            }
            foreach ($wanted as $assetId => $w) {
                if ($existing->has($assetId)) {
                    $existing[$assetId]->update(['role' => $w['role']]);
                } else {
                    $item->assignedAssets()->create(['asset_id' => $assetId, 'role' => $w['role'], 'status' => 'allocated']);
                }
            }
        });

        return $this->respond($request, $job, 'Assets assigned.');
    }

    public function conflicts(ProductionJob $job): JsonResponse
    {
        Gate::authorize('jobs.jobs.view');

        return response()->json([
            'conflicts' => array_map(fn (ConflictReport $r) => $r->toArray(), $this->jobs->conflicts($job)),
            'availability' => (object) $this->guard->lineAvailability($job),
        ]);
    }

    public function validation(ProductionJob $job, JobValidationService $validation): JsonResponse
    {
        Gate::authorize('jobs.jobs.view');

        return response()->json([
            'findings' => array_map(fn (RuleFinding $f) => $f->toArray(), $validation->validate($job)),
        ]);
    }

    /**
     * Runs an allocation change; if the job holds stock firmly and the change
     * creates a shortage, it is rolled back unless an authorised override was given.
     */
    private function guarded(Request $request, ProductionJob $job, callable $change, bool $check = true): mixed
    {
        $override = $request->boolean('override');
        if ($override) {
            Gate::authorize('reservations.overbooking.approve');
        }

        try {
            return DB::transaction(function () use ($job, $change, $check, $override, $request) {
                $result = $change();
                if (! $check) {
                    return $result;
                }
                $job->refresh();
                $shortages = array_values(array_filter($this->jobs->conflicts($job), fn (ConflictReport $r) => $r->hasConflict()));
                if ($shortages && $this->guard->blocks($job, $shortages[0])) {
                    if (! $override) {
                        throw new OverbookingException($shortages);
                    }
                    AuditLog::record('job.overbooking_override', $job, [], ['allocation' => true], 'Allocation overbooking override: '.$request->input('override_reason'));
                }

                return $result;
            });
        } catch (OverbookingException $e) {
            if ($request->wantsJson()) {
                abort(response()->json(['message' => 'Overbooked: '.$e->getMessage(), 'conflicts' => $e->toArray()], 422));
            }
            throw ValidationException::withMessages(['overbooking' => 'Overbooked: '.$e->getMessage()]);
        }
    }

    private function respond(Request $request, ProductionJob $job, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message] + $extra);
        }

        return back()->with('success', $message);
    }

    private function assertOwned(ProductionJob $job, JobItem $item): void
    {
        abort_unless($item->job_id === $job->id, 404);
    }

    private function assertEditable(ProductionJob $job): void
    {
        if (! $job->stage->allowsAllocationEdits()) {
            throw ValidationException::withMessages(['item' => "Allocations are locked once a job is {$job->stage->label()}."]);
        }
    }
}
