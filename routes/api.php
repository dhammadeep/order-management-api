<?php

use App\Helpers\ResponseHelper;
use App\Http\Auth\Controllers\AuthController;
use App\Http\Order\Controllers\OrderController;
use App\Http\Product\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ResponseHelper::respond(
    code: 200,
    data: ['status' => 'ok'],
    message: 'API is available.',
));

Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');

    Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])
        ->whereNumber('id')
        ->name('api.orders.show');

    Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
    Route::get('/products/{id}', [ProductController::class, 'show'])
        ->whereNumber('id')
        ->name('api.products.show');

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/products', [ProductController::class, 'store'])->name('api.products.store');
        Route::put('/products/{id}', [ProductController::class, 'update'])
            ->whereNumber('id')
            ->name('api.products.update');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])
            ->whereNumber('id')
            ->name('api.products.destroy');
    });
});
