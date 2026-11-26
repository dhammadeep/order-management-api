<?php

use App\Helpers\ResponseHelper;
use App\Http\Auth\Middleware\EnsureUserHasRole;
use App\Http\Payment\Exceptions\PaymentConflictException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::respond(
                    code: 422,
                    message: 'Validation failed.',
                    errors: $exception->errors(),
                );
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::respond(
                    code: 401,
                    message: 'Unauthenticated.',
                );
            }
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::respond(
                    code: 403,
                    message: 'You are not authorized to access this resource.',
                );
            }
        });

        $exceptions->render(function (PaymentConflictException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::respond(
                    code: 409,
                    message: $exception->getMessage(),
                );
            }
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->is('api/*')) {
                $status = $exception->getStatusCode();

                return ResponseHelper::respond(
                    code: $status,
                    message: $status === 404 ? 'Resource not found.' : 'Request could not be completed.',
                );
            }
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::respond(
                    code: 500,
                    message: 'An unexpected error occurred.',
                );
            }
        });
    })->create();
