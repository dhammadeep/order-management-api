<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Auth\Enums\UserRole;
use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Feature coverage for order creation, inventory safety, and access rules.
 *
 * @author Dhammadeep
 */
final class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_place_an_order_with_server_calculated_totals_and_snapshots(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $first = $this->product(['name' => 'Original Bottle', 'sku' => 'BOTTLE-01', 'price' => '18.50', 'stock_quantity' => 5]);
        $second = $this->product(['name' => 'Steel Cup', 'sku' => 'CUP-02', 'price' => '5.25', 'stock_quantity' => 4]);

        $response = $this->withToken($customer->createToken('order-customer')->plainTextToken)
            ->postJson('/api/orders', [
                'total_amount' => '0.01',
                'items' => [
                    ['product_id' => $first->id, 'quantity' => 2, 'unit_price' => '0.01'],
                    ['product_id' => $second->id, 'quantity' => 1, 'unit_price' => '0.01'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.total_amount', '42.25')
            ->assertJsonPath('data.order.status', OrderStatus::Pending->value)
            ->assertJsonCount(2, 'data.order.items')
            ->assertJsonPath('data.order.items.0.product_name', 'Original Bottle')
            ->assertJsonPath('data.order.items.0.product_sku', 'BOTTLE-01')
            ->assertJsonPath('data.order.items.0.unit_price', '18.50')
            ->assertJsonPath('data.order.items.0.quantity', 2)
            ->assertJsonPath('data.order.items.0.line_total', '37.00');

        $this->assertDatabaseHas('products', ['id' => $first->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('products', ['id' => $second->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('orders', ['user_id' => $customer->id, 'total_amount' => '42.25']);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $first->id,
            'product_name' => 'Original Bottle',
            'product_sku' => 'BOTTLE-01',
            'unit_price' => '18.50',
            'quantity' => 2,
            'line_total' => '37.00',
        ]);

        $first->update(['name' => 'Renamed Bottle', 'sku' => 'BOTTLE-NEW', 'price' => '99.99']);
        $this->getOrder($customer, (int) $response->json('data.order.id'))
            ->assertOk()
            ->assertJsonPath('data.order.items.0.product_name', 'Original Bottle')
            ->assertJsonPath('data.order.items.0.product_sku', 'BOTTLE-01')
            ->assertJsonPath('data.order.items.0.unit_price', '18.50');
    }

    public function test_insufficient_stock_returns_422_without_partial_order_or_stock_reduction(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $available = $this->product(['stock_quantity' => 5]);
        $short = $this->product(['stock_quantity' => 1]);

        $this->withToken($customer->createToken('order-customer')->plainTextToken)
            ->postJson('/api/orders', ['items' => [
                ['product_id' => $available->id, 'quantity' => 3],
                ['product_id' => $short->id, 'quantity' => 2],
            ]])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['items']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseHas('products', ['id' => $available->id, 'stock_quantity' => 5]);
        $this->assertDatabaseHas('products', ['id' => $short->id, 'stock_quantity' => 1]);
    }

    public function test_inactive_products_cannot_be_ordered(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $product = $this->product(['status' => ProductStatus::Inactive]);

        $this->postOrder($customer, [['product_id' => $product->id, 'quantity' => 1]])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
    }

    public function test_soft_deleted_products_cannot_be_ordered(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $product = $this->product();
        $product->delete();

        $this->postOrder($customer, [['product_id' => $product->id, 'quantity' => 1]])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_product_ids_are_rejected_before_order_processing(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $product = $this->product(['stock_quantity' => 4]);

        $this->postOrder($customer, [
            ['product_id' => $product->id, 'quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 3],
        ])->assertUnprocessable()->assertJsonStructure(['errors' => ['items.1.product_id']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 4]);
    }

    public function test_invalid_product_id_returns_validation_error(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);

        $this->postOrder($customer, [['product_id' => 987654, 'quantity' => 1]])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['items.0.product_id']]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customer_can_list_only_their_own_orders(): void
    {
        $owner = $this->userWithRole(UserRole::Customer);
        $other = $this->userWithRole(UserRole::Customer);
        $ownerOrder = Order::factory()->create(['user_id' => $owner->id]);
        Order::factory()->create(['user_id' => $other->id]);

        $this->withToken($owner->createToken('order-owner')->plainTextToken)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.id', $ownerOrder->id)
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = $this->userWithRole(UserRole::Customer);
        $other = $this->userWithRole(UserRole::Customer);
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $this->withToken($other->createToken('order-other')->plainTextToken)
            ->getJson('/api/orders/'.$order->id)
            ->assertForbidden();
    }

    public function test_admin_can_list_and_view_all_orders(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $customer = $this->userWithRole(UserRole::Customer);
        $order = $this->createOrder($customer);

        $token = $admin->createToken('order-admin')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.id', $order->id);

        $this->withToken($token)
            ->getJson('/api/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.order.id', $order->id);
    }

    public function test_admin_cannot_place_a_customer_order(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $product = $this->product();

        $this->postOrder($admin, [['product_id' => $product->id, 'quantity' => 1]])
            ->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
    }

    public function test_order_routes_require_authentication(): void
    {
        $this->postJson('/api/orders', ['items' => [['product_id' => 1, 'quantity' => 1]]])->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/orders/1')->assertUnauthorized();
    }

    public function test_missing_order_returns_404(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);

        $this->withToken($customer->createToken('order-customer')->plainTextToken)
            ->getJson('/api/orders/99999')
            ->assertNotFound();
    }

    public function test_listing_is_paginated_and_order_items_are_eager_loaded(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);
        $first = $this->createOrder($customer);
        $second = $this->createOrder($customer);
        $third = $this->createOrder($customer);

        $this->withToken($customer->createToken('order-customer')->plainTextToken)
            ->getJson('/api/orders?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.id', $first->id)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonStructure(['data' => ['orders' => [['items']]]]);

        $this->assertNotSame($second->id, $third->id);
    }

    private function userWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => '10.00',
            'stock_quantity' => 10,
            'status' => ProductStatus::Active,
        ], $attributes));
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    private function postOrder(User $user, array $items): TestResponse
    {
        return $this->withToken($user->createToken('order-test')->plainTextToken)
            ->postJson('/api/orders', ['items' => $items]);
    }

    private function getOrder(User $user, int $id): TestResponse
    {
        return $this->withToken($user->createToken('order-read')->plainTextToken)
            ->getJson('/api/orders/'.$id);
    }

    private function createOrder(User $user): Order
    {
        $product = $this->product();
        $response = $this->postOrder($user, [['product_id' => $product->id, 'quantity' => 1]])
            ->assertCreated();

        return Order::query()->findOrFail((int) $response->json('data.order.id'));
    }
}
