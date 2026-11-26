<?php

declare(strict_types=1);

namespace App\Http\Order\Repositories;

use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Database access for order records and their purchase snapshots.
 *
 * @author Dhammadeep
 */
final class OrderRepository
{
    public function lockById(int $id): ?Order
    {
        return Order::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    public function markPaid(Order $order): void
    {
        $order->status = OrderStatus::Paid;
        $order->save();
    }

    /**
     * Persist an order and all of its items using a small, fixed number of queries.
     * The caller owns the surrounding transaction.
     *
     * @param  array{user_id: int, order_number: string, total_amount: string, status: string}  $attributes
     * @param  list<array{product_id: int, product_name: string, product_sku: string, unit_price: string, quantity: int, line_total: string}>  $items
     */
    public function createWithItems(array $attributes, array $items): Order
    {
        $order = Order::query()->create($attributes);
        $timestamp = now();
        $rows = collect($items)->map(fn (array $item): array => [
            'order_id' => $order->id,
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'product_sku' => $item['product_sku'],
            'unit_price' => $item['unit_price'],
            'quantity' => $item['quantity'],
            'line_total' => $item['line_total'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        $order->items()->getRelated()->newQuery()->insert($rows);

        return $order;
    }

    public function findByIdWithItems(int $id): ?Order
    {
        return Order::query()->with('items')->find($id);
    }

    public function paginate(?int $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->with('items')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
