<?php

use App\Helpers\ResponseHelper;
use App\Http\Auth\Controllers\AuthController;
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
});
