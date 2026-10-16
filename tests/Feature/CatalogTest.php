<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Lab;
use App\Models\Presentation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Categorías, laboratorios y presentaciones.
 */
class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }

    public function test_any_authenticated_user_can_list_the_catalog(): void
    {
        Category::factory()->create(['name' => 'Pediatría']);
        Lab::factory()->create(['name' => 'Bayer']);
        Presentation::factory()->create(['name' => 'Caja', 'unit' => 'tabletas', 'quantity' => 30]);

        $doctor = User::factory()->doctor()->create();

        $this->actingAs($doctor)->getJson('/api/categories')->assertOk()->assertJsonPath('data.0.name', 'Pediatría');
        $this->actingAs($doctor)->getJson('/api/labs')->assertOk()->assertJsonPath('data.0.name', 'Bayer');
        $this->actingAs($doctor)->getJson('/api/presentations')->assertOk()->assertJsonPath('data.0.label', 'Caja de 30 tabletas');
    }

    public function test_guests_cannot_see_the_catalog(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_admin_manages_categories(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)->postJson('/api/categories', ['name' => 'Cardiología'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin)->putJson("/api/categories/{$id}", ['name' => 'Cardiología pediátrica'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cardiología pediátrica');

        $this->actingAs($admin)->deleteJson("/api/categories/{$id}")->assertNoContent();
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_category_names_are_unique(): void
    {
        Category::factory()->create(['name' => 'Pediatría']);

        $this->actingAs($this->admin())->postJson('/api/categories', ['name' => 'Pediatría'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_other_roles_cannot_modify_the_catalog(): void
    {
        foreach ([Role::Doctor, Role::CallCenter, Role::SalesRep, Role::Billing] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->postJson('/api/labs', ['name' => 'Lab '.$role->value])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('labs', 0);
    }

    public function test_lab_in_use_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $product->delete(); // Aun eliminado, el producto conserva el laboratorio.

        $this->actingAs($this->admin())->deleteJson("/api/labs/{$product->lab_id}")->assertConflict();
        $this->assertDatabaseHas('labs', ['id' => $product->lab_id]);
    }

    public function test_presentation_in_use_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())->deleteJson("/api/presentations/{$product->presentation_id}")->assertConflict();
    }

    public function test_presentation_requires_positive_quantity(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/presentations', ['name' => 'Caja', 'unit' => 'tabletas', 'quantity' => 0])
            ->assertJsonValidationErrors('quantity');
    }
}
