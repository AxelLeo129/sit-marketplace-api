<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_email(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', Role::CallCenter->value)
            ->assertJsonStructure(['token']);
    }

    public function test_doctor_can_log_in_with_collegiate_number(): void
    {
        $doctor = User::factory()->doctor()->create();

        $this->postJson('/api/login', ['login' => $doctor->collegiate_number, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', Role::Doctor->value);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'incorrecta'])
            ->assertUnauthorized();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])
            ->assertUnauthorized();
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password',
            'role' => Role::Admin->value,
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->travel(config('sanctum.expiration') + 1)->minutes();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_api_errors_are_json_even_without_accept_header(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
