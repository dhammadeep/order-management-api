<?php

declare(strict_types=1);

namespace App\Http\Product\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Requests\ProductIndexRequest;
use App\Http\Product\Requests\ProductStoreRequest;
use App\Http\Product\Requests\ProductUpdateRequest;
use App\Http\Product\Resources\ProductResource;
use App\Http\Product\Services\ProductService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP endpoints for product catalog management.
 *
 * @author Dhammadeep
 */
final class ProductController
{
    public function __construct(private readonly ProductService $products) {}

    public function index(ProductIndexRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $status = isset($filters['status']) ? ProductStatus::from($filters['status']) : null;

        $products = $this->products->paginateFor(
            user: $user,
            page: (int) ($filters['page'] ?? 1),
            perPage: (int) ($filters['per_page'] ?? 15),
            search: $filters['search'] ?? null,
            status: $status,
        );

        return ResponseHelper::respond(
            code: 200,
            data: [
                'products' => ProductResource::collection($products->getCollection())->resolve($request),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ],
            message: 'Products retrieved successfully.',
        );
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated());

        return ResponseHelper::respond(
            code: 201,
            data: ['product' => ProductResource::make($product)->resolve($request)],
            message: 'Product created successfully.',
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $product = $this->products->findVisibleTo($user, $id);

        if ($product === null) {
            return ResponseHelper::respond(404, message: 'Product not found.');
        }

        return ResponseHelper::respond(
            code: 200,
            data: ['product' => ProductResource::make($product)->resolve($request)],
            message: 'Product retrieved successfully.',
        );
    }

    public function update(ProductUpdateRequest $request, int $id): JsonResponse
    {
        $product = $this->products->update($id, $request->validated());

        if ($product === null) {
            return ResponseHelper::respond(404, message: 'Product not found.');
        }

        return ResponseHelper::respond(
            code: 200,
            data: ['product' => ProductResource::make($product)->resolve($request)],
            message: 'Product updated successfully.',
        );
    }

    public function destroy(int $id): JsonResponse
    {
        if (! $this->products->delete($id)) {
            return ResponseHelper::respond(404, message: 'Product not found.');
        }

        return ResponseHelper::respond(
            code: 200,
            message: 'Product deleted successfully.',
        );
    }
}
