<?php

declare(strict_types=1);

namespace App\Http\Order\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Order\Requests\OrderIndexRequest;
use App\Http\Order\Requests\OrderStoreRequest;
use App\Http\Order\Resources\OrderResource;
use App\Http\Order\Services\OrderService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * HTTP endpoints for placing and reading orders.
 *
 * @author Dhammadeep
 */
final class OrderController
{
    public function __construct(private readonly OrderService $orders) {}

    public function store(OrderStoreRequest $request): JsonResponse
    {
        /** @var User $customer */
        $customer = $request->user();
        $order = $this->orders->placeOrder($customer, $request->validated()['items']);

        return ResponseHelper::respond(
            code: 201,
            data: ['order' => OrderResource::make($order)->resolve($request)],
            message: 'Order placed successfully.',
        );
    }

    public function index(OrderIndexRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $orders = $this->orders->paginateFor(
            user: $user,
            page: (int) ($filters['page'] ?? 1),
            perPage: (int) ($filters['per_page'] ?? 15),
        );

        return ResponseHelper::respond(
            code: 200,
            data: [
                'orders' => OrderResource::collection($orders->getCollection())->resolve($request),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
            ],
            message: 'Orders retrieved successfully.',
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orders->find($id);

        if ($order === null) {
            return ResponseHelper::respond(404, message: 'Order not found.');
        }

        Gate::authorize('view', $order);

        return ResponseHelper::respond(
            code: 200,
            data: ['order' => OrderResource::make($order)->resolve($request)],
            message: 'Order retrieved successfully.',
        );
    }
}
