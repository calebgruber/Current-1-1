<?php

namespace Tests\Feature\Inventory;

use App\Domain\Assets\Models\Asset;
use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocationScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_pages_expose_hierarchy_equipment_and_custom_fields(): void
    {
        CustomFieldDefinition::create(['entity_type' => 'location', 'key' => 'zone', 'label' => 'Zone', 'type' => 'text', 'show_in_list' => true]);
        $parent = Location::create(['name' => 'Warehouse', 'type' => 'warehouse']);
        $child = Location::create(['name' => 'Shelf', 'parent_id' => $parent->id, 'type' => 'shelf', 'code' => 'SH-1', 'barcode' => 'ABC']);
        $child->setCustomFields(['zone' => 'North']);
        $product = Product::factory()->bulk()->create(['name' => 'Cable']);
        StockLevel::create(['location_id' => $child->id, 'product_id' => $product->id, 'quantity' => 12, 'quantity_damaged' => 2]);
        $assetProduct = Product::factory()->create(['name' => 'Light']);
        Asset::create(['location_id' => $child->id, 'product_id' => $assetProduct->id, 'asset_number' => 'LIGHT-1', 'condition' => 'good', 'status' => 'available']);
        $this->actingAs($this->superAdmin());

        $this->get('/inventory/locations?search=SH-1&type=shelf')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Locations/Index')->has('locations', 1)
            ->where('locations.0.path', 'Warehouse › Shelf')->where('locations.0.bulk_units', 12)
            ->where('locations.0.assets_count', 1)->where('locations.0.custom_fields.zone', 'North')
            ->where('customFieldColumns.0.key', 'zone')->where('filters.type', 'shelf'));
        $this->get("/inventory/locations/create?parent={$parent->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Locations/Form')->where('defaultParentId', $parent->id)->has('parentOptions', 2)->where('customFieldSchema.0.key', 'zone'));
        $this->get("/inventory/locations/{$child->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Locations/Show')->where('location.path', 'Warehouse › Shelf')
            ->where('breadcrumbs.0.name', 'Warehouse')->where('assets.0.asset_number', 'LIGHT-1')
            ->where('assetsTotal', 1)->where('stock.0.quantity_damaged', 2)
            ->where('label.code', 'ABC')->where('customFields.zone', 'North')->has('history'));
        $this->get("/inventory/locations/{$parent->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Locations/Show')->where('children.0.id', $child->id));
        $this->get("/inventory/locations/{$parent->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Locations/Form')->has('parentOptions', 0)->where('location.id', $parent->id));
    }

    public function test_location_create_update_validation_and_delete_guards(): void
    {
        CustomFieldDefinition::create(['entity_type' => 'location', 'key' => 'zone', 'label' => 'Zone', 'type' => 'text', 'is_required' => true]);
        $parent = Location::create(['name' => 'Warehouse', 'type' => 'warehouse']);
        $this->actingAs($this->superAdmin());
        $this->post('/inventory/locations', ['name' => 'Shelf', 'type' => 'shelf'])->assertSessionHasErrors('custom_fields.zone');
        $this->post('/inventory/locations', ['name' => 'Shelf', 'type' => 'shelf', 'parent_id' => $parent->id, 'code' => ' S1 ', 'is_active' => false, 'custom_fields' => ['zone' => 'A']])
            ->assertSessionHasNoErrors();
        $child = Location::where('name', 'Shelf')->firstOrFail();
        $this->assertSame('S1', $child->code);
        $this->assertFalse($child->is_active);
        $this->assertSame('A', $child->customFields()['zone']);

        $this->put("/inventory/locations/{$parent->id}", ['name' => 'Warehouse', 'type' => 'warehouse', 'parent_id' => $child->id, 'custom_fields' => ['zone' => 'B']])
            ->assertSessionHasErrors('parent_id');
        $this->delete("/inventory/locations/{$parent->id}")->assertSessionHas('error');
        $this->put("/inventory/locations/{$child->id}", ['name' => 'Shelf 2', 'type' => 'bin', 'is_active' => false, 'custom_fields' => ['zone' => 'B']])
            ->assertRedirect("/inventory/locations/{$child->id}")->assertSessionHasNoErrors();
        $this->assertSame('B', $child->fresh()->customFields()['zone']);
        $this->assertFalse($child->fresh()->is_active);

        $product = Product::factory()->bulk()->create();
        StockLevel::create(['location_id' => $child->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->delete("/inventory/locations/{$child->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('locations', ['id' => $child->id]);
    }

    public function test_each_location_action_enforces_its_permission(): void
    {
        $location = Location::create(['name' => 'Warehouse', 'type' => 'warehouse']);
        $this->actingAs($this->userWithPermissions(['inventory.locations.view']));
        $this->get('/inventory/locations')->assertOk();
        $this->get("/inventory/locations/{$location->id}")->assertOk();
        $this->get('/inventory/locations/create')->assertForbidden();
        $this->post('/inventory/locations', [])->assertForbidden();
        $this->get("/inventory/locations/{$location->id}/edit")->assertForbidden();
        $this->put("/inventory/locations/{$location->id}", [])->assertForbidden();
        $this->delete("/inventory/locations/{$location->id}")->assertForbidden();

        $this->actingAs($this->userWithPermissions([]));
        $this->get('/inventory/locations')->assertForbidden();
        $this->get("/inventory/locations/{$location->id}")->assertForbidden();
    }
}
