<?php

namespace Tests\Feature\Search;

use App\Domain\Inventory\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_palette_query_returns_permitted_results(): void
    {
        Product::factory()->create(['name' => 'ETC Lustr Series 3']);

        $this->actingAs($this->userWithPermissions(['inventory.products.view']))
            ->getJson(route('search.query', ['q' => 'lustr']))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'product')
            ->assertJsonPath('results.0.title', 'ETC Lustr Series 3');
    }

    public function test_results_are_filtered_by_permission(): void
    {
        Product::factory()->create(['name' => 'ETC Lustr Series 3']);

        $this->actingAs($this->userWithPermissions([]))
            ->getJson(route('search.query', ['q' => 'lustr']))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_unknown_type_filter_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson(route('search.query', ['q' => 'x', 'types' => ['secrets']]))
            ->assertUnprocessable();
    }

    public function test_full_page_search_renders(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('search.index', ['q' => 'lustr']))
            ->assertOk()
            ->assertViewIs('search.index')
            ->assertViewHas('q', 'lustr')
            ->assertSee('ETC Lustr Series 3');
    }
}
