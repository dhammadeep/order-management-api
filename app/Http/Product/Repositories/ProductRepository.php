<?php

declare(strict_types=1);

namespace App\Http\Product\Repositories;

use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Eloquent queries for products.
 *
 * @author Dhammadeep
 */
final class ProductRepository
{
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
