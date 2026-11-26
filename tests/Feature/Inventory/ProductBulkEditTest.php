<?php

namespace Tests\Feature\Inventory;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProductBulkEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_edit_updates_only_selected_safe_fields_and_audits_each_change(): void
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio']);
        $first = Product::factory()->bulk()->create(['is_active' => true, 'description' => 'Keep me']);
        $second = Product::factory()->create(['is_active' => true]);
        $other = Product::factory()->create(['is_active' => true]);
        $first->stockLevels()->create(['quantity' => 12, 'quantity_damaged' => 2]);
        $tag = Tag::query()->create(['name' => 'Test', 'slug' => 'test']);
        $first->tags()->attach($tag);
        $first->accessories()->create(['accessory_product_id' => $other->id, 'quantity' => 1, 'type' => 'optional']);
        $user = $this->userWithPermissions(['inventory.products.edit']);

        $this->actingAs($user)->from('/inventory/products?search=audio')
            ->patch('/inventory/products/bulk', ['product_ids' => [$first->id, $second->id], 'changes' => ['category_id' => $category->id, 'is_active' => false]])
            ->assertRedirect('/inventory/products?search=audio')->assertSessionHasNoErrors();

        $this->assertSame($category->id, $first->fresh()->category_id);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
        $this->assertTrue($other->fresh()->is_active);
        $this->assertSame('bulk', $first->fresh()->tracking->value);
        $this->assertSame('Keep me', $first->fresh()->description);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $first->id, 'quantity' => 12, 'quantity_damaged' => 2]);
        $this->assertSame(1, $first->tags()->count());
        $this->assertSame(1, $first->accessories()->count());
        foreach ([$first, $second] as $product) {
            $log = AuditLog::query()->where('auditable_type', $product->getMorphClass())->where('auditable_id', $product->id)->where('event', 'updated')->sole();
            $this->assertSame($user->id, $log->user_id);
            $this->assertEquals(['category_id' => null, 'is_active' => 1], $log->old_values);
            $this->assertEquals(['category_id' => $category->id, 'is_active' => 0], $log->new_values);
        }
    }

    public function test_category_can_be_cleared_explicitly_and_noop_is_not_audited(): void
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio']);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);
        $this->actingAs($this->superAdmin());

        $this->patch('/inventory/products/bulk', ['product_ids' => [$product->id], 'changes' => ['category_id' => null]])
            ->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->category_id);
        $this->assertFalse($product->fresh()->is_active);
        $this->patch('/inventory/products/bulk', ['product_ids' => [$product->id], 'changes' => ['category_id' => null]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $product->auditLogs()->where('event', 'updated')->count());
    }

    public function test_bulk_edit_requires_product_edit_permission(): void
    {
        $product = Product::factory()->create();
        $payload = ['product_ids' => [$product->id], 'changes' => ['is_active' => false]];
        $this->patch('/inventory/products/bulk', $payload)->assertRedirect('/login');
        $this->actingAs($this->userWithPermissions(['inventory.products.view', 'inventory.products.create', 'inventory.products.delete']))
            ->patch('/inventory/products/bulk', $payload)->assertForbidden();
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_invalid_or_unsafe_payloads_are_rejected_without_partial_updates(): void
    {
        $product = Product::factory()->create();
        $deleted = Product::factory()->create();
        $deleted->delete();
        $this->actingAs($this->superAdmin());
        $cases = [
            [['product_ids' => [], 'changes' => ['is_active' => false]], 'product_ids'],
            [['product_ids' => array_fill(0, 101, $product->id), 'changes' => ['is_active' => false]], 'product_ids'],
            [['product_ids' => [$product->id, $product->id], 'changes' => ['is_active' => false]], 'product_ids.1'],
            [['product_ids' => [$product->id, $deleted->id], 'changes' => ['is_active' => false]], 'product_ids.1'],
            [['product_ids' => [$product->id, 999999], 'changes' => ['is_active' => false]], 'product_ids.1'],
            [['product_ids' => [$product->id], 'changes' => []], 'changes'],
            [['product_ids' => [$product->id], 'changes' => ['category_id' => 999999]], 'changes.category_id'],
            [['product_ids' => [$product->id], 'changes' => ['is_active' => 'invalid']], 'changes.is_active'],
            [['product_ids' => [$product->id], 'changes' => ['is_active' => false, 'tracking' => 'bulk']], 'changes'],
            [['product_ids' => [$product->id], 'changes' => ['is_active' => false, 'stock' => []]], 'changes'],
            [['product_ids' => [$product->id], 'changes' => ['is_active' => false], 'stock' => []], 'request'],
        ];
        foreach ($cases as [$payload, $error]) {
            $this->patch('/inventory/products/bulk', $payload)->assertSessionHasErrors($error);
            $this->assertTrue($product->fresh()->is_active);
        }
        $this->assertSame(0, $product->auditLogs()->where('event', 'updated')->count());
    }

    public function test_failure_during_update_rolls_back_all_changes_and_audits(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        Product::updated(function (Product $product) use ($second) {
            if ($product->is($second)) {
                throw new RuntimeException('Simulated failure');
            }
        });
        $this->actingAs($this->superAdmin())->withoutExceptionHandling();

        try {
            $this->patch('/inventory/products/bulk', ['product_ids' => [$first->id, $second->id], 'changes' => ['is_active' => false]]);
            $this->fail('Expected simulated failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure', $exception->getMessage());
        }

        $this->assertTrue($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(0, $first->auditLogs()->where('event', 'updated')->count());
        $this->assertSame(0, $second->auditLogs()->where('event', 'updated')->count());
    }
}
