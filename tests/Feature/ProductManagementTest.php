<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Auth\Enums\UserRole;
use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for product management and role-specific catalog access.
 *
 * @author Dhammadeep
 */
final class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_product(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);

        $this->withToken($admin->createToken('product-admin')->plainTextToken)
            ->postJson('/api/products', $this->validProduct())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.sku', 'HOME-BTL-101')
            ->assertJsonPath('data.product.status', 'active');

        $this->assertDatabaseHas('products', [
            'sku' => 'HOME-BTL-101',
            'stock_quantity' => 24,
        ]);
    }

    public function test_customer_cannot_create_a_product(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);

        $this->withToken($customer->createToken('product-customer')->plainTextToken)
            ->postJson('/api/products', $this->validProduct())
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['sku' => 'HOME-BTL-101']);
    }

    public function test_customer_cannot_update_or_delete_a_product(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $product = Product::factory()->create();
        $token = $customer->createToken('product-customer')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/products/'.$product->id, ['name' => 'Unauthorized Change'])
            ->assertForbidden();

        $this->withToken($token)
            ->deleteJson('/api/products/'.$product->id)
            ->assertForbidden();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => $product->name,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_update_a_product(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $product = Product::factory()->create(['name' => 'Old Product Name']);

        $this->withToken($admin->createToken('product-admin')->plainTextToken)
            ->putJson('/api/products/'.$product->id, [
                'name' => 'Updated Product Name',
                'sku' => $product->sku,
                'stock_quantity' => 42,
            ])
            ->assertOk()
            ->assertJsonPath('data.product.name', 'Updated Product Name')
            ->assertJsonPath('data.product.stock_quantity', 42);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
            'stock_quantity' => 42,
        ]);
    }

    public function test_customer_listing_contains_active_products_only(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        Product::factory()->count(2)->create(['status' => ProductStatus::Active]);
        Product::factory()->inactive()->create();

        $this->withToken($customer->createToken('product-customer')->plainTextToken)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(2, 'data.products')
            ->assertJsonPath('data.products.0.status', 'active')
            ->assertJsonPath('data.products.1.status', 'active');
    }

    public function test_customer_can_search_products_by_name(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        Product::factory()->create(['name' => 'Blue Ceramic Mug']);
        Product::factory()->create(['name' => 'Steel Water Bottle']);

        $this->withToken($customer->createToken('product-customer')->plainTextToken)
            ->getJson('/api/products?search=Ceramic')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'Blue Ceramic Mug');
    }

    public function test_customer_can_search_products_by_sku(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        Product::factory()->create(['sku' => 'TECH-CBL-445']);
        Product::factory()->create(['sku' => 'HOME-BTL-992']);

        $this->withToken($customer->createToken('product-customer')->plainTextToken)
            ->getJson('/api/products?search=TECH-CBL')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.sku', 'TECH-CBL-445');
    }

    public function test_product_listing_is_paginated(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        Product::factory()->count(3)->create();

        $this->withToken($customer->createToken('product-customer')->plainTextToken)
            ->getJson('/api/products?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.last_page', 2);
    }

    public function test_admin_can_filter_inactive_products(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        Product::factory()->create(['status' => ProductStatus::Active]);
        Product::factory()->inactive()->create();

        $this->withToken($admin->createToken('product-admin')->plainTextToken)
            ->getJson('/api/products?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.status', 'inactive');
    }

    public function test_customer_cannot_view_an_inactive_product(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $product = Product::factory()->inactive()->create();

        $this->withToken($customer->createToken('product-customer')->plainTextToken)
            ->getJson('/api/products/'.$product->id)
            ->assertNotFound();
    }

    public function test_customer_cannot_filter_for_inactive_products(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);

        $this->withToken($customer->createToken('product-customer')->plainTextToken)
            ->getJson('/api/products?status=inactive')
            ->assertForbidden();
    }

    public function test_admin_deletion_soft_deletes_a_product(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $product = Product::factory()->create();

        $this->withToken($admin->createToken('product-admin')->plainTextToken)
            ->deleteJson('/api/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_soft_deleted_products_are_not_visible_to_customers(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $product = Product::factory()->create();
        $product->delete();

        $this->withToken($customer->createToken('product-customer')->plainTextToken)
            ->getJson('/api/products/'.$product->id)
            ->assertNotFound();
    }

    public function test_admin_normal_routes_do_not_show_soft_deleted_products(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $product = Product::factory()->create();
        $product->delete();

        $this->withToken($admin->createToken('product-admin')->plainTextToken)
            ->getJson('/api/products/'.$product->id)
            ->assertNotFound();
    }

    public function test_invalid_product_input_returns_validation_errors(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);

        $this->withToken($admin->createToken('product-admin')->plainTextToken)
            ->postJson('/api/products', [
                'name' => '',
                'sku' => 'HOME-BTL-101',
                'price' => -1,
                'stock_quantity' => -3,
                'status' => 'pending',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name', 'price', 'stock_quantity', 'status']]);
    }

    public function test_unauthenticated_product_requests_return_unauthorized(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $this->postJson('/api/products', $this->validProduct())->assertUnauthorized();
    }

    private function userWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * @return array{name: string, sku: string, price: string, stock_quantity: int, status: string}
     */
    private function validProduct(): array
    {
        return [
            'name' => 'Stainless Steel Bottle',
            'sku' => 'HOME-BTL-101',
            'price' => '18.50',
            'stock_quantity' => 24,
            'status' => ProductStatus::Active->value,
        ];
    }
}
