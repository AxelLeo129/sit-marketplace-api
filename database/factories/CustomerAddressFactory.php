<?php

namespace Database\Factories;

use App\Enums\AddressType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => AddressType::Shipping,
            'country' => 'Guatemala',
            'department' => 'Guatemala',
            'municipality' => 'Guatemala',
            'street' => fake()->streetAddress(),
            'neighborhood' => 'Zona '.fake()->numberBetween(1, 21),
        ];
    }

    public function billing(): static
    {
        return $this->state(fn () => ['type' => AddressType::Billing]);
    }
}
