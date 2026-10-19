<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private function callCenter(): User
    {
        return User::factory()->role(Role::CallCenter)->create();
    }

    public function test_call_center_creates_a_customer_and_normalizes_tax_id(): void
    {
        $this->actingAs($this->callCenter())
            ->postJson('/api/customers', ['name' => 'Juan Pérez', 'tax_id' => 'cf', 'phone' => '55551234'])
            ->assertCreated()
            ->assertJsonPath('data.tax_id', 'CF');
    }

    public function test_validates_the_tax_id_format(): void
    {
        $this->actingAs($this->callCenter())
            ->postJson('/api/customers', ['name' => 'Juan', 'tax_id' => 'abc', 'phone' => '1'])
            ->assertJsonValidationErrors('tax_id');

        $this->actingAs($this->callCenter())
            ->postJson('/api/customers', ['name' => 'Juan', 'tax_id' => '1234567-k', 'phone' => '1'])
            ->assertCreated()
            ->assertJsonPath('data.tax_id', '1234567-K');
    }

    public function test_searches_by_phone(): void
    {
        Customer::factory()->create(['phone' => '55551234']);
        Customer::factory()->create(['phone' => '44449999']);

        $this->actingAs($this->callCenter())->getJson('/api/customers?search=5555')->assertJsonCount(1, 'data');
    }

    public function test_only_call_center_and_admin_manage_customers(): void
    {
        foreach ([Role::Doctor, Role::Courier, Role::Billing] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->getJson('/api/customers')->assertForbidden();
        }
    }

    public function test_manages_addresses(): void
    {
        $customer = Customer::factory()->create();
        $agent = $this->callCenter();

        $id = $this->actingAs($agent)
            ->postJson("/api/customers/{$customer->id}/addresses", [
                'type' => 'shipping', 'department' => 'Guatemala', 'municipality' => 'Mixco', 'street' => '5a calle 3-20',
            ])
            ->assertCreated()
            ->assertJsonPath('data.full_address', '5a calle 3-20, Mixco, Guatemala')
            ->json('data.id');

        $this->actingAs($agent)->deleteJson("/api/customers/{$customer->id}/addresses/{$id}")->assertNoContent();
    }

    public function test_address_must_belong_to_the_customer_in_the_url(): void
    {
        $address = CustomerAddress::factory()->create();
        $other = Customer::factory()->create();

        $this->actingAs($this->callCenter())
            ->deleteJson("/api/customers/{$other->id}/addresses/{$address->id}")
            ->assertNotFound();
    }
}
