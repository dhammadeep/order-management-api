<?php

declare(strict_types=1);

namespace App\Http\Auth\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Auth\Requests\LoginRequest;
use App\Http\Auth\Requests\RegisterRequest;
use App\Http\Auth\Resources\UserResource;
use App\Http\Auth\Services\AuthService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP endpoints for customer registration and token authentication.
 *
 * @author Dhammadeep
 */
final class AuthController
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->registerCustomer($request->validated());

        return ResponseHelper::respond(
            code: 201,
            data: ['user' => UserResource::make($user)->resolve($request)],
            message: 'Customer registered successfully.',
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $session = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return ResponseHelper::respond(
            code: 200,
            data: [
                'user' => UserResource::make($session['user'])->resolve($request),
                'access_token' => $session['access_token'],
                'token_type' => 'Bearer',
            ],
            message: 'Login successful.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return ResponseHelper::respond(
            code: 200,
            message: 'Logged out successfully.',
        );
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ResponseHelper::respond(
            code: 200,
            data: ['user' => UserResource::make($user)->resolve($request)],
            message: 'Current user retrieved successfully.',
        );
    }
}
