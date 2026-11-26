<?php

namespace Tests\Feature\Inventory;

use App\Domain\Assets\Models\Asset;
use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get('/inventory/locations?search=SH-1&type=shelf')->assertOk()
            ->assertSee('Warehouse › Shelf')->assertSee('12')->assertSee('North');
        $this->get("/inventory/locations/create?parent={$parent->id}")->assertOk()
            ->assertSee('New location')->assertSee('Warehouse')->assertSee('Zone');
        $this->get("/inventory/locations/{$child->id}")->assertOk()
            ->assertSee('Warehouse › Shelf')->assertSee('LIGHT-1')->assertSee('ABC')->assertSee('North')->assertSee('History');
        $this->get("/inventory/locations/{$parent->id}")->assertOk()->assertSee('Shelf');
        $this->get("/inventory/locations/{$parent->id}/edit")->assertOk()->assertSee('Edit location')->assertSee('Warehouse');
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
