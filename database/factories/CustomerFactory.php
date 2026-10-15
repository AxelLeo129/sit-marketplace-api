<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'tax_id' => 'CF',
            'phone' => fake()->unique()->numerify('########'),
            'email' => fake()->safeEmail(),
        ];
    }
}
