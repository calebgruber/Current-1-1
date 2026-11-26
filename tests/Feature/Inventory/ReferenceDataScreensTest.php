<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferenceDataScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_manufacturer_pages_render(): void
    {
        Category::query()->create(['name' => 'Lighting', 'slug' => 'lighting']);
        Manufacturer::query()->create(['name' => 'Example']);
        $this->actingAs($this->superAdmin());

        $this->get('/inventory/categories')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Categories/Index')->has('categories', 1)->where('categories.0.name', 'Lighting'));
        $this->get('/inventory/manufacturers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Manufacturers/Index')->has('manufacturers.data', 1)->where('manufacturers.data.0.name', 'Example'));
    }

    public function test_categories_reject_cycles_and_reparent_children_on_deletion(): void
    {
        $parent = Category::query()->create(['name' => 'Parent', 'slug' => 'parent']);
        $child = Category::query()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);
        $this->actingAs($this->superAdmin());

        $this->put("/inventory/categories/{$parent->id}", ['name' => 'Parent', 'parent_id' => $child->id])
            ->assertSessionHasErrors('parent_id');
        $this->assertNull($parent->fresh()->parent_id);
        $this->delete("/inventory/categories/{$parent->id}")->assertSessionHasNoErrors();
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_reference_data_mutations_require_permissions(): void
    {
        $user = $this->userWithPermissions(['inventory.categories.view']);
        $this->actingAs($user);

        $this->get('/inventory/categories')->assertOk();
        $this->get('/inventory/manufacturers')->assertOk();
        $this->post('/inventory/categories', ['name' => 'Forbidden'])->assertForbidden();
        $this->post('/inventory/manufacturers', ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_manufacturer_website_must_be_http_or_https(): void
    {
        $this->actingAs($this->superAdmin());
        $this->post('/inventory/manufacturers', ['name' => 'Example', 'website' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('website');
        $this->post('/inventory/manufacturers', ['name' => 'Example', 'website' => 'https://example.test'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('manufacturers', ['name' => 'Example', 'website' => 'https://example.test']);
    }
}
