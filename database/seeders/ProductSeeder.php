<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Seed a repeatable set of example catalog products.
 *
 * @author Dhammadeep
 */
final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Stainless Steel Water Bottle', 'sku' => 'HOME-BTL-001', 'price' => '18.50', 'stock_quantity' => 120, 'status' => ProductStatus::Active],
            ['name' => 'Wireless Optical Mouse', 'sku' => 'TECH-MSE-002', 'price' => '24.99', 'stock_quantity' => 75, 'status' => ProductStatus::Active],
            ['name' => 'Cotton Crew T-Shirt', 'sku' => 'APP-TSH-003', 'price' => '16.00', 'stock_quantity' => 200, 'status' => ProductStatus::Active],
            ['name' => 'Ceramic Pour Over Coffee Set', 'sku' => 'HOME-COF-004', 'price' => '42.00', 'stock_quantity' => 34, 'status' => ProductStatus::Active],
            ['name' => 'USB-C Charging Cable', 'sku' => 'TECH-CBL-005', 'price' => '9.95', 'stock_quantity' => 300, 'status' => ProductStatus::Active],
            ['name' => 'Insulated Lunch Bag', 'sku' => 'HOME-LCH-006', 'price' => '29.50', 'stock_quantity' => 51, 'status' => ProductStatus::Active],
            ['name' => 'Foldable Travel Umbrella', 'sku' => 'ACC-UMB-007', 'price' => '21.75', 'stock_quantity' => 0, 'status' => ProductStatus::Inactive],
            ['name' => 'Bluetooth Desk Speaker', 'sku' => 'TECH-SPK-008', 'price' => '59.00', 'stock_quantity' => 12, 'status' => ProductStatus::Inactive],
        ];

        $rows = collect($products)
            ->map(fn (array $product): array => [
                ...$product,
                'status' => $product['status']->value,
            ])
            ->all();

        Product::query()->upsert(
            $rows,
            ['sku'],
            ['name', 'price', 'stock_quantity', 'status', 'updated_at'],
        );
    }
}
