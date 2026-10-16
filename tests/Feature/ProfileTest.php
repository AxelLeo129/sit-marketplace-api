<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_updates_own_data_but_not_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/me', ['name' => 'Nuevo Nombre', 'role' => Role::Admin->value, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nuevo Nombre')
            ->assertJsonPath('data.role', Role::CallCenter->value);

        $this->assertTrue($user->refresh()->is_active);
    }

    public function test_doctor_updates_clinic_and_specialties(): void
    {
        $doctor = User::factory()->doctor()->create();
        $category = Category::factory()->create();

        $this->actingAs($doctor)
            ->patchJson('/api/me', [
                'doctor_profile' => ['clinic_name' => 'Clínica Central', 'show_alerts' => false],
                'category_ids' => [$category->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.doctor_profile.clinic_name', 'Clínica Central')
            ->assertJsonPath('data.doctor_profile.show_alerts', false)
            ->assertJsonPath('data.specialties.0.id', $category->id);
    }

    public function test_non_doctors_cannot_send_clinic_data(): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson('/api/me', ['doctor_profile' => ['clinic_name' => 'X']])
            ->assertJsonValidationErrors('doctor_profile');
    }

    public function test_password_change_requires_current_password_and_keeps_current_session(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('actual')->plainTextToken;
        $user->createToken('otro-dispositivo');

        $this->withToken($currentToken)
            ->putJson('/api/me/password', ['current_password' => 'incorrecta', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'])
            ->assertJsonValidationErrors('current_password');

        $this->withToken($currentToken)
            ->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'])
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'nueva-clave-1'])->assertOk();
    }

    public function test_user_uploads_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/api/me/avatar', ['image' => UploadedFile::fake()->image('yo.jpg')], ['Accept' => 'application/json'])
            ->assertOk();

        Storage::disk('public')->assertExists($user->refresh()->avatar_path);
    }

    public function test_only_doctors_upload_a_clinic_logo(): void
    {
        Storage::fake('public');
        $image = ['image' => UploadedFile::fake()->image('logo.png')];

        $this->actingAs(User::factory()->create())
            ->post('/api/me/clinic-logo', $image, ['Accept' => 'application/json'])
            ->assertForbidden();

        $doctor = User::factory()->doctor()->create();
        $this->actingAs($doctor)
            ->post('/api/me/clinic-logo', $image, ['Accept' => 'application/json'])
            ->assertOk();

        Storage::disk('public')->assertExists($doctor->doctorProfile->clinic_logo_path);
    }
}
