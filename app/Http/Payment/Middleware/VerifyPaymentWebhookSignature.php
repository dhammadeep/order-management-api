<?php

declare(strict_types=1);

namespace App\Http\Payment\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify the provider signature against the exact raw request body before payload validation.
 *
 * @author Dhammadeep
 */
final class VerifyPaymentWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');

        if (! is_string($signature) || preg_match('/\A[0-9a-f]{64}\z/i', $signature) !== 1) {
            return ResponseHelper::respond(
                code: 401,
                message: 'Invalid webhook signature.',
            );
        }

        $secret = config('services.payment_webhook.secret');

        if (! is_string($secret) || $secret === '') {
            return ResponseHelper::respond(
                code: 500,
                message: 'Payment webhook is not configured.',
            );
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, strtolower($signature))) {
            return ResponseHelper::respond(
                code: 401,
                message: 'Invalid webhook signature.',
            );
        }

        return $next($request);
    }
}
