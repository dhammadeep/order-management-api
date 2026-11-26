<?php

declare(strict_types=1);

namespace App\Http\Product\Services;

use App\Http\Auth\Enums\UserRole;
use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Models\Product;
use App\Http\Product\Repositories\ProductRepository;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Applies product access rules and coordinates product persistence.
 *
 * @author Dhammadeep
 */
final class ProductService
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @throws AuthorizationException
     */
    public function paginateFor(User $user, int $page, int $perPage, ?string $search, ?ProductStatus $status): LengthAwarePaginator
    {
        $isAdmin = $user->role === UserRole::Admin;

        if (! $isAdmin && $status !== null && $status !== ProductStatus::Active) {
            throw new AuthorizationException('Only admins may filter products by status.');
        }

        return $this->products->paginate(
            page: $page,
            perPage: $perPage,
            search: $search,
            status: $isAdmin ? $status : ProductStatus::Active,
        );
    }

    public function findVisibleTo(User $user, int $id): ?Product
    {
        $requiredStatus = $user->role === UserRole::Admin ? null : ProductStatus::Active;

        return $this->products->findById($id, $requiredStatus);
    }

    /**
     * @param  array{name: string, sku: string, price: numeric-string|int|float, stock_quantity: int, status: string}  $attributes
     */
    public function create(array $attributes): Product
    {
        return $this->products->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): ?Product
    {
        $product = $this->products->findById($id);

        if ($product === null) {
            return null;
        }

        return $this->products->update($product, $attributes);
    }

    public function delete(int $id): bool
    {
        $product = $this->products->findById($id);

        return $product !== null && $this->products->softDelete($product);
    }
}
