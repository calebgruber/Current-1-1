<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetService;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Warehouse\DTOs\ScanResult;
use App\Domain\Warehouse\Enums\ScanMode;
use App\Domain\Warehouse\Services\ScanProcessor;
use App\Domain\Warehouse\Services\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function staleScanner(Asset $asset, string $status): WarehouseService
    {
        return new class(new ScanProcessor, new AssetService, $asset, $status) extends WarehouseService
        {
            public function __construct(ScanProcessor $processor, AssetService $assets, private Asset $asset, private string $status)
            {
                parent::__construct($processor, $assets);
            }

            public function resolve(string $code): ?array
            {
                return ['kind' => 'asset', 'asset_id' => $this->asset->id, 'asset_number' => $this->asset->asset_number,
                    'product_id' => $this->asset->product_id, 'product' => 'Old product', 'status' => $this->status];
            }
        };
    }

    public function test_scan_uses_locked_status_for_checkout_and_return(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'maintenance']);
        $job = ProductionJob::factory()->create();
        $item = $job->items()->create(['product_id' => $product->id, 'required_quantity' => 1, 'checked_out_quantity' => 1]);
        $item->assignedAssets()->create(['asset_id' => $asset->id, 'role' => 'active', 'status' => 'checked_out']);
        $scanner = $this->staleScanner($asset, 'checked_out');

        $checkout = $scanner->scan(ScanMode::CheckOut, $asset->asset_number, $job);
        $this->assertSame(ScanResult::ERROR, $checkout->level);
        $this->assertSame('Do not use', $checkout->title);
        $return = $scanner->scan(ScanMode::Return, $asset->asset_number, $job);
        $this->assertSame(ScanResult::SUCCESS, $return->level);
        $this->assertSame(AssetStatus::InMaintenance, $asset->fresh()->status);
        $this->assertSame(1, $item->fresh()->returned_quantity);
        $this->assertDatabaseMissing('asset_status_history', ['asset_id' => $asset->id, 'to_status' => 'available']);
    }

    public function test_scan_uses_locked_product_and_rejects_retired_damage(): void
    {
        $old = Product::factory()->create();
        $new = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $old->id, 'status' => 'available']);
        $scanner = $this->staleScanner($asset, 'available');
        DB::table('assets')->where('id', $asset->id)->update(['product_id' => $new->id, 'status' => 'retired']);
        $result = $scanner->scan(ScanMode::Damage, $asset->asset_number, null);
        $this->assertSame(ScanResult::ERROR, $result->level);
        $this->assertSame('Retired asset', $result->title);
        $this->assertSame(AssetStatus::Retired, $asset->fresh()->status);
        $this->assertDatabaseCount('maintenance_records', 0);

        $job = ProductionJob::factory()->create();
        $job->items()->create(['product_id' => $old->id, 'required_quantity' => 1]);
        $result = $scanner->scan(ScanMode::Pick, $asset->asset_number, $job);
        $this->assertSame('Not on this job', $result->title);
    }

    public function test_damage_report_cannot_reopen_a_written_off_asset(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'retired']);
        $this->actingAs($this->superAdmin())->post('/warehouse/damage', [
            'asset_id' => $asset->id, 'condition' => 'poor', 'priority' => 'normal', 'notes' => 'Damaged lens',
        ])->assertSessionHasErrors('asset_id');
        $this->assertSame(AssetStatus::Retired, $asset->fresh()->status);
        $this->assertDatabaseCount('maintenance_records', 0);
        $this->assertDatabaseCount('condition_reports', 0);
    }

    public function test_damage_report_creates_record_and_marks_the_locked_asset_damaged(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $this->actingAs($this->superAdmin())->post('/warehouse/damage', [
            'asset_id' => $asset->id, 'condition' => 'poor', 'priority' => 'normal', 'notes' => 'Damaged lens',
        ])->assertSessionHasNoErrors();
        $this->assertSame(AssetStatus::Damaged, $asset->fresh()->status);
        $this->assertDatabaseHas('maintenance_records', ['asset_id' => $asset->id, 'product_id' => $product->id, 'status' => 'open']);
        $this->assertDatabaseHas('condition_reports', ['asset_id' => $asset->id, 'condition' => 'poor']);
    }

    public function test_direct_creation_cannot_open_an_active_record_for_retired_or_reassigned_asset(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'retired']);
        $data = ['product_id' => $product->id, 'asset_id' => $asset->id, 'quantity' => 1, 'type' => 'repair', 'priority' => 'normal', 'status' => 'open', 'title' => 'Repair'];
        foreach (['retired', 'checked_out', 'reassigned'] as $case) {
            if ($case === 'checked_out') {
                $asset->update(['status' => 'checked_out']);
            }
            if ($case === 'reassigned') {
                $asset->update(['status' => 'available', 'product_id' => $other->id]);
            }
            try {
                MaintenanceRecord::query()->create($data);
                $this->fail("Expected {$case} asset to be rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('asset_id', $exception->errors());
            }
        }
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function test_missing_action_does_not_override_retired_status(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'retired']);
        $job = ProductionJob::factory()->create();
        $item = $job->items()->create(['product_id' => $product->id, 'required_quantity' => 1]);
        $item->assignedAssets()->create(['asset_id' => $asset->id, 'role' => 'active', 'status' => 'checked_out']);
        $this->actingAs($this->superAdmin())->post("/warehouse/jobs/{$job->id}/missing", ['asset_id' => $asset->id])->assertSessionHasErrors('asset_id');
        $this->assertSame(AssetStatus::Retired, $asset->fresh()->status);
        $this->assertSame('checked_out', $item->assignedAssets()->first()->status);
    }

    public function test_asset_reassignment_is_blocked_during_active_maintenance(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $record = MaintenanceRecord::query()->create(['product_id' => $product->id, 'asset_id' => $asset->id, 'quantity' => 1, 'type' => 'repair', 'priority' => 'normal', 'status' => 'open', 'title' => 'Repair']);
        $user = $this->superAdmin();
        $input = ['product_id' => $other->id, 'asset_number' => $asset->asset_number, 'condition' => 'good'];
        $this->actingAs($user)->put("/assets/{$asset->id}", $input)->assertSessionHasErrors('product_id');
        $this->assertSame($product->id, $asset->fresh()->product_id);
        $this->put("/assets/{$asset->id}/status", ['status' => 'available'])->assertSessionHasErrors('status');
        $this->put("/assets/{$asset->id}/status", ['status' => 'retired'])->assertSessionHasErrors('status');
        $this->put("/maintenance/{$record->id}", ['product_id' => $product->id, 'asset_id' => $asset->id, 'quantity' => 1, 'type' => 'repair', 'priority' => 'normal', 'status' => 'completed', 'title' => 'Repair'])->assertSessionHasNoErrors();
        $this->put("/assets/{$asset->id}", $input)->assertSessionHasNoErrors();
        $this->assertSame($other->id, $asset->fresh()->product_id);
    }

    public function test_existing_mismatched_asset_can_be_closed_but_not_reopened(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $record = MaintenanceRecord::query()->create(['product_id' => $product->id, 'asset_id' => $asset->id, 'quantity' => 1, 'type' => 'repair', 'priority' => 'normal', 'status' => 'open', 'title' => 'Repair']);
        DB::table('assets')->where('id', $asset->id)->update(['product_id' => $other->id]);
        $this->actingAs($this->superAdmin())->get("/maintenance/{$record->id}/edit")
            ->assertOk()->assertSee($asset->asset_number);
        $data = ['product_id' => $product->id, 'asset_id' => $asset->id, 'quantity' => 1, 'type' => 'repair', 'priority' => 'normal', 'status' => 'completed', 'title' => 'Repair'];
        $this->put("/maintenance/{$record->id}", $data)->assertSessionHasNoErrors();
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->put("/maintenance/{$record->id}", [...$data, 'status' => 'open'])->assertSessionHasErrors('asset_id');
    }
}
