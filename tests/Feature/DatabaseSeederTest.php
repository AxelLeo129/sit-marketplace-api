<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Prescription;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_created_from_configuration(): void
    {
        config([
            'marketplace.admin.email' => 'admin@sit-marketplace.test',
            'marketplace.admin.password' => 'secreto-largo',
        ]);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(Role::Admin, User::firstWhere('email', 'admin@sit-marketplace.test')->role);
    }

    public function test_admin_is_skipped_without_credentials(): void
    {
        config(['marketplace.admin.email' => null, 'marketplace.admin.password' => null]);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_seeder_creates_one_user_per_role_and_prescriptions(): void
    {
        $this->seed(DemoSeeder::class);

        foreach ([Role::Doctor, Role::SalesRep, Role::CallCenter, Role::Dispatcher, Role::Courier, Role::Billing] as $role) {
            $this->assertDatabaseHas('users', ['email' => "{$role->value}@demo.test", 'role' => $role->value]);
        }

        $doctor = User::firstWhere('email', 'doctor@demo.test');
        $this->assertNotNull($doctor->doctorProfile);
        $this->assertSame(5, Prescription::where('doctor_id', $doctor->id)->count());
    }
}
