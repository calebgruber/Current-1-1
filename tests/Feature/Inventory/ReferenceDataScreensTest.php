<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Manufacturer;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\HierarchyOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_category_delete_checks_assigned_products_after_locking_category(): void
    {
        $category = Category::query()->create(['name' => 'A', 'slug' => 'a']);
        $product = Product::query()->create(['name' => 'Product', 'category_id' => $category->id]);
        $this->actingAs($this->superAdmin());

        $queries = [];
        $outerLevel = DB::transactionLevel();
        DB::listen(function ($query) use (&$queries, $outerLevel) {
            if (DB::transactionLevel() > $outerLevel) {
                $queries[] = strtolower(str_replace(['"', '`'], '', $query->sql));
            }
        });

        $this->delete("/inventory/categories/{$category->id}")
            ->assertSessionHas('error', 'Move the products in this category before deleting it.');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);

        $categoryQuery = collect($queries)->search(fn ($sql) => str_contains($sql, 'from categories'));
        $productQuery = collect($queries)->search(fn ($sql) => str_contains($sql, 'from products'));
        $this->assertNotFalse($categoryQuery);
        $this->assertNotFalse($productQuery);
        $this->assertLessThan($productQuery, $categoryQuery);
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            $this->assertStringContainsString('for update', $queries[$categoryQuery]);
            $this->assertStringContainsString('for update', $queries[$productQuery]);
        }
    }

    public function test_reference_data_mutations_require_permissions(): void
    {
        $user = $this->userWithPermissions(['inventory.categories.view']);
        $this->actingAs($user);

        $this->get('/inventory/categories')->assertOk();
        $this->get('/inventory/manufacturers')->assertOk();
        $this->post('/inventory/categories', ['name' => 'Forbidden'])->assertForbidden();
        $this->put('/inventory/categories/move', [])->assertForbidden();
        $this->post('/inventory/manufacturers', ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_deep_category_trees_remain_visible_and_support_cycle_detection(): void
    {
        $root = Category::query()->create(['name' => 'Root', 'slug' => 'root']);
        $parent = $root;
        for ($i = 1; $i <= 25; $i++) {
            $parent = Category::query()->create(['name' => "Level $i", 'slug' => "level-$i", 'parent_id' => $parent->id]);
        }
        $this->actingAs($this->superAdmin());

        $this->get('/inventory/categories')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('categories', 26)->where('categories.25.id', $parent->id)->where('categories.25.depth', 25));
        $this->assertCount(26, app(HierarchyOptions::class)->descendantIds(Category::class, $root->id));
        $this->put("/inventory/categories/{$root->id}", ['name' => 'Root', 'parent_id' => $parent->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_moves_are_ordered_scoped_and_reject_stale_or_invalid_requests(): void
    {
        $a = Category::query()->create(['name' => 'A', 'slug' => 'a']);
        $b = Category::query()->create(['name' => 'B', 'slug' => 'b']);
        $child = Category::query()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $a->id]);
        $other = Category::query()->create(['name' => 'Other', 'slug' => 'other', 'parent_id' => $child->id, 'sort_order' => 42]);
        $this->actingAs($this->superAdmin());
        $version = (int) DB::table('category_tree_version')->value('version');

        $this->put('/inventory/categories/move', ['id' => $b->id, 'parent_id' => $a->id, 'before_id' => $child->id, 'version' => $version])
            ->assertSessionHasNoErrors();
        $this->assertSame($a->id, $b->fresh()->parent_id);
        $this->assertSame([$b->id, $child->id], Category::query()->where('parent_id', $a->id)->orderBy('sort_order')->pluck('id')->all());
        $this->assertSame(42, $other->fresh()->sort_order);
        $this->put('/inventory/categories/move', ['id' => $b->id, 'parent_id' => null, 'before_id' => null, 'version' => $version])
            ->assertSessionHasErrors('move');
        $version++;
        $this->put('/inventory/categories/move', ['id' => $a->id, 'parent_id' => $other->id, 'before_id' => null, 'version' => $version])
            ->assertSessionHasErrors('move');
        $this->put('/inventory/categories/move', ['id' => $b->id, 'parent_id' => null, 'before_id' => $child->id, 'version' => $version])
            ->assertSessionHasErrors('move');
        $this->put('/inventory/categories/move', ['id' => $b->id, 'parent_id' => null, 'before_id' => null, 'version' => $version])
            ->assertSessionHasNoErrors();
        $this->assertNull($b->fresh()->parent_id);
    }

    public function test_regular_category_mutation_invalidates_a_stale_move(): void
    {
        $category = Category::query()->create(['name' => 'A', 'slug' => 'a']);
        $this->actingAs($this->superAdmin());
        $version = (int) DB::table('category_tree_version')->value('version');
        $this->post('/inventory/categories', ['name' => 'B'])->assertSessionHasNoErrors();
        $this->put('/inventory/categories/move', ['id' => $category->id, 'parent_id' => null, 'before_id' => null, 'version' => $version])
            ->assertSessionHasErrors('move');
        $this->put("/inventory/categories/{$category->id}", ['name' => 'Changed', 'version' => $version])
            ->assertSessionHasErrors('version');
        $this->assertSame('A', $category->fresh()->name);
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
