<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\Tag;
use App\Domain\Inventory\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_relationships_and_tracking(): void
    {
        $accessory = Product::factory()->create(['name' => 'Cable']);
        $alternative = Product::factory()->create(['name' => 'Alternate']);
        $this->actingAs($this->superAdmin());

        $this->post('/inventory/products', [
            'name' => 'Console', 'tracking' => 'bulk', 'tags' => ['Audio', 'Live'],
            'accessories' => [['accessory_product_id' => $accessory->id, 'quantity' => 2, 'type' => 'optional', 'auto_add' => false, 'notes' => 'Spare']],
            'alternatives' => [['product_id' => $alternative->id]],
        ])->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Console')->firstOrFail();
        $this->assertSame('bulk', $product->tracking->value);
        $this->assertEqualsCanonicalizing(['Audio', 'Live'], $product->tags()->pluck('name')->all());
        $this->assertDatabaseHas('product_accessories', ['product_id' => $product->id, 'accessory_product_id' => $accessory->id, 'type' => 'optional', 'auto_add' => false]);
        $this->assertDatabaseHas('product_alternatives', ['product_id' => $product->id, 'alternative_product_id' => $alternative->id]);
        $this->assertSame(0, $alternative->alternatives()->count());

        $this->get("/inventory/products/{$product->id}/edit")->assertOk()
            ->assertSee('Edit product')->assertSee('Accessories')->assertSee('Cable')
            ->assertSee('Alternative products')->assertSee('Alternate')->assertSee('Audio')->assertSee('Live');
        $this->put("/inventory/products/{$product->id}", ['name' => 'Console', 'tracking' => 'serialized'])
            ->assertSessionHasErrors('tracking');
        $this->assertSame('bulk', $product->fresh()->tracking->value);

        $this->put("/inventory/products/{$product->id}", [
            'name' => 'Console', 'tracking' => 'bulk', 'tags' => ['Updated'], 'accessories' => [], 'alternatives' => [],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Updated'], $product->tags()->pluck('name')->all());
        $this->assertSame(0, $product->accessories()->count());
        $this->assertSame(0, $product->alternatives()->count());

        $this->post('/inventory/products', ['name' => 'Serialized', 'tracking' => 'serialized'])->assertSessionHasNoErrors();
        $this->assertSame('serialized', Product::query()->where('name', 'Serialized')->firstOrFail()->tracking->value);
    }

    public function test_associations_are_validated_and_omissions_preserve_existing_rows(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $deleted = Product::factory()->create();
        $deleted->delete();
        $product->alternatives()->attach($other);
        $this->actingAs($this->superAdmin());
        $base = ['name' => $product->name, 'tracking' => 'serialized'];

        $this->put("/inventory/products/{$product->id}", $base)->assertSessionHasNoErrors();
        $this->assertSame(1, $product->alternatives()->count());
        $this->put("/inventory/products/{$product->id}", $base + ['alternatives' => [['product_id' => $product->id]]])
            ->assertSessionHasErrors('alternatives.0.product_id');
        $this->put("/inventory/products/{$product->id}", $base + ['alternatives' => [['product_id' => $other->id], ['product_id' => $other->id]]])
            ->assertSessionHasErrors('alternatives.0.product_id');
        $this->put("/inventory/products/{$product->id}", $base + ['alternatives' => [['product_id' => $deleted->id]]])
            ->assertSessionHasErrors('alternatives.0.product_id');
        $this->put("/inventory/products/{$product->id}", $base + ['accessories' => [['accessory_product_id' => $product->id, 'quantity' => 1, 'type' => 'required']]])
            ->assertSessionHasErrors('accessories.0.accessory_product_id');
        $this->put("/inventory/products/{$product->id}", $base + ['accessories' => [['accessory_product_id' => $other->id, 'quantity' => 0, 'type' => 'invalid']]])
            ->assertSessionHasErrors(['accessories.0.quantity', 'accessories.0.type']);
        $this->assertSame(1, $product->alternatives()->count());
    }

    public function test_associations_require_product_view_permission(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $product->alternatives()->attach($other);
        $this->actingAs($this->userWithPermissions(['inventory.products.edit']));
        $payload = ['name' => $product->name, 'tracking' => 'serialized'];

        $this->get("/inventory/products/{$product->id}/edit")->assertOk()
            ->assertSee('Product view permission is required')->assertDontSee('Alternative products');
        $this->put("/inventory/products/{$product->id}", $payload + ['alternatives' => []])->assertForbidden();
        $this->put("/inventory/products/{$product->id}", $payload + ['accessories' => []])->assertForbidden();
        $this->put("/inventory/products/{$product->id}", $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, $product->alternatives()->count());
        $this->actingAs($this->userWithPermissions(['inventory.products.view']))
            ->put("/inventory/products/{$product->id}", $payload + ['alternatives' => []])->assertForbidden();
    }

    public function test_tag_names_remain_distinct_and_reusable_when_slugs_collide_or_are_empty(): void
    {
        $product = Product::factory()->create();
        $legacy = Tag::query()->create(['name' => '🎛️', 'slug' => 'legacy-random']);
        $other = Tag::query()->create(['name' => 'Existing', 'slug' => 'power']);
        $service = app(ProductService::class);
        $names = ['🎛️', '🎤', '🎸', 'Power', 'Power!', 'Power!'];

        $service->syncTags($product, $names);
        $this->assertSame(5, $product->tags()->count());
        $this->assertEqualsCanonicalizing(array_unique($names), $product->tags()->pluck('name')->all());
        $this->assertTrue($product->tags()->whereKey($legacy->id)->exists());
        $this->assertFalse($product->tags()->whereKey($other->id)->exists());
        $ids = $product->tags()->pluck('tags.id')->all();
        $service->syncTags($product, $names);
        $this->assertEqualsCanonicalizing($ids, $product->tags()->pluck('tags.id')->all());
    }

    public function test_omitted_relationships_preserve_concurrent_changes_but_explicit_clears_apply(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $this->actingAs($this->superAdmin());
        $this->get("/inventory/products/{$product->id}/edit")->assertOk();

        app(ProductService::class)->save($product, [
            'tags' => ['Concurrent'],
            'accessories' => [['accessory_product_id' => $other->id, 'quantity' => 2, 'type' => 'required', 'auto_add' => true]],
            'alternatives' => [['product_id' => $other->id]],
        ]);
        $base = ['name' => 'Edited from stale form', 'tracking' => 'serialized'];
        $this->put("/inventory/products/{$product->id}", $base)->assertSessionHasNoErrors();
        $this->assertSame(['Concurrent'], $product->tags()->pluck('name')->all());
        $this->assertSame(1, $product->accessories()->count());
        $this->assertSame(1, $product->alternatives()->count());

        $this->put("/inventory/products/{$product->id}", $base + ['tags' => [], 'accessories' => [], 'alternatives' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, $product->tags()->count());
        $this->assertSame(0, $product->accessories()->count());
        $this->assertSame(0, $product->alternatives()->count());
    }

    public function test_category_assignment_is_revalidated_inside_product_transaction(): void
    {
        $product = Product::factory()->create();
        try {
            app(ProductService::class)->save($product, ['name' => 'Changed', 'category_id' => 999999]);
            $this->fail('A missing category should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category_id', $e->errors());
        }
        $this->assertNotSame('Changed', $product->fresh()->name);
    }
}
