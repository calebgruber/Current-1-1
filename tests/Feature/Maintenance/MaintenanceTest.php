<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Assets\Enums\AssetStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\AvailabilityService;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Product $product, ?Asset $asset = null, array $changes = []): array
    {
        return array_merge([
            'product_id' => $product->id, 'asset_id' => $asset?->id, 'quantity' => 1,
            'title' => 'Repair light', 'type' => 'repair', 'priority' => 'normal', 'status' => 'open',
        ], $changes);
    }

    public function test_create_list_show_edit_and_asset_lifecycle(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $user = $this->superAdmin();
        $this->actingAs($user);
        $this->get('/maintenance/create')->assertOk()->assertSee('New maintenance record')->assertSee('Scan or enter asset barcode');
        $this->post('/maintenance', $this->payload($product, $asset))->assertSessionHasNoErrors();
        $record = MaintenanceRecord::query()->firstOrFail();
        $this->assertSame($user->id, $record->reported_by);
        $this->assertSame(AssetStatus::InMaintenance, $asset->fresh()->status);
        $this->assertDatabaseHas('asset_status_history', ['asset_id' => $asset->id, 'to_status' => 'maintenance', 'context_id' => $record->id, 'context_type' => $record->getMorphClass(), 'user_id' => $user->id]);
        $this->get('/maintenance?search=Repair&status=open')->assertOk()->assertSee($record->reference)->assertSee('Repair light');
        $this->get("/maintenance/{$record->id}")->assertOk()->assertSee('Repair light')->assertSee('Description');
        $this->get("/maintenance/{$record->id}/edit")->assertOk()->assertSee('Edit maintenance record');
        $this->put("/maintenance/{$record->id}", $this->payload($product, $asset, ['status' => 'in_progress']))->assertSessionHasNoErrors();
        $this->assertNotNull($record->fresh()->started_at);
        $this->put("/maintenance/{$record->id}", $this->payload($product, $asset, ['status' => 'completed', 'resolution' => 'Fixed']))->assertSessionHasNoErrors();
        $this->assertNotNull($record->fresh()->completed_at);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->put("/maintenance/{$record->id}", $this->payload($product, $asset, ['status' => 'open']))->assertSessionHasNoErrors();
        $this->assertNull($record->fresh()->completed_at);
        $this->assertSame(AssetStatus::InMaintenance, $asset->fresh()->status);
    }

    public function test_multiple_records_do_not_prematurely_release_or_write_off_asset(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'damaged']);
        $this->actingAs($this->superAdmin());
        $this->post('/maintenance', $this->payload($product, $asset))->assertSessionHasNoErrors();
        $this->post('/maintenance', $this->payload($product, $asset, ['title' => 'Second']))->assertSessionHasNoErrors();
        [$first, $second] = MaintenanceRecord::query()->orderBy('id')->get()->all();
        $this->put("/maintenance/{$first->id}", $this->payload($product, $asset, ['status' => 'written_off']))->assertSessionHasErrors('status');
        $this->put("/maintenance/{$first->id}", $this->payload($product, $asset, ['status' => 'completed']))->assertSessionHasNoErrors();
        $this->assertSame(AssetStatus::Damaged, $asset->fresh()->status);
        $this->put("/maintenance/{$second->id}", $this->payload($product, $asset, ['status' => 'written_off']))->assertSessionHasNoErrors();
        $this->assertSame(AssetStatus::Retired, $asset->fresh()->status);
        $this->put("/maintenance/{$second->id}", $this->payload($product, $asset, ['status' => 'open']))->assertSessionHasErrors('status');
    }

    public function test_permissions_and_approval_are_enforced(): void
    {
        $product = Product::factory()->bulk()->create();
        $record = MaintenanceRecord::query()->create($this->payload($product));
        $this->actingAs($this->userWithPermissions([]))->get('/maintenance')->assertForbidden();
        $this->get("/maintenance/{$record->id}")->assertForbidden();
        $this->post('/maintenance', $this->payload($product))->assertForbidden();
        $this->actingAs($this->userWithPermissions(['maintenance.records.create', 'maintenance.records.edit']));
        $this->post('/maintenance', $this->payload($product, null, ['status' => 'completed']))->assertForbidden();
        $this->put("/maintenance/{$record->id}", $this->payload($product, null, ['status' => 'completed']))->assertForbidden();
        $this->put("/maintenance/{$record->id}", $this->payload($product, null, ['status' => 'in_progress']))->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $record->fresh()->status->value);
    }

    public function test_asset_lookup_requires_create_permission_and_matches_exact_codes_within_product(): void
    {
        $product = Product::factory()->create(['is_active' => false]);
        $other = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'barcode' => 'BAR-100', 'serial_number' => 'Serial-100', 'asset_number' => 'ASSET-100']);
        Asset::factory()->create(['product_id' => $other->id, 'serial_number' => 'Serial-100']);
        $body = ['product_id' => $product->id, 'code' => 'BAR-100'];

        $this->postJson('/maintenance/asset-lookup', $body)->assertUnauthorized();
        $this->actingAs($this->userWithPermissions(['maintenance.records.view']))->postJson('/maintenance/asset-lookup', $body)->assertForbidden();
        $this->actingAs($this->userWithPermissions(['maintenance.records.create']));
        foreach (['BAR-100', 'Serial-100', 'ASSET-100'] as $code) {
            $this->postJson('/maintenance/asset-lookup', ['product_id' => $product->id, 'code' => $code])
                ->assertOk()->assertExactJson(['asset' => ['id' => $asset->id, 'asset_number' => 'ASSET-100']]);
        }
        $this->postJson('/maintenance/asset-lookup', ['product_id' => $product->id, 'code' => 'serial-100'])->assertNotFound();
        $this->postJson('/maintenance/asset-lookup', ['product_id' => $other->id, 'code' => 'BAR-100'])->assertNotFound();
        $this->postJson('/maintenance/asset-lookup', ['product_id' => $product->id, 'code' => 'unknown'])->assertNotFound();
        $this->postJson('/maintenance/asset-lookup', ['product_id' => $product->id, 'code' => ''])->assertUnprocessable();
        $this->postJson('/maintenance/asset-lookup', ['product_id' => $product->id, 'code' => str_repeat('a', 129)])->assertUnprocessable();
        $this->postJson('/maintenance/asset-lookup', ['product_id' => Product::factory()->bulk()->create()->id, 'code' => 'BAR-100'])->assertUnprocessable();
    }

    public function test_lookup_detects_ambiguous_codes_and_rejects_deleted_or_unavailable_assets(): void
    {
        $product = Product::factory()->create();
        $first = Asset::factory()->create(['product_id' => $product->id, 'serial_number' => 'DUPLICATE']);
        $second = Asset::factory()->create(['product_id' => $product->id, 'serial_number' => 'DUPLICATE']);
        $this->actingAs($this->superAdmin());
        $lookup = fn (string $code) => $this->postJson('/maintenance/asset-lookup', ['product_id' => $product->id, 'code' => $code]);
        $lookup('DUPLICATE')->assertStatus(409)->assertJsonMissingPath('asset');
        $second->update(['serial_number' => $first->barcode]);
        $lookup($first->barcode)->assertStatus(409)->assertJsonMissingPath('asset');
        $second->delete();
        $lookup('DUPLICATE')->assertOk()->assertJsonPath('asset.id', $first->id);
        foreach (['available', 'damaged', 'maintenance'] as $status) {
            $first->update(['status' => $status]);
            $lookup($first->barcode)->assertOk();
        }
        foreach (['retired', 'lost', 'allocated', 'checked_out'] as $status) {
            $first->update(['status' => $status]);
            $lookup($first->barcode)->assertUnprocessable()->assertJsonMissingPath('asset');
        }
        $first->delete();
        $lookup($first->barcode)->assertNotFound();
        $product->delete();
        $lookup('DUPLICATE')->assertUnprocessable();
    }

    public function test_lookup_selection_is_revalidated_on_save_without_a_job(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id]);
        $this->actingAs($this->superAdmin());
        $this->postJson('/maintenance/asset-lookup', ['product_id' => $product->id, 'code' => $asset->barcode])->assertOk();
        $asset->update(['status' => 'checked_out']);
        $this->post('/maintenance', $this->payload($product, $asset))->assertSessionHasErrors('asset_id');
        $asset->update(['status' => 'available']);
        $this->post('/maintenance', $this->payload($product, $asset, ['job_id' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_records', ['product_id' => $product->id, 'asset_id' => $asset->id, 'job_id' => null]);
        $job = ProductionJob::factory()->create();
        $this->post('/maintenance', $this->payload($product, $asset, ['job_id' => $job->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('maintenance_records', ['asset_id' => $asset->id, 'job_id' => $job->id]);
    }

    public function test_invalid_targets_and_unsafe_asset_states_are_rejected(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $other->id, 'status' => 'available']);
        $this->actingAs($this->superAdmin());
        $this->post('/maintenance', $this->payload($product))->assertSessionHasErrors('asset_id');
        $this->post('/maintenance', $this->payload($product, $asset))->assertSessionHasErrors('asset_id');
        $this->post('/maintenance', $this->payload($other, $asset, ['quantity' => 2]))->assertSessionHasErrors('asset_id');
        $asset->update(['status' => 'checked_out']);
        $this->post('/maintenance', $this->payload($other, $asset))->assertSessionHasErrors('asset_id');
        $asset->update(['status' => 'available']);
        $this->post('/maintenance', $this->payload($other, $asset))->assertSessionHasNoErrors();
        $record = MaintenanceRecord::query()->firstOrFail();
        $this->put("/maintenance/{$record->id}", $this->payload($other, $asset, ['quantity' => 2]))->assertSessionHasErrors('product_id');
        $asset->update(['status' => 'lost']);
        $this->put("/maintenance/{$record->id}", $this->payload($other, $asset, ['status' => 'completed']))->assertSessionHasNoErrors();
        $this->assertSame(AssetStatus::Lost, $asset->fresh()->status);
    }

    public function test_bulk_records_do_not_adjust_stock_counters(): void
    {
        $product = Product::factory()->bulk()->create();
        $level = $product->stockLevels()->create(['quantity' => 10, 'quantity_maintenance' => 2, 'quantity_damaged' => 1, 'quantity_lost' => 0]);
        $availability = fn () => app(AvailabilityService::class)->forProduct($product, new \DateTimeImmutable('2030-01-01'), new \DateTimeImmutable('2030-01-02'));
        $this->assertSame(7, $availability()->available);
        $this->actingAs($this->superAdmin())->post('/maintenance', $this->payload($product, null, ['quantity' => 3]))->assertSessionHasNoErrors();
        $this->assertSame(7, $availability()->available);
        $record = MaintenanceRecord::query()->firstOrFail();
        $this->put("/maintenance/{$record->id}", $this->payload($product, null, ['quantity' => 3, 'status' => 'completed']))->assertSessionHasNoErrors();
        $this->assertSame(2, $level->fresh()->quantity_maintenance);
        $this->assertSame(1, $level->fresh()->quantity_damaged);
        $this->assertSame(7, $availability()->available);
    }

    public function test_soft_deleted_targets_can_be_closed_but_not_reopened(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $this->actingAs($this->superAdmin())->post('/maintenance', $this->payload($product, $asset))->assertSessionHasNoErrors();
        $record = MaintenanceRecord::query()->firstOrFail();
        $product->delete();
        $asset->delete();

        $this->get("/maintenance/{$record->id}")->assertOk()->assertSee($product->name)->assertSee($asset->asset_number);
        $this->get("/maintenance/{$record->id}/edit")->assertOk()->assertSee($product->name)->assertSee($asset->asset_number);
        $this->put("/maintenance/{$record->id}", $this->payload($product, $asset, ['status' => 'completed']))->assertSessionHasNoErrors();
        $this->assertSame('completed', $record->fresh()->status->value);
        $this->assertSame(AssetStatus::InMaintenance, $asset->fresh()->status);
        $this->assertDatabaseMissing('search_index', ['entity_type' => $asset->getMorphClass(), 'entity_id' => $asset->id]);
        $this->put("/maintenance/{$record->id}", $this->payload($product, $asset, ['status' => 'open']))->assertSessionHasErrors('status');
        $this->post('/maintenance', $this->payload($product, $asset))->assertSessionHasErrors('product_id');
    }

    public function test_reference_sequence_is_initialized_from_legacy_references(): void
    {
        $product = Product::factory()->bulk()->create();
        MaintenanceRecord::query()->create($this->payload($product, null, ['reference' => 'MR-00400']));
        $migration = require database_path('migrations/2026_11_26_000001_create_maintenance_reference_sequence.php');
        $migration->down();
        $migration->up();
        $first = MaintenanceRecord::query()->create($this->payload($product));
        $second = MaintenanceRecord::query()->create($this->payload($product));
        $this->assertSame('MR-00401', $first->reference);
        $this->assertSame('MR-00402', $second->reference);
    }

    public function test_soft_deleted_product_can_be_closed_with_a_live_asset(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $this->actingAs($this->superAdmin())->post('/maintenance', $this->payload($product, $asset))->assertSessionHasNoErrors();
        $record = MaintenanceRecord::query()->firstOrFail();
        $product->delete();
        $this->put("/maintenance/{$record->id}", $this->payload($product, $asset, ['status' => 'completed']))->assertSessionHasNoErrors();
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->put("/maintenance/{$record->id}", $this->payload($product, $asset, ['status' => 'open']))->assertSessionHasErrors('status');
    }

    public function test_reference_sequence_rolls_back_with_a_failed_creation(): void
    {
        $product = Product::factory()->bulk()->create();
        try {
            DB::transaction(function () use ($product) {
                MaintenanceRecord::query()->create($this->payload($product));
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }
        $record = MaintenanceRecord::query()->create($this->payload($product));
        $this->assertSame('MR-00001', $record->reference);
        $this->assertSame(1, MaintenanceRecord::query()->count());
    }

    public function test_records_created_outside_the_module_hold_an_available_asset(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $record = MaintenanceRecord::query()->create($this->payload($product, $asset));
        $this->assertSame(AssetStatus::InMaintenance, $asset->fresh()->status);
        $this->assertDatabaseHas('asset_status_history', [
            'asset_id' => $asset->id, 'context_id' => $record->id, 'context_type' => $record->getMorphClass(),
            'from_status' => 'available', 'to_status' => 'maintenance',
        ]);
    }

    public function test_active_record_check_uses_a_current_locking_read_on_mysql(): void
    {
        $product = Product::factory()->create();
        $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $this->actingAs($this->superAdmin())->post('/maintenance', $this->payload($product, $asset))->assertSessionHasNoErrors();
        $record = MaintenanceRecord::query()->firstOrFail();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });
        $this->put("/maintenance/{$record->id}", $this->payload($product, $asset, ['status' => 'completed']))->assertSessionHasNoErrors();
        $activeReads = array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'select') && str_contains($sql, 'maintenance_records') && str_contains($sql, 'status') && str_contains($sql, 'limit 1')));
        $this->assertNotEmpty($activeReads);
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', end($activeReads));
        }
    }
}
