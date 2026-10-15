<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('########'),
            'role' => Role::CallCenter,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Usuario con el rol indicado.
     */
    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    /**
     * Médico con número de colegiado.
     */
    public function doctor(): static
    {
        return $this->state(fn () => [
            'role' => Role::Doctor,
            'collegiate_number' => (string) fake()->unique()->numberBetween(1000, 99999),
        ]);
    }

    /**
     * Usuario desactivado (no puede iniciar sesión).
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
