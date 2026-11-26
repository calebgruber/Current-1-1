<?php

namespace Database\Factories;

use App\Domain\Jobs\Models\ProductionJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductionJob> */
class ProductionJobFactory extends Factory
{
    protected $model = ProductionJob::class;

    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(3, 30))->startOfDay()->addHours(9);

        return [
            'name' => fake()->sentence(3),
            'stage' => 'draft',
            'prep_starts_at' => $start->copy()->subDays(2),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays(3),
            'returns_at' => $start->copy()->addDays(4),
        ];
    }
}
