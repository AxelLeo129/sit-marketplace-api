<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function doctorPayload(array $overrides = []): array
    {
        return [
            'name' => 'Dra. Ana López',
            'email' => 'ana@example.com',
            'password' => 'clave-segura-1',
            'role' => Role::Doctor->value,
            'collegiate_number' => '45678',
            'doctor_profile' => ['clinic_name' => 'Clínica Ana'],
            'category_ids' => [Category::factory()->create()->id],
            ...$overrides,
        ];
    }

    public function test_admin_creates_a_doctor_with_profile_and_specialties(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/users', $this->doctorPayload())
            ->assertCreated()
            ->assertJsonPath('data.role', 'doctor')
            ->assertJsonPath('data.doctor_profile.clinic_name', 'Clínica Ana')
            ->assertJsonCount(1, 'data.specialties');

        $this->postJson('/api/login', ['login' => '45678', 'password' => 'clave-segura-1'])->assertOk();
    }

    public function test_doctor_requires_collegiate_number(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/users', $this->doctorPayload(['collegiate_number' => null]))
            ->assertJsonValidationErrors('collegiate_number');
    }

    public function test_doctor_fields_are_rejected_for_other_roles(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/users', $this->doctorPayload(['role' => Role::CallCenter->value]))
            ->assertJsonValidationErrors(['collegiate_number', 'doctor_profile', 'category_ids']);
    }

    public function test_password_must_meet_the_policy(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/users', $this->doctorPayload(['password' => '123']))
            ->assertJsonValidationErrors('password');
    }

    public function test_sales_rep_creates_doctors_assigned_to_themselves_only(): void
    {
        $salesRep = User::factory()->role(Role::SalesRep)->create();

        // Aunque pida crear un administrador, se crea un médico asignado a él.
        $this->actingAs($salesRep)
            ->postJson('/api/users', $this->doctorPayload(['role' => Role::Admin->value, 'sales_rep_id' => null]))
            ->assertCreated()
            ->assertJsonPath('data.role', 'doctor')
            ->assertJsonPath('data.sales_rep.id', $salesRep->id);
    }

    public function test_sales_rep_lists_and_edits_only_their_doctors(): void
    {
        $salesRep = User::factory()->role(Role::SalesRep)->create();
        $ownDoctor = User::factory()->doctor()->create(['sales_rep_id' => $salesRep->id]);
        $otherDoctor = User::factory()->doctor()->create();

        $this->actingAs($salesRep)->getJson('/api/users')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownDoctor->id);

        $this->actingAs($salesRep)->patchJson("/api/users/{$ownDoctor->id}", ['phone' => '55554444'])->assertOk();
        $this->actingAs($salesRep)->patchJson("/api/users/{$otherDoctor->id}", ['phone' => '55554444'])->assertForbidden();
        $this->actingAs($salesRep)->deleteJson("/api/users/{$ownDoctor->id}")->assertForbidden();
    }

    public function test_other_roles_cannot_manage_users(): void
    {
        foreach ([Role::Doctor, Role::CallCenter, Role::Courier] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->getJson('/api/users')->assertForbidden();
        }
    }

    public function test_admin_filters_users_by_role_and_search(): void
    {
        $admin = $this->admin();
        User::factory()->role(Role::Courier)->create(['name' => 'Mario Mensajero']);
        User::factory()->role(Role::Courier)->create(['name' => 'Luis Mensajero']);

        $this->actingAs($admin)->getJson('/api/users?role=courier')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/users?search=mario')->assertJsonCount(1, 'data');
    }

    public function test_admin_cannot_change_own_role_or_deactivate_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/users/{$admin->id}", ['role' => Role::Courier->value, 'is_active' => false])
            ->assertJsonValidationErrors(['role', 'is_active']);

        $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}")->assertForbidden();
    }

    public function test_deactivating_a_user_revokes_their_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->actingAs($this->admin())->patchJson("/api/users/{$user->id}", ['is_active' => false])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_a_deactivated_user_with_a_live_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_delete_is_soft_and_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('test');

        $this->actingAs($this->admin())->deleteJson("/api/users/{$user->id}")->assertNoContent();

        $this->assertSoftDeleted($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
