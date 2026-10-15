<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Category;
use App\Models\DoctorProfile;
use App\Models\Lab;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Presentation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios para desarrollo local.
 *
 * Crea un usuario por rol con correo `<rol>@demo.test` y contraseña `password`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect(['Pediatría', 'Gastroenterología', 'Cardiología', 'Dermatología', 'Medicina general'])
            ->map(fn (string $name) => Category::firstOrCreate(['name' => $name]));

        $labs = Lab::factory()->count(4)->create();
        $presentations = Presentation::factory()->count(4)->create();

        $products = Product::factory()
            ->count(20)
            ->recycle($labs)
            ->recycle($presentations)
            ->create()
            ->each(fn (Product $product) => $product->categories()->attach($categories->random(2)->pluck('id')));

        $salesRep = $this->demoUser(Role::SalesRep);

        $doctor = $this->demoUser(Role::Doctor, [
            'collegiate_number' => '12345',
            'sales_rep_id' => $salesRep->id,
        ]);
        DoctorProfile::factory()->for($doctor)->create();
        $doctor->specialties()->sync($categories->take(2)->pluck('id'));

        foreach ([Role::CallCenter, Role::Dispatcher, Role::Courier, Role::Billing] as $role) {
            $this->demoUser($role);
        }

        Prescription::factory()
            ->count(5)
            ->for($doctor, 'doctor')
            ->create()
            ->each(fn (Prescription $prescription) => PrescriptionItem::factory()
                ->count(2)
                ->for($prescription)
                ->recycle($products)
                ->create());
    }

    /**
     * Crea (o reutiliza) el usuario demo de un rol.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function demoUser(Role $role, array $attributes = []): User
    {
        $email = "{$role->value}@demo.test";

        return User::firstOrCreate(
            ['email' => $email],
            User::factory()->role($role)->raw(['email' => $email, 'name' => "Demo {$role->label()}", ...$attributes]),
        );
    }
}
