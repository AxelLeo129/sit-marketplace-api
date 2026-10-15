<?php

namespace Database\Factories;

use App\Enums\PrescriptionStatus;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'doctor_id' => User::factory()->doctor(),
            'patient_name' => fake()->name(),
            'patient_phone' => fake()->numerify('########'),
            'symptoms' => fake()->sentence(),
            'diagnosis' => fake()->sentence(),
            'issued_on' => today(),
            'allows_call_center' => true,
            'status' => PrescriptionStatus::Pending,
        ];
    }
}
