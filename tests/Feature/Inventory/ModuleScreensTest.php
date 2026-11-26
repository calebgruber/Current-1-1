<?php

namespace Tests\Feature\Inventory;

use App\Domain\Assets\Models\Asset;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

        $this->get('/inventory/products')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Inventory/Products/Index')->has('products.data', 1));
        $this->get('/inventory/products/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Inventory/Products/Form'));
        $this->get("/inventory/products/{$product->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Inventory/Products/Show')->where('product.name', 'Console'));
        $this->get("/inventory/products/{$product->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Inventory/Products/Form'));
        $this->get('/assets')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Assets/Index')->has('assets.data', 1));
        $this->get('/assets/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Assets/Form'));
        $this->get("/assets/{$asset->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Assets/Show')->where('asset.asset_number', 'CON-001'));
        $this->get("/assets/{$asset->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Assets/Form'));
        $this->get('/contacts')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Contacts/Index')->has('contacts.data', 1));
        $this->get('/contacts/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Contacts/Form'));
        $this->get("/contacts/{$contact->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Contacts/Show')->where('contact.name', 'Stage Manager'));
        $this->get("/contacts/{$contact->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Contacts/Form'));
        $this->get("/availability?product={$product->id}&from=2030-01-01&to=2030-01-02")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Availability/Index')->where('initial.product.id', $product->id));
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
