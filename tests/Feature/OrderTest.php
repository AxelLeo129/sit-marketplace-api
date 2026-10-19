<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PrescriptionStatus;
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

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $doctor;

    private User $salesRep;

    private Product $product;

    private Customer $customer;

    private CustomerAddress $billing;

    private CustomerAddress $shipping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::CallCenter)->create();
        $this->salesRep = User::factory()->role(Role::SalesRep)->create();
        $this->doctor = User::factory()->doctor()->create(['sales_rep_id' => $this->salesRep->id]);
        // Precio Q112 con IVA → neto Q100; costo Q60 → margen Q40 por unidad.
        $this->product = Product::factory()->create(['price' => 112, 'cost' => 60, 'stock' => 10]);
        $this->customer = Customer::factory()->create();
        $this->billing = CustomerAddress::factory()->billing()->for($this->customer)->create();
        $this->shipping = CustomerAddress::factory()->for($this->customer)->create();
    }

    private function prescription(?Product $product = null): Prescription
    {
        $prescription = Prescription::factory()->for($this->doctor, 'doctor')->create();
        PrescriptionItem::factory()->for($prescription)->for($product ?? $this->product)->create();

        return $prescription;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Prescription $prescription, array $overrides = []): array
    {
        return [
            'prescription_id' => $prescription->id,
            'customer_id' => $this->customer->id,
            'billing_address_id' => $this->billing->id,
            'shipping_address_id' => $this->shipping->id,
            'payment_method' => 'cash_on_delivery',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            ...$overrides,
        ];
    }

    private function placeOrder(?Prescription $prescription = null, array $overrides = []): Order
    {
        $id = $this->actingAs($this->agent)
            ->postJson('/api/orders', $this->payload($prescription ?? $this->prescription(), $overrides))
            ->assertCreated()
            ->json('data.id');

        return Order::findOrFail($id);
    }

    public function test_call_center_turns_a_prescription_into_an_order(): void
    {
        $prescription = $this->prescription();

        $this->actingAs($this->agent)
            ->postJson('/api/orders', $this->payload($prescription))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_invoice')
            ->assertJsonPath('data.total', '224.00')
            ->assertJsonPath('data.items.0.origin', 'prescribed');

        $this->assertSame(8, $this->product->refresh()->stock);
        $this->assertSame(PrescriptionStatus::Ordered, $prescription->refresh()->status);
    }

    public function test_creates_commissions_for_each_beneficiary(): void
    {
        $order = $this->placeOrder();

        // Margen Q80 (2 unidades): médico 20 %, visitador 2 %, mensajero 0.5 % (sin asignar).
        // El call-center no cobra en la primera compra, así que no se crea su fila.
        $commissions = $order->commissions()->get()->keyBy(fn ($commission) => $commission->role->value);

        $this->assertEqualsCanonicalizing(['doctor', 'sales_rep', 'courier'], $commissions->keys()->all());
        $this->assertSame('16.00', $commissions['doctor']->amount);
        $this->assertSame($this->doctor->id, $commissions['doctor']->user_id);
        $this->assertSame('1.60', $commissions['sales_rep']->amount);
        $this->assertSame($this->salesRep->id, $commissions['sales_rep']->user_id);
        $this->assertNull($commissions['courier']->user_id);
    }

    public function test_products_added_by_call_center_pay_no_doctor_commission(): void
    {
        $extra = Product::factory()->create(['stock' => 5]);

        $order = $this->placeOrder(overrides: ['items' => [['product_id' => $extra->id, 'quantity' => 1]]]);

        $this->assertSame('added_by_call_center', $order->items->first()->origin->value);
        $this->assertFalse($order->commissions()->where('role', 'doctor')->exists());
    }

    public function test_repeat_purchase_lowers_doctor_rate_and_pays_call_center(): void
    {
        $this->placeOrder();
        $second = $this->placeOrder();

        $item = $second->items->first();
        $this->assertSame(2, $item->purchase_number);
        $this->assertSame('15.00', $item->commissions()->where('role', 'doctor')->value('rate'));
        $this->assertSame($this->agent->id, $item->commissions()->where('role', 'call_center')->value('user_id'));
    }

    public function test_client_cannot_set_prices_or_origin(): void
    {
        $order = $this->placeOrder(overrides: [
            'total' => 1,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1, 'origin' => 'added_by_call_center']],
        ]);

        $this->assertSame('112.00', $order->total);
        $this->assertSame('prescribed', $order->items->first()->origin->value);
    }

    public function test_card_payment_requires_card_data_and_applies_the_fee(): void
    {
        $this->actingAs($this->agent)
            ->postJson('/api/orders', $this->payload($this->prescription(), ['payment_method' => 'card']))
            ->assertJsonValidationErrors(['payment_reference', 'cardholder_name', 'card_last_four']);

        $order = $this->placeOrder(overrides: [
            'payment_method' => 'card',
            'payment_reference' => 'AUT-123',
            'cardholder_name' => 'Juan Pérez',
            'card_last_four' => '4242',
        ]);

        $this->assertSame('2.50', $order->card_fee_rate);
        $this->assertSame('5.00', $order->items->first()->card_fee_amount);
    }

    public function test_rejects_full_card_numbers_and_card_data_on_cash(): void
    {
        $this->actingAs($this->agent)
            ->postJson('/api/orders', $this->payload($this->prescription(), [
                'payment_method' => 'card', 'payment_reference' => 'A', 'cardholder_name' => 'J', 'card_last_four' => '4242424242424242',
            ]))
            ->assertJsonValidationErrors('card_last_four');

        $this->actingAs($this->agent)
            ->postJson('/api/orders', $this->payload($this->prescription(), ['card_last_four' => '4242']))
            ->assertJsonValidationErrors('card_last_four');
    }

    public function test_rejects_insufficient_stock_without_side_effects(): void
    {
        $prescription = $this->prescription();

        $this->actingAs($this->agent)
            ->postJson('/api/orders', $this->payload($prescription, ['items' => [['product_id' => $this->product->id, 'quantity' => 11]]]))
            ->assertJsonValidationErrors(['items.0.quantity' => 'Solo hay 10 unidades']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->refresh()->stock);
        $this->assertSame(PrescriptionStatus::Pending, $prescription->refresh()->status);
    }

    public function test_addresses_must_belong_to_the_customer(): void
    {
        $foreign = CustomerAddress::factory()->create();

        $this->actingAs($this->agent)
            ->postJson('/api/orders', $this->payload($this->prescription(), [
                'shipping_address_id' => $foreign->id,
                'billing_address_id' => $this->shipping->id, // Existe, pero es de entrega.
            ]))
            ->assertJsonValidationErrors(['shipping_address_id', 'billing_address_id']);
    }

    public function test_a_prescription_can_only_be_ordered_once(): void
    {
        $prescription = $this->prescription();
        $this->placeOrder($prescription);

        $this->actingAs($this->agent)->postJson('/api/orders', $this->payload($prescription))->assertForbidden();
    }

    public function test_only_call_center_and_admin_create_orders(): void
    {
        $this->actingAs($this->doctor)->postJson('/api/orders', $this->payload($this->prescription()))->assertForbidden();
    }

    public function test_financial_breakdown_depends_on_the_role(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->agent)->getJson("/api/orders/{$order->id}")
            ->assertJsonMissingPath('data.items.0.vat_amount')
            ->assertJsonMissingPath('data.items.0.unit_cost');

        $this->actingAs(User::factory()->role(Role::Billing)->create())->getJson("/api/orders/{$order->id}")
            ->assertJsonPath('data.items.0.vat_amount', '24.00')
            ->assertJsonMissingPath('data.items.0.unit_cost');

        $this->actingAs(User::factory()->role(Role::Admin)->create())->getJson("/api/orders/{$order->id}")
            ->assertJsonPath('data.items.0.gross_margin', '80.00')
            ->assertJsonCount(3, 'data.items.0.commissions');
    }

    public function test_visibility_by_role(): void
    {
        $pending = $this->placeOrder();
        $assigned = $this->placeOrder();
        $courier = User::factory()->role(Role::Courier)->create();
        $assigned->update(['status' => OrderStatus::Assigned, 'courier_id' => $courier->id]);

        $this->actingAs($courier)->getJson('/api/orders')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assigned->id);
        $this->actingAs($courier)->getJson("/api/orders/{$pending->id}")->assertForbidden();

        $this->actingAs(User::factory()->role(Role::Dispatcher)->create())->getJson('/api/orders')
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->doctor)->getJson('/api/orders')->assertForbidden();
    }

    public function test_cancel_restores_stock_and_closes_the_prescription(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->agent)->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(10, $this->product->refresh()->stock);
        $this->assertSame(PrescriptionStatus::Cancelled, $order->prescription->refresh()->status);
    }

    public function test_cancelled_orders_do_not_count_as_previous_purchases(): void
    {
        $first = $this->placeOrder();
        $this->actingAs($this->agent)->postJson("/api/orders/{$first->id}/cancel");

        $this->assertSame(1, $this->placeOrder()->items->first()->purchase_number);
    }

    public function test_assigned_orders_cannot_be_cancelled(): void
    {
        $order = $this->placeOrder();
        $order->update(['status' => OrderStatus::Assigned]);

        $this->actingAs($this->agent)->postJson("/api/orders/{$order->id}/cancel")->assertForbidden();
        $this->assertSame(8, $this->product->refresh()->stock);
    }

    public function test_search_by_order_number_or_customer(): void
    {
        // Teléfono sin los dígitos de los números de pedido, para que la búsqueda por número no coincida con él.
        $this->customer->update(['phone' => '55559999']);
        $order = $this->placeOrder();
        $this->placeOrder();

        $this->actingAs($this->agent)->getJson("/api/orders?search={$order->id}")->assertJsonCount(1, 'data');
        $this->actingAs($this->agent)->getJson("/api/orders?search={$this->customer->phone}")->assertJsonCount(2, 'data');
    }
}
