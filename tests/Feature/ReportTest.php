<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $doctor;

    private User $courier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00');

        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->doctor = User::factory()->doctor()->create([
            'sales_rep_id' => User::factory()->role(Role::SalesRep)->create()->id,
        ]);
        $this->courier = User::factory()->role(Role::Courier)->create();
        // Precio Q112 con IVA → neto Q100; costo Q60 → margen Q40 por unidad.
        $this->product = Product::factory()->create(['price' => 112, 'cost' => 60, 'stock' => 100]);
    }

    /**
     * Crea un pedido de 2 unidades con el endpoint del call-center.
     * Margen Q80: médico Q16, visitador Q1.60, mensajero Q0.40.
     */
    private function placeOrder(): Order
    {
        $prescription = Prescription::factory()->for($this->doctor, 'doctor')->create();
        PrescriptionItem::factory()->for($prescription)->for($this->product)->create();
        $customer = Customer::factory()->create();

        $id = $this->actingAs(User::factory()->role(Role::CallCenter)->create())
            ->postJson('/api/orders', [
                'prescription_id' => $prescription->id,
                'customer_id' => $customer->id,
                'billing_address_id' => CustomerAddress::factory()->billing()->for($customer)->create()->id,
                'shipping_address_id' => CustomerAddress::factory()->for($customer)->create()->id,
                'payment_method' => 'cash_on_delivery',
                'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->json('data.id');

        return Order::findOrFail($id);
    }

    /**
     * Crea un pedido y lo marca como entregado por el mensajero en la fecha indicada.
     */
    private function deliveredOrder(string $deliveredAt): Order
    {
        $order = $this->placeOrder();
        $order->update(['status' => OrderStatus::Delivered, 'courier_id' => $this->courier->id, 'delivered_at' => $deliveredAt]);
        Commission::whereIn('order_item_id', $order->items()->select('id'))
            ->where('role', Role::Courier)
            ->update(['user_id' => $this->courier->id]);

        return $order;
    }

    public function test_sales_report_counts_only_orders_delivered_in_the_period(): void
    {
        $this->deliveredOrder('2026-10-05 15:00');
        $this->deliveredOrder('2026-09-30 15:00'); // Mes anterior: fuera del período por defecto.
        $this->placeOrder(); // Sin entregar: no es venta.

        $this->actingAs($this->admin)->getJson('/api/reports/sales')
            ->assertOk()
            ->assertJsonPath('period', ['from' => '2026-10-01', 'to' => '2026-10-20'])
            ->assertJsonPath('summary.orders_count', 1)
            ->assertJsonPath('summary.units', 2)
            ->assertJsonPath('summary.sales', '224.00')
            ->assertJsonPath('summary.vat', '24.00')
            ->assertJsonPath('summary.cost', '120.00')
            ->assertJsonPath('summary.gross_margin', '80.00')
            ->assertJsonPath('summary.commissions', '18.00')
            ->assertJsonPath('summary.profit', '62.00')
            ->assertJsonPath('top_products.0.name', $this->product->name)
            // Por estado cuenta la fecha de creación: los 3 pedidos se crearon este mes.
            ->assertJsonPath('orders_by_status.delivered', 2)
            ->assertJsonPath('orders_by_status.pending_invoice', 1);
    }

    public function test_sales_series_groups_by_day_or_month(): void
    {
        $this->deliveredOrder('2026-09-10 09:00');
        $this->deliveredOrder('2026-10-05 09:00');
        $this->deliveredOrder('2026-10-05 18:00');

        $this->actingAs($this->admin)->getJson('/api/reports/sales?from=2026-09-01&to=2026-10-31')
            ->assertJsonPath('series.0.period', '2026-09-10')
            ->assertJsonPath('series.1.period', '2026-10-05')
            ->assertJsonPath('series.1.orders_count', 2);

        $this->actingAs($this->admin)->getJson('/api/reports/sales?from=2026-09-01&to=2026-10-31&group_by=month')
            ->assertJsonCount(2, 'series')
            ->assertJsonPath('series.1.period', '2026-10')
            ->assertJsonPath('series.1.sales', '448.00');
    }

    public function test_commissions_report_groups_by_beneficiary(): void
    {
        $this->deliveredOrder('2026-10-05 15:00');
        $this->deliveredOrder('2026-10-06 15:00');

        $response = $this->actingAs($this->admin)->getJson('/api/reports/commissions')->assertOk();

        // Médico: Q16 por pedido (clientes distintos, ambos son primera compra).
        $doctor = collect($response->json('data'))->firstWhere('role', 'doctor');
        $this->assertSame('32.00', $doctor['amount']);
        $this->assertSame(2, $doctor['orders_count']);

        $this->actingAs($this->admin)->getJson('/api/reports/commissions?role=courier')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->courier->id)
            ->assertJsonPath('total', '0.80');
    }

    public function test_beneficiary_sees_only_their_own_commissions(): void
    {
        $order = $this->deliveredOrder('2026-10-05 15:00');
        $this->placeOrder(); // Sin entregar: aún no cuenta.

        $this->actingAs($this->doctor)->getJson('/api/reports/my-commissions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', '16.00')
            ->assertJsonPath('data.0.order_id', $order->id)
            ->assertJsonPath('data.0.amount', '16.00')
            ->assertJsonMissingPath('data.0.gross_margin');

        $this->actingAs($this->courier)->getJson('/api/reports/my-commissions')
            ->assertJsonPath('total', '0.40');
    }

    public function test_only_admin_sees_sales_and_all_commissions(): void
    {
        foreach ([$this->doctor, $this->courier, User::factory()->role(Role::Billing)->create()] as $user) {
            $this->actingAs($user)->getJson('/api/reports/sales')->assertForbidden();
            $this->actingAs($user)->getJson('/api/reports/commissions')->assertForbidden();
        }
    }

    public function test_only_commission_earners_have_my_commissions(): void
    {
        $this->actingAs($this->admin)->getJson('/api/reports/my-commissions')->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Dispatcher)->create())->getJson('/api/reports/my-commissions')->assertForbidden();
    }

    public function test_validates_the_period(): void
    {
        $this->actingAs($this->admin)->getJson('/api/reports/sales?from=2026-10-10&to=2026-10-01')
            ->assertJsonValidationErrors('to');
    }
}
