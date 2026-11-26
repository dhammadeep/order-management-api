<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use App\Http\Order\Models\OrderItem;
use App\Http\Product\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature coverage for expired pending order cancellation and inventory restoration.
 *
 * @author Dhammadeep
 */
final class ExpiredOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-26 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_expired_pending_order_is_cancelled_and_restores_multiple_product_stocks(): void
    {
        $first = Product::factory()->create(['stock_quantity' => 3]);
        $second = Product::factory()->create(['stock_quantity' => 8]);
        $order = $this->orderWithItems(OrderStatus::Pending, now()->subMinutes(31), [
            [$first, 2],
            [$second, 4],
        ]);

        $this->artisan('orders:cancel-expired')
            ->expectsOutput('Expired order cancellation complete: scanned=1, cancelled=1, skipped=0, failed=0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Cancelled->value]);
        $this->assertDatabaseHas('products', ['id' => $first->id, 'stock_quantity' => 5]);
        $this->assertDatabaseHas('products', ['id' => $second->id, 'stock_quantity' => 12]);
    }

    public function test_pending_order_newer_than_thirty_minutes_is_not_changed(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $order = $this->orderWithItems(OrderStatus::Pending, now()->subMinutes(29), [[$product, 2]]);
        $boundaryOrder = $this->orderWithItems(OrderStatus::Pending, now()->subMinutes(30), [[$product, 1]]);

        $this->artisan('orders:cancel-expired')
            ->expectsOutput('Expired order cancellation complete: scanned=0, cancelled=0, skipped=0, failed=0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
        $this->assertDatabaseHas('orders', ['id' => $boundaryOrder->id, 'status' => OrderStatus::Pending->value]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 3]);
    }

    public function test_paid_shipped_and_cancelled_orders_are_not_changed_or_restored(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 7]);
        $orders = collect([OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Cancelled])
            ->map(fn (OrderStatus $status): Order => $this->orderWithItems($status, now()->subHour(), [[$product, 2]]));

        $this->artisan('orders:cancel-expired')
            ->expectsOutput('Expired order cancellation complete: scanned=0, cancelled=0, skipped=0, failed=0.')
            ->assertExitCode(0);

        foreach ($orders as $order) {
            $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => $order->status->value]);
        }
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 7]);
    }

    public function test_repeated_command_execution_does_not_restore_stock_twice(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 4]);
        $order = $this->orderWithItems(OrderStatus::Pending, now()->subHour(), [[$product, 3]]);

        $this->artisan('orders:cancel-expired')->assertSuccessful();
        $this->artisan('orders:cancel-expired')
            ->expectsOutput('Expired order cancellation complete: scanned=0, cancelled=0, skipped=0, failed=0.')
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Cancelled->value]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 7]);
    }

    public function test_stock_and_order_status_roll_back_if_cancellation_fails(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 4]);
        $order = $this->orderWithItems(OrderStatus::Pending, now()->subHour(), [[$product, 3]]);

        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'update `orders`')) {
                throw new \RuntimeException('Simulated cancellation persistence failure.');
            }
        });

        $this->artisan('orders:cancel-expired')
            ->expectsOutput('Expired order cancellation complete: scanned=1, cancelled=0, skipped=0, failed=1.')
            ->assertExitCode(1);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 4]);
    }

    private function orderWithItems(OrderStatus $status, mixed $createdAt, array $lines): Order
    {
        $order = Order::factory()->create([
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach ($lines as [$product, $quantity]) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
            ]);
        }

        return $order;
    }
}
