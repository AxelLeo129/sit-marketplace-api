<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DoctorProfile;
use App\Models\Lab;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Presentation;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderFulfillment;
use App\Services\OrderPlacement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios para desarrollo local.
 *
 * Crea un usuario por rol con correo `<rol>@demo.test` y contraseña `password`,
 * un catálogo, 5 recetas abiertas, un cliente y 3 pedidos (2 entregados) para los reportes.
 */
class DemoSeeder extends Seeder
{
    public function run(OrderPlacement $placement, OrderFulfillment $fulfillment): void
    {
        $categories = collect(['Pediatría', 'Gastroenterología', 'Cardiología', 'Dermatología', 'Medicina general'])
            ->map(fn (string $name) => Category::firstOrCreate(['name' => $name]));

        $labs = Lab::factory()->count(4)->create();
        $presentations = Presentation::factory()->count(4)->create();

        // Con existencias suficientes para que los pedidos demo no fallen.
        $products = Product::factory()
            ->count(20)
            ->recycle($labs)
            ->recycle($presentations)
            ->state(fn () => ['stock' => fake()->numberBetween(20, 200)])
            ->create()
            ->each(fn (Product $product) => $product->categories()->attach($categories->random(2)->pluck('id')));

        $salesRep = $this->demoUser(Role::SalesRep);

        $doctor = $this->demoUser(Role::Doctor, [
            'collegiate_number' => '12345',
            'sales_rep_id' => $salesRep->id,
        ]);
        DoctorProfile::factory()->for($doctor)->create();
        $doctor->specialties()->sync($categories->take(2)->pluck('id'));

        $callCenter = $this->demoUser(Role::CallCenter);
        $billing = $this->demoUser(Role::Billing);
        $dispatcher = $this->demoUser(Role::Dispatcher);
        $courier = $this->demoUser(Role::Courier);

        // 5 recetas quedan abiertas para probar el call-center; las otras 3 se convierten en pedidos aquí.
        $prescriptions = $this->prescriptions($doctor, $products, 8);

        // Cliente con dirección de facturación y de entrega, listo para crear pedidos.
        $customer = Customer::factory()->create(['name' => 'Cliente Demo', 'phone' => '55550000']);
        $billingAddress = CustomerAddress::factory()->billing()->for($customer)->create();
        $shippingAddress = CustomerAddress::factory()->for($customer)->create();

        // Pedidos con el flujo real: los 2 primeros se entregan y el último queda pendiente de factura.
        foreach ($prescriptions->slice(5)->values() as $index => $prescription) {
            $order = $placement->place($callCenter, $prescription, [
                'customer_id' => $customer->id,
                'billing_address_id' => $billingAddress->id,
                'shipping_address_id' => $shippingAddress->id,
                'payment_method' => PaymentMethod::CashOnDelivery->value,
                'items' => $prescription->items->pluck('product_id')->unique()
                    ->map(fn (int $productId) => ['product_id' => $productId, 'quantity' => 1])
                    ->values()
                    ->all(),
            ]);

            if ($index < 2) {
                $fulfillment->invoice($billing, $order, ['invoice_number' => "DEMO-{$order->id}"]);
                $fulfillment->assign($dispatcher, $order, $courier);
                $fulfillment->startDelivery($courier, $order);
                $fulfillment->deliver($courier, $order, ['received_by' => $customer->name]);
            }
        }
    }

    /**
     * Recetas del médico demo con 2 productos cada una.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Prescription>
     */
    private function prescriptions(User $doctor, Collection $products, int $count): Collection
    {
        return Prescription::factory()
            ->count($count)
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
