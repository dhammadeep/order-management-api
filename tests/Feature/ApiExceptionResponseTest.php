<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Ensure API exception responses remain consistent and do not disclose internals.
 *
 * @author Dhammadeep
 */
final class ApiExceptionResponseTest extends TestCase
{
    public function test_missing_api_route_returns_common_not_found_response(): void
    {
        $this->getJson('/api/a-route-that-does-not-exist')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'data' => null,
            ]);
    }

    public function test_unexpected_api_exception_returns_generic_server_error_without_details(): void
    {
        Route::get('/api/testing/unexpected-error', function (): never {
            throw new RuntimeException('SQL password and internal trace must not be returned.');
        });

        $this->getJson('/api/testing/unexpected-error')
            ->assertInternalServerError()
            ->assertExactJson([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'data' => null,
            ])
            ->assertDontSee('SQL password')
            ->assertDontSee('RuntimeException');
    }
}
