<?php

namespace Tests\Unit\Jobs;

use App\Domain\Jobs\Services\PullSheetBuilder;
use PHPUnit\Framework\TestCase;

class PullSheetBuilderTest extends TestCase
{
    private PullSheetBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PullSheetBuilder;
    }

    public function test_spec_example_twenty_required_plus_five_spare_pulls_twenty_five(): void
    {
        $sheet = $this->builder->build([
            ['id' => 1, 'section' => 'Lighting', 'product_id' => 10, 'product' => 'Lustr', 'sku' => 'LUS', 'required' => 20, 'spare' => 5, 'weight_kg' => 12.5],
        ]);

        $line = $sheet['sections'][0]['lines'][0];
        $this->assertSame(20, $line['required']);
        $this->assertSame(5, $line['spare']);
        $this->assertSame(25, $line['pull']);
        $this->assertSame(25, $line['remaining_to_pick']);

        $this->assertSame(20, $sheet['totals']['required']);
        $this->assertSame(5, $sheet['totals']['spare']);
        $this->assertSame(25, $sheet['totals']['pull']);
        $this->assertSame(312.5, $sheet['totals']['weight_kg']);

        $this->assertSame(['product_id' => 10, 'product' => 'Lustr', 'sku' => 'LUS', 'location' => null, 'required' => 20, 'spare' => 5, 'pull' => 25, 'picked' => 0], $sheet['by_product'][0]);
    }

    public function test_accessory_quantities_computed_separately_for_spares(): void
    {
        $this->assertSame(['required' => 20, 'spare' => 5, 'pull' => 25], PullSheetBuilder::accessoryQuantities(1, 20, 5));
        $this->assertSame(['required' => 40, 'spare' => 10, 'pull' => 50], PullSheetBuilder::accessoryQuantities(2, 20, 5));
        // Fractional per-unit rounds up independently: 0.25 × 5 = 1.25 → 2, 0.25 × 1 → 1
        $this->assertSame(['required' => 2, 'spare' => 1, 'pull' => 3], PullSheetBuilder::accessoryQuantities(0.25, 5, 1));
        // Floating point noise must not add a unit: 0.1 × 30 = 3.0000000000000004
        $this->assertSame(['required' => 3, 'spare' => 0, 'pull' => 3], PullSheetBuilder::accessoryQuantities(0.1, 30, 0));
    }

    public function test_children_nested_under_parent_and_sections_grouped(): void
    {
        $sheet = $this->builder->build([
            ['id' => 1, 'section' => 'Lighting', 'product_id' => 10, 'product' => 'Lustr', 'required' => 20, 'spare' => 5],
            ['id' => 2, 'parent_id' => 1, 'section' => 'Lighting', 'product_id' => 11, 'product' => 'Clamp', 'required' => 20, 'spare' => 5],
            ['id' => 3, 'section' => 'Audio', 'product_id' => 12, 'product' => 'Speaker', 'required' => 4],
            ['id' => 4, 'product_id' => 13, 'product' => 'Gaffer tape', 'required' => 2],
        ]);

        $names = array_column($sheet['sections'], 'name');
        $this->assertSame(['Lighting', 'Audio', 'General'], $names);
        $lighting = $sheet['sections'][0]['lines'];
        $this->assertCount(1, $lighting);
        $this->assertSame('Clamp', $lighting[0]['children'][0]['product']);
        $this->assertSame(25, $lighting[0]['children'][0]['pull']);

        // Children count toward totals.
        $this->assertSame(46, $sheet['totals']['required']);
        $this->assertSame(10, $sheet['totals']['spare']);
        $this->assertSame(56, $sheet['totals']['pull']);
        $this->assertSame(4, $sheet['totals']['lines']);
    }

    public function test_orphans_listed_as_top_level(): void
    {
        $sheet = $this->builder->build([
            ['id' => 5, 'parent_id' => 99, 'section' => 'Rigging', 'product_id' => 11, 'product' => 'Clamp', 'required' => 3],
        ]);

        $this->assertSame('Rigging', $sheet['sections'][0]['name']);
        $this->assertSame('Clamp', $sheet['sections'][0]['lines'][0]['product']);
        $this->assertSame([], $sheet['sections'][0]['lines'][0]['children']);
    }

    public function test_progress_counters_and_outstanding(): void
    {
        $sheet = $this->builder->build([
            ['id' => 1, 'product_id' => 10, 'product' => 'Lustr', 'required' => 20, 'spare' => 5, 'picked' => 22, 'checked_out' => 22, 'returned' => 10],
            ['id' => 2, 'product_id' => 10, 'product' => 'Lustr', 'required' => 2, 'spare' => 0, 'picked' => 3, 'checked_out' => 1, 'returned' => 2],
        ]);

        $line = $sheet['sections'][0]['lines'][0];
        $this->assertSame(12, $line['outstanding']);
        $this->assertSame(3, $line['remaining_to_pick']);
        $second = $sheet['sections'][0]['lines'][1];
        $this->assertSame(0, $second['outstanding']);
        $this->assertSame(0, $second['remaining_to_pick']);

        $this->assertSame(25, $sheet['totals']['picked']);
        $this->assertSame(23, $sheet['totals']['checked_out']);
        $this->assertSame(12, $sheet['totals']['returned']);
        $this->assertSame(12, $sheet['totals']['outstanding']);

        // Same product merged in by_product
        $this->assertCount(1, $sheet['by_product']);
        $this->assertSame(22, $sheet['by_product'][0]['required']);
        $this->assertSame(27, $sheet['by_product'][0]['pull']);
        $this->assertSame(25, $sheet['by_product'][0]['picked']);
    }

    public function test_by_product_sorted_by_location_then_name(): void
    {
        $sheet = $this->builder->build([
            ['id' => 1, 'product_id' => 1, 'product' => 'Zeta', 'location' => 'A-01'],
            ['id' => 2, 'product_id' => 2, 'product' => 'Alpha', 'location' => 'B-02'],
            ['id' => 3, 'product_id' => 3, 'product' => 'Beta', 'location' => 'A-01'],
            ['id' => 4, 'product_id' => 4, 'product' => 'Gamma'],
        ]);

        $this->assertSame(['Gamma', 'Beta', 'Zeta', 'Alpha'], array_column($sheet['by_product'], 'product'));
    }

    public function test_weight_uses_pull_quantity_and_rounds(): void
    {
        $sheet = $this->builder->build([
            ['id' => 1, 'product_id' => 1, 'product' => 'A', 'required' => 3, 'spare' => 1, 'weight_kg' => 0.333],
            ['id' => 2, 'product_id' => 2, 'product' => 'B', 'required' => 1, 'weight_kg' => '2.5'],
        ]);
        $this->assertSame(3.83, $sheet['totals']['weight_kg']);
    }

    public function test_empty_input(): void
    {
        $sheet = $this->builder->build([]);
        $this->assertSame([], $sheet['sections']);
        $this->assertSame([], $sheet['by_product']);
        $this->assertSame(0, $sheet['totals']['pull']);
        $this->assertSame(0, $sheet['totals']['lines']);
    }

    public function test_assets_and_notes_passed_through(): void
    {
        $sheet = $this->builder->build([
            ['id' => 1, 'product_id' => 1, 'product' => 'A', 'required' => 1, 'notes' => 'Handle with care', 'assets' => [['id' => 5, 'asset_number' => 'A-1']]],
        ]);
        $line = $sheet['sections'][0]['lines'][0];
        $this->assertSame('Handle with care', $line['notes']);
        $this->assertSame('A-1', $line['assets'][0]['asset_number']);
    }
}
