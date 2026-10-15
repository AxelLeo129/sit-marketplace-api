<?php

namespace Database\Factories;

use App\Models\Presentation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presentation>
 */
class PresentationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Caja', 'Frasco', 'Blister', 'Tubo']),
            'unit' => fake()->randomElement(['tabletas', 'cápsulas', 'ml', 'g']),
            'quantity' => fake()->randomElement([10, 20, 30, 60, 120]),
        ];
    }
}
