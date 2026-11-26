<?php

namespace Tests\Feature\Inventory;

use App\Domain\Assets\Models\Asset;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_module_pages_resolve_and_expose_records(): void
    {
        $product = Product::factory()->create(['name' => 'Console']);
        $asset = Asset::query()->create([
            'product_id' => $product->id, 'asset_number' => 'CON-001', 'condition' => 'good', 'status' => 'available',
        ]);
        $contact = Contact::query()->create(['name' => 'Stage Manager', 'type' => 'person']);
        $this->actingAs($this->superAdmin());

        $this->get('/inventory/products')->assertOk()->assertSee('Products')->assertSee('Console');
        $this->get('/inventory/products/create')->assertOk()->assertSee('New product');
        $this->get("/inventory/products/{$product->id}")->assertOk()->assertSee('Console')->assertSee('Availability');
        $this->get("/inventory/products/{$product->id}/edit")->assertOk()->assertSee('Edit product');
        $this->get('/assets')->assertOk()->assertSee('Assets')->assertSee('CON-001');
        $this->get('/assets/create')->assertOk()->assertSee('New asset');
        $this->get("/assets/{$asset->id}")->assertOk()->assertSee('CON-001')->assertSee('Condition reports');
        $this->get("/assets/{$asset->id}/edit")->assertOk()->assertSee('Edit asset');
        $this->get('/contacts')->assertOk()->assertViewIs('contacts.index')->assertSee('Contacts')->assertSee('Stage Manager');
        $this->get('/contacts/create')->assertOk()->assertViewIs('contacts.form')->assertSee('New contact');
        $this->get("/contacts/{$contact->id}")->assertOk()->assertViewIs('contacts.show')->assertSee('Stage Manager');
        $this->get("/contacts/{$contact->id}/edit")->assertOk()->assertViewIs('contacts.form')->assertSee('Edit contact');
        $this->get("/availability?product={$product->id}&from=2030-01-01&to=2030-01-02")->assertOk()->assertViewIs('availability.index')->assertViewHas('initial.product.id', $product->id);
    }

    public function test_product_edit_preserves_omitted_relationships_and_inactive_state(): void
    {
        $product = Product::factory()->create(['is_active' => false]);
        $accessory = Product::factory()->create();
        $tag = Tag::query()->create(['name' => 'Lighting', 'slug' => 'lighting']);
        $product->tags()->attach($tag);
        $product->accessories()->create(['accessory_product_id' => $accessory->id, 'quantity' => 1, 'type' => 'required', 'auto_add' => true]);

        $this->actingAs($this->superAdmin())
            ->put("/inventory/products/{$product->id}", ['name' => 'Renamed', 'tracking' => 'serialized'])
            ->assertRedirect("/inventory/products/{$product->id}")
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame(1, $product->tags()->count());
        $this->assertSame(1, $product->accessories()->count());

        $this->put("/inventory/products/{$product->id}", ['name' => 'Renamed', 'tracking' => 'serialized', 'tags' => [], 'accessories' => [], 'is_active' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->is_active);
        $this->assertSame(0, $product->tags()->count());
        $this->assertSame(0, $product->accessories()->count());
    }

    public function test_stock_adjustment_and_contact_creation_validate_input(): void
    {
        $product = Product::factory()->bulk()->create();
        $this->actingAs($this->superAdmin());
        $this->put("/inventory/products/{$product->id}/stock", ['quantity' => 3, 'quantity_damaged' => 4])
            ->assertSessionHasErrors('quantity');
        $this->put("/inventory/products/{$product->id}/stock", ['quantity' => 3, 'quantity_damaged' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity' => 3, 'quantity_damaged' => 1]);
        $this->post('/contacts', ['type' => 'person', 'name' => 'New contact', 'email' => 'bad'])
            ->assertSessionHasErrors('email');
        $this->post('/contacts', ['type' => 'person', 'name' => 'New contact', 'email' => 'person@example.test'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contacts', ['name' => 'New contact', 'email' => 'person@example.test']);
    }
}
