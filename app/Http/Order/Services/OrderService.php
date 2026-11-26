<?php

declare(strict_types=1);

namespace App\Http\Order\Services;

use App\Http\Auth\Enums\UserRole;
use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use App\Http\Order\Repositories\OrderRepository;
use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Repositories\ProductRepository;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Applies order rules and coordinates locked stock and order persistence.
 *
 * @author Dhammadeep
 */
final class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    public function placeOrder(User $customer, array $items): Order
    {
        if ($customer->role !== UserRole::Customer) {
            throw new AuthorizationException;
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => ['At least one product is required.']]);
        }

        $quantitiesByProductId = [];

        foreach ($items as $item) {
            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($productId === false || $quantity === false || $productId < 1 || $quantity < 1 || $quantity > 4294967295) {
                throw ValidationException::withMessages(['items' => ['Each product ID and quantity must be positive.']]);
            }

            if (array_key_exists($productId, $quantitiesByProductId)) {
                throw ValidationException::withMessages(['items' => ['Each product may appear only once in an order.']]);
            }

            $quantitiesByProductId[$productId] = $quantity;
        }

        $productIds = array_keys($quantitiesByProductId);
        sort($productIds, SORT_NUMERIC);

        $orderId = DB::transaction(function () use ($customer, $productIds, $quantitiesByProductId): int {
            $lockedProducts = $this->products->lockForOrder($productIds)->keyBy('id');

            if ($lockedProducts->count() !== count($productIds)) {
                throw ValidationException::withMessages(['items' => ['One or more products do not exist.']]);
            }

            $orderItems = [];
            $orderTotal = '0.00';

            foreach ($productIds as $productId) {
                $product = $lockedProducts->get($productId);
                $quantity = $quantitiesByProductId[$productId];

                if ($product->trashed() || $product->status !== ProductStatus::Active) {
                    throw ValidationException::withMessages(['items' => ["Product {$productId} is not available for purchase."]]);
                }

                if ($product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages(['items' => ["Insufficient stock for product {$productId}."]]);
                }

                $unitPrice = (string) $product->price;
                $lineTotal = bcmul($unitPrice, (string) $quantity, 2);
                $orderTotal = bcadd($orderTotal, $lineTotal, 2);

                if (bccomp($lineTotal, '9999999999.99', 2) > 0 || bccomp($orderTotal, '9999999999.99', 2) > 0) {
                    throw ValidationException::withMessages(['items' => ['The order total exceeds the supported monetary range.']]);
                }

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $order = $this->orders->createWithItems([
                'user_id' => $customer->id,
                'order_number' => 'ORD-'.Str::upper((string) Str::ulid()),
                'total_amount' => $orderTotal,
                'status' => OrderStatus::Pending->value,
            ], $orderItems);

            $this->products->decrementStockQuantities($quantitiesByProductId);

            return $order->id;
        }, attempts: 3);

        return $this->orders->findByIdWithItems($orderId)
            ?? throw (new ModelNotFoundException)->setModel(Order::class, [$orderId]);
    }

    public function find(int $id): ?Order
    {
        return $this->orders->findByIdWithItems($id);
    }

    public function paginateFor(User $user, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginate(
            userId: $user->role === UserRole::Admin ? null : $user->id,
            page: $page,
            perPage: $perPage,
        );
    }
}
