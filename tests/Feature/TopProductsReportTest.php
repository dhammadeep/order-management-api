<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Auth\Enums\UserRole;
use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use App\Http\Order\Models\OrderItem;
use App\Http\Product\Models\Product;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature coverage for the admin top-products aggregate report.
 *
 * @author Dhammadeep
 */
final class TopProductsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_returns_top_five_products_ranked_by_total_quantity(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $products = collect(range(1, 6))->map(fn (int $number): Product => Product::factory()->create([
            'name' => "Product {$number}",
            'sku' => "REPORT-{$number}",
        ]));

        $this->recordSale($products[0], OrderStatus::Paid, '2026-06-15 12:00:00', 5);
        $this->recordSale($products[0], OrderStatus::Shipped, '2026-06-16 12:00:00', 7);
        $this->recordSale($products[1], OrderStatus::Paid, '2026-06-15 12:00:00', 10);
        $this->recordSale($products[2], OrderStatus::Paid, '2026-06-15 12:00:00', 10);
        $this->recordSale($products[3], OrderStatus::Paid, '2026-06-15 12:00:00', 7);
        $this->recordSale($products[4], OrderStatus::Paid, '2026-06-15 12:00:00', 4);
        $this->recordSale($products[5], OrderStatus::Paid, '2026-06-15 12:00:00', 2);

        $response = $this->asAdmin($admin)->getJson($this->reportUrl());

        $response->assertOk()
            ->assertJsonCount(5, 'data.products')
            ->assertJsonPath('data.products.0.product_id', $products[0]->id)
            ->assertJsonPath('data.products.0.total_quantity_sold', 12)
            ->assertJsonPath('data.products.1.product_id', $products[1]->id)
            ->assertJsonPath('data.products.1.total_quantity_sold', 10)
            ->assertJsonPath('data.products.2.product_id', $products[2]->id)
            ->assertJsonPath('data.products.4.product_id', $products[4]->id);
    }

    public function test_report_includes_date_boundaries_and_paid_or_shipped_orders_only(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $product = Product::factory()->create();

        $this->recordSale($product, OrderStatus::Paid, '2026-06-01 00:00:00', 3);
        $this->recordSale($product, OrderStatus::Shipped, '2026-06-30 23:59:59', 4);
        $this->recordSale($product, OrderStatus::Paid, '2026-05-31 23:59:59', 20);
        $this->recordSale($product, OrderStatus::Paid, '2026-07-01 00:00:00', 20);
        $this->recordSale($product, OrderStatus::Pending, '2026-06-15 12:00:00', 20);
        $this->recordSale($product, OrderStatus::Cancelled, '2026-06-15 12:00:00', 20);

        $this->asAdmin($admin)->getJson($this->reportUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.total_quantity_sold', 7);
    }

    public function test_report_uses_purchase_snapshots_after_catalog_product_changes_or_deletion(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $product = Product::factory()->create(['name' => 'Catalog Name', 'sku' => 'CATALOG-1']);
        $this->recordSale($product, OrderStatus::Paid, '2026-06-15 12:00:00', 6, 'Purchased Name', 'PURCHASE-1');
        $product->update(['name' => 'Renamed Catalog Product', 'sku' => 'CATALOG-2']);
        $product->delete();

        $this->asAdmin($admin)->getJson($this->reportUrl())
            ->assertOk()
            ->assertJsonPath('data.products.0.product_id', $product->id)
            ->assertJsonPath('data.products.0.product_name', 'Purchased Name')
            ->assertJsonPath('data.products.0.product_sku', 'PURCHASE-1')
            ->assertJsonPath('data.products.0.total_quantity_sold', 6);
    }

    public function test_catalog_edits_do_not_split_sales_for_one_product_into_duplicate_rows(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $product = Product::factory()->create(['name' => 'Original Name', 'sku' => 'ORIGINAL-1']);
        $this->recordSale($product, OrderStatus::Paid, '2026-06-10 12:00:00', 3, 'Original Name', 'ORIGINAL-1');
        $this->recordSale($product, OrderStatus::Paid, '2026-06-20 12:00:00', 4, 'Updated Snapshot Name', 'UPDATED-1');

        $this->asAdmin($admin)->getJson($this->reportUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.product_id', $product->id)
            ->assertJsonPath('data.products.0.product_name', 'Updated Snapshot Name')
            ->assertJsonPath('data.products.0.product_sku', 'UPDATED-1')
            ->assertJsonPath('data.products.0.total_quantity_sold', 7);
    }

    public function test_report_returns_an_empty_products_array_when_there_are_no_sales(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);

        $this->asAdmin($admin)->getJson($this->reportUrl())
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Top products report generated successfully.',
                'data' => ['products' => []],
            ]);
    }

    public function test_customer_cannot_access_report(): void
    {
        $customer = $this->userWithRole(UserRole::Customer);

        $this->withToken($customer->createToken('report-customer')->plainTextToken)
            ->getJson($this->reportUrl())
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_report(): void
    {
        $this->getJson($this->reportUrl())->assertUnauthorized();
    }

    public function test_invalid_date_range_returns_validation_error(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);

        $this->asAdmin($admin)->getJson('/api/admin/reports/top-products?from_date=2026-06-30&to_date=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['to_date']]);
    }

    public function test_report_executes_one_aggregate_join_query(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $product = Product::factory()->create();
        $this->recordSale($product, OrderStatus::Paid, '2026-06-15 12:00:00', 2);
        $reportQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$reportQueries): void {
            if (str_contains(strtolower($query->sql), 'order_items')) {
                $reportQueries[] = strtolower($query->sql);
            }
        });

        $this->asAdmin($admin)->getJson($this->reportUrl())->assertOk();

        $this->assertCount(1, $reportQueries);
        $this->assertStringContainsString('join', $reportQueries[0]);
        $this->assertStringContainsString('sum(', $reportQueries[0]);
        $this->assertStringContainsString('group by', $reportQueries[0]);
        $this->assertStringContainsString('limit 5', $reportQueries[0]);
        $this->assertStringContainsString('created_at', $reportQueries[0]);
        $this->assertStringContainsString('status', $reportQueries[0]);
    }

    private function userWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function asAdmin(User $admin): static
    {
        return $this->withToken($admin->createToken('report-admin')->plainTextToken);
    }

    private function reportUrl(): string
    {
        return '/api/admin/reports/top-products?from_date=2026-06-01&to_date=2026-06-30';
    }

    private function recordSale(
        Product $product,
        OrderStatus $status,
        string $createdAt,
        int $quantity,
        ?string $snapshotName = null,
        ?string $snapshotSku = null,
    ): void {
        $order = Order::factory()->create([
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $snapshotName ?? $product->name,
            'product_sku' => $snapshotSku ?? $product->sku,
            'unit_price' => '1.00',
            'quantity' => $quantity,
            'line_total' => number_format($quantity, 2, '.', ''),
        ]);
    }
}
