<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Http\Order\Models\Order;
use App\Http\Order\Models\OrderItem;
use App\Http\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPriceCents = random_int(100, 100000);
        $quantity = fake()->numberBetween(1, 5);
        $lineTotalCents = $unitPriceCents * $quantity;

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'product_sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('????-####')),
            'unit_price' => $this->formatCents($unitPriceCents),
            'quantity' => $quantity,
            'line_total' => $this->formatCents($lineTotalCents),
        ];
    }

    private function formatCents(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
