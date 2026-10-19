<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private User $billing;

    private User $dispatcher;

    private User $courier;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->billing = User::factory()->role(Role::Billing)->create();
        $this->dispatcher = User::factory()->role(Role::Dispatcher)->create();
        $this->courier = User::factory()->role(Role::Courier)->create();
        $this->order = $this->placeOrder();
    }

    /**
     * Crea un pedido real con el endpoint del call-center.
     */
    private function placeOrder(): Order
    {
        $product = Product::factory()->create(['price' => 112, 'cost' => 60, 'stock' => 10]);
        $prescription = Prescription::factory()->for(User::factory()->doctor(), 'doctor')->create();
        PrescriptionItem::factory()->for($prescription)->for($product)->create();
        $customer = Customer::factory()->create(['tax_id' => '1234567-8']);

        $id = $this->actingAs(User::factory()->role(Role::CallCenter)->create())
            ->postJson('/api/orders', [
                'prescription_id' => $prescription->id,
                'customer_id' => $customer->id,
                'billing_address_id' => CustomerAddress::factory()->billing()->for($customer)->create()->id,
                'shipping_address_id' => CustomerAddress::factory()->for($customer)->create()->id,
                'payment_method' => 'cash_on_delivery',
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->json('data.id');

        return Order::findOrFail($id);
    }

    private function invoice(?Order $order = null, array $data = ['invoice_number' => 'A-100']): void
    {
        $order ??= $this->order;
        $this->actingAs($this->billing)->postJson("/api/orders/{$order->id}/invoice", $data)->assertOk();
    }

    private function assign(?User $courier = null): void
    {
        $this->actingAs($this->dispatcher)
            ->postJson("/api/orders/{$this->order->id}/assign", ['courier_id' => ($courier ?? $this->courier)->id])
            ->assertOk();
    }

    private function startDelivery(): void
    {
        $this->actingAs($this->courier)->postJson("/api/orders/{$this->order->id}/start-delivery")->assertOk();
    }

    public function test_billing_invoices_with_the_customer_tax_id_by_default(): void
    {
        $this->actingAs($this->billing)
            ->postJson("/api/orders/{$this->order->id}/invoice", ['invoice_number' => 'A-100'])
            ->assertOk()
            ->assertJsonPath('data.status', 'invoiced')
            ->assertJsonPath('data.invoice.number', 'A-100')
            ->assertJsonPath('data.invoice.tax_id', '1234567-8')
            ->assertJsonPath('data.invoice.invoiced_by.id', $this->billing->id);
    }

    public function test_invoice_number_is_unique_and_tax_id_is_validated(): void
    {
        $this->invoice();
        $other = $this->placeOrder();

        $this->actingAs($this->billing)
            ->postJson("/api/orders/{$other->id}/invoice", ['invoice_number' => 'A-100', 'invoice_tax_id' => 'abc'])
            ->assertJsonValidationErrors(['invoice_number', 'invoice_tax_id']);
    }

    public function test_invoice_date_cannot_be_in_the_future(): void
    {
        $this->actingAs($this->billing)
            ->postJson("/api/orders/{$this->order->id}/invoice", ['invoice_number' => 'A-1', 'invoiced_at' => now()->addDay()->toDateString()])
            ->assertJsonValidationErrors('invoiced_at');
    }

    public function test_only_billing_and_admin_invoice_and_only_once(): void
    {
        $this->actingAs($this->dispatcher)
            ->postJson("/api/orders/{$this->order->id}/invoice", ['invoice_number' => 'A-1'])
            ->assertForbidden();

        $this->invoice();

        $this->actingAs($this->billing)
            ->postJson("/api/orders/{$this->order->id}/invoice", ['invoice_number' => 'A-2'])
            ->assertForbidden();
    }

    public function test_dispatcher_assigns_a_courier_who_gets_the_courier_commission(): void
    {
        $this->invoice();

        $this->actingAs($this->dispatcher)
            ->postJson("/api/orders/{$this->order->id}/assign", ['courier_id' => $this->courier->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.delivery.courier.id', $this->courier->id);

        $this->assertSame($this->courier->id, $this->order->commissions()->where('role', 'courier')->value('user_id'));
    }

    public function test_cannot_assign_before_invoicing(): void
    {
        $this->actingAs($this->dispatcher)
            ->postJson("/api/orders/{$this->order->id}/assign", ['courier_id' => $this->courier->id])
            ->assertForbidden();
    }

    public function test_only_active_couriers_can_be_assigned(): void
    {
        $this->invoice();
        $inactive = User::factory()->role(Role::Courier)->create(['is_active' => false]);

        foreach ([$inactive, $this->billing] as $user) {
            $this->actingAs($this->dispatcher)
                ->postJson("/api/orders/{$this->order->id}/assign", ['courier_id' => $user->id])
                ->assertJsonValidationErrors('courier_id');
        }
    }

    public function test_reassigning_moves_the_commission_and_the_visibility(): void
    {
        $this->invoice();
        $this->assign();
        $other = User::factory()->role(Role::Courier)->create();
        $this->assign($other);

        $this->assertSame($other->id, $this->order->commissions()->where('role', 'courier')->value('user_id'));
        $this->actingAs($this->courier)->getJson("/api/orders/{$this->order->id}")->assertForbidden();
    }

    public function test_courier_completes_the_delivery(): void
    {
        $this->invoice();
        $this->assign();

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/start-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/deliver", ['received_by' => 'María López'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.delivery.received_by', 'María López');

        $this->assertNotNull($this->order->refresh()->delivered_at);
    }

    public function test_only_the_assigned_courier_moves_the_order(): void
    {
        $this->invoice();
        $this->assign();

        $this->actingAs(User::factory()->role(Role::Courier)->create())
            ->postJson("/api/orders/{$this->order->id}/start-delivery")
            ->assertForbidden();
    }

    public function test_cannot_deliver_without_starting_the_route(): void
    {
        $this->invoice();
        $this->assign();

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/deliver", ['received_by' => 'María'])
            ->assertForbidden();
    }

    public function test_failed_delivery_requires_a_reason_and_can_be_reassigned(): void
    {
        $this->invoice();
        $this->assign();
        $this->startDelivery();

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/fail-delivery")
            ->assertJsonValidationErrors('notes');

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/fail-delivery", ['notes' => 'Nadie en casa'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivery_failed');

        $this->assign();
        $this->assertSame(OrderStatus::Assigned, $this->order->refresh()->status);
        $this->assertNull($this->order->dispatched_at);
    }

    public function test_failed_delivery_can_be_cancelled_and_restores_stock(): void
    {
        $this->invoice();
        $this->assign();
        $this->startDelivery();
        $this->actingAs($this->courier)->postJson("/api/orders/{$this->order->id}/fail-delivery", ['notes' => 'Dirección incorrecta']);

        $this->actingAs(User::factory()->role(Role::CallCenter)->create())
            ->postJson("/api/orders/{$this->order->id}/cancel")
            ->assertOk();

        $this->assertSame(10, $this->order->items->first()->product->refresh()->stock);
    }

    public function test_exposes_the_actions_available_to_the_user(): void
    {
        $this->actingAs($this->billing)->getJson("/api/orders/{$this->order->id}")
            ->assertJsonPath('data.can.invoice', true)
            ->assertJsonPath('data.can.assign', false);

        $this->invoice();

        $this->actingAs($this->dispatcher)->getJson("/api/orders/{$this->order->id}")
            ->assertJsonPath('data.can.assign', true)
            ->assertJsonPath('data.can.invoice', false);
    }

    public function test_dispatcher_lists_active_couriers_with_their_workload(): void
    {
        $this->invoice();
        $this->assign();
        User::factory()->role(Role::Courier)->create(['is_active' => false]);

        $this->actingAs($this->dispatcher)->getJson('/api/couriers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pending_deliveries_count', 1);

        $this->actingAs($this->courier)->getJson('/api/couriers')->assertForbidden();
    }
}
