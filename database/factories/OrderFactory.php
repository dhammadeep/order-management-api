<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.Str::upper((string) Str::ulid()),
            'total_amount' => '0.00',
            'status' => OrderStatus::Pending,
        ];
    }
}
