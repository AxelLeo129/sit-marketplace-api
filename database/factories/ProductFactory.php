<?php

namespace Database\Factories;

use App\Models\Lab;
use App\Models\Presentation;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 10, 300);

        return [
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->randomElement([5, 10, 20, 50, 100, 500]).' mg',
            'description' => fake()->sentence(),
            'presentation_id' => Presentation::factory(),
            'lab_id' => Lab::factory(),
            'price' => round($cost * 1.6, 2),
            'cost' => $cost,
            'stock' => fake()->numberBetween(0, 200),
            'warehouse' => fake()->randomElement(['Central', 'Zona 10', 'Mixco']),
            'is_active' => true,
            'is_regulated' => false,
        ];
    }
}
