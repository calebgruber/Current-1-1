<?php

namespace App\Domain\Warehouse\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\ConditionReport;
use App\Domain\Assets\Services\AssetService;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Jobs\Models\JobItemAsset;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DamageController extends Controller
{
    /** Report damage on a serialized asset or a quantity of bulk stock. */
    public function store(Request $request, AssetService $assets): RedirectResponse|JsonResponse
    {
        Gate::authorize('warehouse.damage.edit');
        $data = $request->validate([
            'asset_id' => ['nullable', 'required_without:product_id', 'integer', 'exists:assets,id'],
            'product_id' => ['nullable', 'required_without:asset_id', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'job_id' => ['nullable', 'integer', 'exists:production_jobs,id'],
            'condition' => ['required', Rule::in(['fair', 'poor', 'unusable'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'notes' => ['required', 'string', 'min:3', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'notes.required' => 'Describe what is damaged so the maintenance team can fix it.',
        ]);

        $user = $request->user();
        $job = isset($data['job_id']) ? ProductionJob::query()->find($data['job_id']) : null;
        $asset = isset($data['asset_id']) ? Asset::query()->with('product')->find($data['asset_id']) : null;
        $product = $asset?->product ?? Product::query()->find($data['product_id']);

        if ($asset && $asset->status === AssetStatus::Retired) {
            throw ValidationException::withMessages(['asset_id' => "{$asset->asset_number} is retired."]);
        }
        if (! $asset && $product?->isSerialized()) {
            throw ValidationException::withMessages(['product_id' => 'This product is serialized — scan or choose the individual asset.']);
        }

        $stored = [];
        foreach ($request->file('photos', []) as $photo) {
            $folder = $asset ? "condition-reports/assets/{$asset->id}" : "condition-reports/products/{$product->id}";
            $stored[] = $photo->storeAs($folder, Str::uuid().'.'.strtolower($photo->guessExtension() ?: 'jpg'), 'local');
        }

        $record = DB::transaction(function () use ($data, $user, $job, $asset, $product, $stored, $assets) {
            $report = null;
            $title = $asset ? "Damage: {$asset->asset_number} ({$product->name})" : 'Damage: '.($data['quantity'] ?? 1)." × {$product->name}";

            if ($asset) {
                $report = ConditionReport::query()->create([
                    'asset_id' => $asset->id,
                    'condition' => $data['condition'],
                    'notes' => $data['notes'],
                    'photos' => $stored,
                    'reported_by' => $user->id,
                ]);
                $asset->forceFill(['condition' => $data['condition'] === 'unusable' ? 'poor' : $data['condition']])->save();
                if ($asset->status !== AssetStatus::Damaged) {
                    $assets->changeStatus($asset, AssetStatus::Damaged, $job, $user->id, 'Damage reported: '.Str::limit($data['notes'], 200));
                }
                if ($job) {
                    JobItemAsset::query()->where('asset_id', $asset->id)
                        ->whereIn('job_item_id', $job->items()->select('id'))
                        ->update(['status' => 'damaged']);
                }
            } else {
                $qty = (int) ($data['quantity'] ?? 1);
                $level = StockLevel::query()->where('product_id', $product->id)
                    ->whereRaw('quantity >= quantity_maintenance + quantity_damaged + quantity_lost + ?', [$qty])
                    ->orderByDesc('quantity')->first();
                if (! $level) {
                    throw ValidationException::withMessages(['quantity' => "There are not {$qty} serviceable {$product->name} in stock to mark damaged."]);
                }
                $level->increment('quantity_damaged', $qty);
            }

            $description = $data['notes'];
            if ($report) {
                $description .= "\n\nCondition report #{$report->id}".($stored ? ' with '.count($stored).' photo(s)' : '').'.';
            }

            $record = MaintenanceRecord::query()->create([
                'product_id' => $product->id,
                'asset_id' => $asset?->id,
                'quantity' => $asset ? 1 : (int) ($data['quantity'] ?? 1),
                'type' => 'damage',
                'status' => 'open',
                'priority' => $data['priority'],
                'title' => Str::limit($title, 250),
                'description' => $description,
                'job_id' => $job?->id,
                'reported_by' => $user->id,
            ]);

            AuditLog::record('warehouse.damage_reported', $asset ?? $product, [], ['maintenance_record_id' => $record->id, 'condition' => $data['condition']], $title, $user->id);

            return $record;
        });

        $message = "Damage logged — maintenance ticket {$record->reference} opened.";
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'reference' => $record->reference, 'maintenance_record_id' => $record->id]);
        }

        return back()->with('success', $message);
    }

    public function photo(ConditionReport $report, int $index): StreamedResponse
    {
        Gate::any(['warehouse.damage.view', 'warehouse.damage.edit']) || abort(403);
        $path = ($report->photos ?? [])[$index] ?? null;
        abort_unless(is_string($path) && str_starts_with($path, 'condition-reports/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /** Marks an asset that did not come back from a job as missing (asset → lost). */
    public function missing(Request $request, ProductionJob $job, AssetService $assets): RedirectResponse|JsonResponse
    {
        Gate::authorize('warehouse.returns.edit');
        $data = $request->validate([
            'asset_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment = JobItemAsset::query()->where('asset_id', $data['asset_id'])
            ->whereIn('job_item_id', $job->items()->select('id'))
            ->where('status', 'checked_out')->first();
        if (! $assignment) {
            throw ValidationException::withMessages(['asset_id' => 'That asset is not outstanding on this job.']);
        }

        DB::transaction(function () use ($assignment, $job, $request, $data, $assets) {
            $assignment->update(['status' => 'missing']);
            if ($asset = Asset::query()->find($assignment->asset_id)) {
                $assets->changeStatus($asset, AssetStatus::Lost, $job, $request->user()->id, 'Missing after return of '.$job->number.(($data['notes'] ?? null) ? ': '.$data['notes'] : ''));
            }
        });

        $message = 'Marked missing.';

        return $request->wantsJson() ? response()->json(['message' => $message]) : back()->with('success', $message);
    }
}
