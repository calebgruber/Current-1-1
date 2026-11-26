<?php

namespace Tests\Feature\Jobs;

use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Jobs\Models\ProductionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_plus_spare_gives_pull_total(): void
    {
        $lustr = Product::factory()->bulk()->create(['name' => 'Lustr']);
        StockLevel::query()->create(['product_id' => $lustr->id, 'quantity' => 40]);
        $job = ProductionJob::factory()->create();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post("/jobs/{$job->id}/items", [
            'product_id' => $lustr->id,
            'required_quantity' => 20,
            'spare_quantity' => 5,
        ])->assertSessionHasNoErrors();

        $item = $job->items()->firstOrFail();
        $this->assertSame(25, $item->pull_quantity);

        $this->actingAs($admin)->getJson("/jobs/{$job->id}/pull-sheet")
            ->assertOk()
            ->assertJsonPath('sheet.totals.required', 20)
            ->assertJsonPath('sheet.totals.spare', 5)
            ->assertJsonPath('sheet.totals.pull', 25)
            ->assertJsonPath('sheet.sections.0.lines.0.pull', 25);

        $this->actingAs($admin)->get("/jobs/{$job->id}/pull-sheet")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Jobs/PullSheet')->where('sheet.totals.pull', 25));

        $this->actingAs($admin)->get("/jobs/{$job->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('totals.pull', 25)
                ->where('items.0.required_quantity', 20)
                ->where('items.0.spare_quantity', 5)
                ->where("availability.{$lustr->id}.level", 'ok'));
    }

    public function test_quantity_required_is_mandatory(): void
    {
        $product = Product::factory()->bulk()->create();
        $job = ProductionJob::factory()->create();
        $this->actingAs($this->superAdmin())->post("/jobs/{$job->id}/items", ['product_id' => $product->id])
            ->assertSessionHasErrors('required_quantity');
    }

    public function test_check_endpoint_reports_shortage(): void
    {
        $product = Product::factory()->bulk()->create();
        StockLevel::query()->create(['product_id' => $product->id, 'quantity' => 4]);
        $job = ProductionJob::factory()->create();

        $this->actingAs($this->userWithPermissions(['jobs.jobs.view']))
            ->getJson("/jobs/{$job->id}/items/check?product_id={$product->id}&quantity=6")
            ->assertOk()
            ->assertJsonPath('conflict.level', 'shortage')
            ->assertJsonPath('conflict.shortage', 2)
            ->assertJsonPath('blocks', false);
    }

    public function test_overbooking_allocation_on_reserved_job_is_blocked(): void
    {
        $product = Product::factory()->bulk()->create();
        StockLevel::query()->create(['product_id' => $product->id, 'quantity' => 4]);
        $job = ProductionJob::factory()->create(['stage' => 'reserved']);

        $this->actingAs($this->superAdmin())
            ->post("/jobs/{$job->id}/items", ['product_id' => $product->id, 'required_quantity' => 3, 'spare_quantity' => 3])
            ->assertSessionHasErrors('overbooking');
        $this->assertSame(0, $job->items()->count());

        $this->actingAs($this->superAdmin())
            ->post("/jobs/{$job->id}/items", ['product_id' => $product->id, 'required_quantity' => 3, 'spare_quantity' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $job->items()->count());
    }

    public function test_items_require_permission_and_locked_stages(): void
    {
        $product = Product::factory()->bulk()->create();
        $job = ProductionJob::factory()->create();
        $this->actingAs($this->userWithPermissions(['jobs.jobs.view']))
            ->post("/jobs/{$job->id}/items", ['product_id' => $product->id, 'required_quantity' => 1])
            ->assertForbidden();

        $locked = ProductionJob::factory()->create(['stage' => 'checked_out']);
        $this->actingAs($this->superAdmin())
            ->post("/jobs/{$locked->id}/items", ['product_id' => $product->id, 'required_quantity' => 1])
            ->assertSessionHasErrors('item');
    }

    public function test_assets_can_be_assigned_to_a_line(): void
    {
        $product = Product::factory()->create();
        $assets = Asset::factory()->count(3)->create(['product_id' => $product->id]);
        $job = ProductionJob::factory()->create();
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post("/jobs/{$job->id}/items", ['product_id' => $product->id, 'required_quantity' => 1, 'spare_quantity' => 1]);
        $item = $job->items()->firstOrFail();

        $this->actingAs($admin)->getJson("/jobs/{$job->id}/items/{$item->id}/assets")->assertOk()->assertJsonCount(3);

        $this->actingAs($admin)->putJson("/jobs/{$job->id}/items/{$item->id}/assets", ['assets' => [
            ['asset_id' => $assets[0]->id, 'role' => 'active'],
            ['asset_id' => $assets[1]->id, 'role' => 'spare'],
            ['asset_id' => $assets[2]->id, 'role' => 'spare'],
        ]])->assertStatus(422);

        $this->actingAs($admin)->putJson("/jobs/{$job->id}/items/{$item->id}/assets", ['assets' => [
            ['asset_id' => $assets[0]->id, 'role' => 'active'],
            ['asset_id' => $assets[1]->id, 'role' => 'spare'],
        ]])->assertOk();
        $this->assertSame(2, $item->assignedAssets()->count());
    }

    public function test_edit_updates_allocation_accessories_and_reservations(): void
    {
        $product = Product::factory()->bulk()->create();
        $accessory = Product::factory()->bulk()->create();
        $product->accessories()->create(['accessory_product_id' => $accessory->id, 'quantity' => 2, 'type' => 'required', 'auto_add' => true]);
        $job = ProductionJob::factory()->create(['stage' => 'quote']);
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post("/jobs/{$job->id}/items", ['product_id' => $product->id, 'required_quantity' => 2, 'spare_quantity' => 1]);
        $item = $job->items()->whereNull('parent_item_id')->firstOrFail();
        $child = $item->children()->firstOrFail();

        $editor = $this->userWithPermissions(['jobs.jobs.view', 'jobs.items.edit']);
        $this->actingAs($editor)->get("/jobs/{$job->id}")
            ->assertInertia(fn (Assert $p) => $p->where('can.items_edit', true)->where('job.allows_allocation_edits', true));
        $this->actingAs($editor)->put("/jobs/{$job->id}/items/{$item->id}", ['required_quantity' => 3, 'spare_quantity' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $item->fresh()->required_quantity);
        $this->assertSame(2, $item->fresh()->spare_quantity);
        $this->assertSame(6, $child->fresh()->required_quantity);
        $this->assertSame(4, $child->fresh()->spare_quantity);
        $this->assertSame(3, $item->reservation()->firstOrFail()->quantity);
        $this->assertSame(2, $item->reservation()->firstOrFail()->spare_quantity);
    }

    public function test_allocation_edit_enforces_permission_ownership_and_stage(): void
    {
        $product = Product::factory()->bulk()->create();
        $job = ProductionJob::factory()->create();
        $item = $job->items()->create(['product_id' => $product->id, 'required_quantity' => 2]);
        $viewer = $this->userWithPermissions(['jobs.jobs.view']);
        $this->actingAs($viewer)->get("/jobs/{$job->id}")
            ->assertInertia(fn (Assert $p) => $p->where('can.items_edit', false));
        $this->actingAs($viewer)->put("/jobs/{$job->id}/items/{$item->id}", ['required_quantity' => 1])->assertForbidden();
        $other = ProductionJob::factory()->create();
        $this->actingAs($this->superAdmin())->put("/jobs/{$other->id}/items/{$item->id}", ['required_quantity' => 1])->assertNotFound();
        $job->update(['stage' => 'checked_out']);
        $this->actingAs($this->superAdmin())->put("/jobs/{$job->id}/items/{$item->id}", ['required_quantity' => 1])->assertSessionHasErrors('item');
        $this->assertSame(2, $item->fresh()->required_quantity);
    }

    public function test_allocation_edit_rejects_overbooking_and_rolls_back(): void
    {
        $product = Product::factory()->bulk()->create();
        StockLevel::query()->create(['product_id' => $product->id, 'quantity' => 4]);
        $job = ProductionJob::factory()->create(['stage' => 'reserved']);
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post("/jobs/{$job->id}/items", ['product_id' => $product->id, 'required_quantity' => 2]);
        $item = $job->items()->firstOrFail();

        $this->actingAs($admin)->put("/jobs/{$job->id}/items/{$item->id}", ['required_quantity' => 4, 'spare_quantity' => 1])
            ->assertSessionHasErrors('overbooking');
        $this->assertSame(2, $item->fresh()->pull_quantity);
        $this->assertSame(2, $item->reservation()->firstOrFail()->quantity);
        $editor = $this->userWithPermissions(['jobs.items.edit']);
        $this->actingAs($editor)->put("/jobs/{$job->id}/items/{$item->id}", [
            'required_quantity' => 4, 'spare_quantity' => 1, 'override' => true, 'override_reason' => 'Approved shortage',
        ])->assertForbidden();
        $this->actingAs($admin)->put("/jobs/{$job->id}/items/{$item->id}", [
            'required_quantity' => 4, 'spare_quantity' => 1, 'override' => true, 'override_reason' => '',
        ])->assertSessionHasErrors('override_reason');
        $this->actingAs($admin)->put("/jobs/{$job->id}/items/{$item->id}", [
            'required_quantity' => 4, 'spare_quantity' => 1, 'override' => true, 'override_reason' => 'Approved shortage',
        ])->assertSessionHasNoErrors();
        $this->assertSame(5, $item->fresh()->pull_quantity);
    }

    public function test_allocation_edit_cannot_reduce_below_progress_or_assignments_including_accessories(): void
    {
        $product = Product::factory()->bulk()->create();
        $accessory = Product::factory()->create();
        $product->accessories()->create(['accessory_product_id' => $accessory->id, 'quantity' => 1, 'type' => 'required', 'auto_add' => true]);
        $job = ProductionJob::factory()->create();
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post("/jobs/{$job->id}/items", ['product_id' => $product->id, 'required_quantity' => 2, 'spare_quantity' => 1]);
        $item = $job->items()->whereNull('parent_item_id')->firstOrFail();
        $child = $item->children()->firstOrFail();

        $item->update(['picked_quantity' => 3]);
        $this->actingAs($admin)->put("/jobs/{$job->id}/items/{$item->id}", ['required_quantity' => 1, 'spare_quantity' => 1])->assertSessionHasErrors('item');
        $this->assertSame(3, $item->fresh()->pull_quantity);

        $item->update(['picked_quantity' => 0]);
        $asset = Asset::factory()->create(['product_id' => $accessory->id]);
        $child->assignedAssets()->create(['asset_id' => $asset->id, 'role' => 'spare', 'status' => 'allocated']);
        $this->actingAs($admin)->put("/jobs/{$job->id}/items/{$item->id}", ['required_quantity' => 2, 'spare_quantity' => 0])->assertSessionHasErrors('item');
        $this->assertSame(1, $item->fresh()->spare_quantity);
        $this->assertSame(1, $child->fresh()->spare_quantity);
    }
}
