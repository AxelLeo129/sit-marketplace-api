<?php

namespace Database\Factories;

use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorProfile>
 */
class DoctorProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->doctor(),
            'clinic_name' => 'Clínica '.fake()->lastName(),
            'clinic_phone' => fake()->numerify('########'),
            'clinic_address' => fake()->address(),
            'keeps_clinical_record' => true,
            'show_alerts' => true,
        ];
    }
}
