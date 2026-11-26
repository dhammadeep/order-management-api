<?php

declare(strict_types=1);

namespace App\Http\Payment\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Payment\Requests\PaymentWebhookRequest;
use App\Http\Payment\Resources\PaymentResource;
use App\Http\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;

/**
 * Accept signed payment-provider notifications.
 *
 * @author Dhammadeep
 */
final class PaymentWebhookController
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(PaymentWebhookRequest $request): JsonResponse
    {
        $result = $this->payments->processSuccessfulWebhook($request->validated());

        return ResponseHelper::respond(
            code: 200,
            data: [
                'payment' => PaymentResource::make($result['payment'])->resolve($request),
                'duplicate' => $result['duplicate'],
            ],
            message: $result['duplicate']
                ? 'Payment notification was already processed.'
                : 'Payment processed successfully.',
        );
    }
}
