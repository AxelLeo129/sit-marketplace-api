<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Lab;
use App\Models\Presentation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Amoxicilina 500 mg',
            'presentation_id' => Presentation::factory()->create()->id,
            'lab_id' => Lab::factory()->create()->id,
            'price' => 112.5,
            'cost' => 60,
            'stock' => 40,
            'is_active' => true,
            'category_ids' => [Category::factory()->create()->id],
            ...$overrides,
        ];
    }

    public function test_admin_creates_a_product_with_categories(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/products', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Amoxicilina 500 mg')
            ->assertJsonPath('data.cost', '60.00')
            ->assertJsonCount(1, 'data.categories');
    }

    public function test_validates_prices_and_references(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/products', $this->validPayload(['price' => -1, 'cost' => 1.234, 'lab_id' => 999]))
            ->assertJsonValidationErrors(['price', 'cost', 'lab_id']);
    }

    public function test_only_admin_sees_the_cost(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->doctor()->create())
            ->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.cost');
    }

    public function test_index_hides_inactive_products_from_non_admins(): void
    {
        Product::factory()->create(['name' => 'Activo']);
        $inactive = Product::factory()->create(['name' => 'Inactivo', 'is_active' => false]);
        $doctor = User::factory()->doctor()->create();

        $this->actingAs($doctor)->getJson('/api/products?include_inactive=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Activo');

        $this->actingAs($doctor)->getJson("/api/products/{$inactive->id}")->assertNotFound();

        $this->actingAs($this->admin())->getJson('/api/products?include_inactive=1')->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_search_category_and_lab(): void
    {
        $category = Category::factory()->create();
        $match = Product::factory()->create(['name' => 'Ibuprofeno 400 mg']);
        $match->categories()->attach($category);
        Product::factory()->create(['name' => 'Ibuprofeno 800 mg']);
        Product::factory()->create(['name' => 'Loratadina']);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/products?search=ibupro')->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson("/api/products?category_id={$category->id}")->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson("/api/products?lab_id={$match->lab_id}")->assertJsonPath('data.0.id', $match->id);
    }

    public function test_update_replaces_categories(): void
    {
        $product = Product::factory()->create();
        $product->categories()->attach(Category::factory()->create());
        $newCategory = Category::factory()->create();

        $this->actingAs($this->admin())
            ->putJson("/api/products/{$product->id}", $this->validPayload(['category_ids' => [$newCategory->id]]))
            ->assertOk()
            ->assertJsonPath('data.categories.0.id', $newCategory->id)
            ->assertJsonCount(1, 'data.categories');
    }

    public function test_delete_is_soft(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertSoftDeleted($product);
    }

    public function test_admin_uploads_and_replaces_the_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/api/products/{$product->id}/image", ['image' => UploadedFile::fake()->image('foto.png')], ['Accept' => 'application/json'])
            ->assertOk();
        $firstPath = $product->refresh()->image_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($admin)
            ->post("/api/products/{$product->id}/image", ['image' => UploadedFile::fake()->image('otra.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.image_url', Storage::disk('public')->url($product->refresh()->image_path));

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertStringStartsWith('products/', $product->image_path);
    }

    public function test_rejects_files_that_are_not_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->post("/api/products/{$product->id}/image", ['image' => UploadedFile::fake()->create('script.php', 10, 'text/x-php')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('image');
    }
}
