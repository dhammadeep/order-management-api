<?php

declare(strict_types=1);

namespace App\Http\Product\Repositories;

use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Eloquent queries for products.
 *
 * @author Dhammadeep
 */
final class ProductRepository
{
    /**
     * Lock product rows in ascending ID order for an atomic order operation.
     * Soft-deleted rows are included so callers can reject them after locking.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    public function lockForOrder(array $productIds): Collection
    {
        sort($productIds, SORT_NUMERIC);

        return Product::withTrashed()
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Decrement all already-locked product rows with one bulk SQL update.
     * IDs and quantities are converted to integers before being embedded in the CASE expression.
     *
     * @param  array<int, int>  $quantitiesByProductId
     */
    public function decrementStockQuantities(array $quantitiesByProductId): void
    {
        $cases = collect($quantitiesByProductId)
            ->map(fn (int $quantity, int|string $productId): string => 'WHEN '.(int) $productId.' THEN stock_quantity - '.(int) $quantity)
            ->implode(' ');
        $productIds = array_map('intval', array_keys($quantitiesByProductId));

        Product::withTrashed()
            ->whereIn('id', $productIds)
            ->update([
                'stock_quantity' => new Expression('CASE id '.$cases.' ELSE stock_quantity END'),
            ]);
    }

    /**
     * Restore stock for rows already locked by the caller using one bulk update.
     *
     * @param  array<int, int>  $quantitiesByProductId
     */
    public function incrementStockQuantities(array $quantitiesByProductId): void
    {
        if ($quantitiesByProductId === []) {
            return;
        }

        ksort($quantitiesByProductId, SORT_NUMERIC);
        $cases = collect($quantitiesByProductId)
            ->map(fn (int $quantity, int|string $productId): string => 'WHEN '.(int) $productId.' THEN stock_quantity + '.(int) $quantity)
            ->implode(' ');

        Product::withTrashed()
            ->whereIn('id', array_map('intval', array_keys($quantitiesByProductId)))
            ->update([
                'stock_quantity' => new Expression('CASE id '.$cases.' ELSE stock_quantity END'),
            ]);
    }

    /**
     * @param  array{name: string, sku: string, price: numeric-string|int|float, stock_quantity: int, status: string}  $attributes
     */
    public function create(array $attributes): Product
    {
        return Product::query()->create($attributes);
    }

    public function findById(int $id, ?ProductStatus $requiredStatus = null): ?Product
    {
        return Product::query()
            ->when($requiredStatus, fn (Builder $query) => $query->where('status', $requiredStatus->value))
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Product
    {
        $product->fill($attributes)->save();

        return $product;
    }

    public function softDelete(Product $product): bool
    {
        return (bool) $product->delete();
    }

    public function paginate(
        int $page,
        int $perPage,
        ?string $search,
        ?ProductStatus $status,
    ): LengthAwarePaginator {
        $query = Product::query()
            ->when($status, fn (Builder $products) => $products->where('status', $status->value))
            ->when($search !== null && $search !== '', function (Builder $products) use ($search): void {
                $term = '%'.$search.'%';

                $products->where(function (Builder $matchingProducts) use ($term): void {
                    $matchingProducts->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term);
                });
            })
            ->orderBy('id');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
