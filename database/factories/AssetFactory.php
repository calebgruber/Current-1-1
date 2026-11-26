<?php

namespace Database\Factories;

use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'asset_number' => strtoupper(fake()->unique()->bothify('A-#####')),
            'serial_number' => strtoupper(fake()->unique()->bothify('SN########')),
            'barcode' => fake()->unique()->numerify('2000########'),
            'status' => 'available',
            'condition' => 'good',
        ];
    }
}
