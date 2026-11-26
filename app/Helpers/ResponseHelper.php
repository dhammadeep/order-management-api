<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

/**
 * Builds the common JSON envelope used by API endpoints.
 *
 * @author Dhammadeep
 */
final class ResponseHelper
{
    /**
     * Return a JSON response with a consistent success or error shape.
     *
     * @param array<string, mixed>|null $errors
     */
    public static function respond(
        int $code,
        mixed $data = null,
        string $message = 'Request processed successfully.',
        ?array $errors = null,
    ): JsonResponse {
        $payload = [
            'success' => $code >= 200 && $code < 300,
            'message' => $message,
            'data' => $data,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }
}
