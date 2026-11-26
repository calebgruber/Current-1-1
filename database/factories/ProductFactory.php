<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'model' => fake()->bothify('M-###'),
            'tracking' => 'serialized',
            'replacement_cost' => fake()->randomFloat(2, 50, 5000),
            'is_active' => true,
        ];
    }

    public function bulk(): static
    {
        return $this->state(fn () => ['tracking' => 'bulk']);
    }
}
