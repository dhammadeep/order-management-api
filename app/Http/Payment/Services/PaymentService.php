<?php

declare(strict_types=1);

namespace App\Http\Payment\Services;

use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use App\Http\Order\Repositories\OrderRepository;
use App\Http\Payment\Exceptions\PaymentConflictException;
use App\Http\Payment\Models\Payment;
use App\Http\Payment\Repositories\PaymentRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Applies verified payment updates atomically and idempotently.
 *
 * @author Dhammadeep
 */
final class PaymentService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly PaymentRepository $payments,
    ) {}

    /**
     * @param  array{transaction_id: string, order_id: int|string, amount?: string|int|float|null, status: string}  $payload
     * @return array{payment: Payment, duplicate: bool}
     */
    public function processSuccessfulWebhook(array $payload): array
    {
        if ($payload['status'] !== 'success') {
            throw ValidationException::withMessages(['status' => ['Only successful payment notifications can be processed.']]);
        }

        $orderId = (int) $payload['order_id'];

        return DB::transaction(function () use ($payload, $orderId): array {
            $order = $this->orders->lockById($orderId)
                ?? throw (new ModelNotFoundException)->setModel(Order::class, [$orderId]);

            $existingPayment = $this->payments->findByTransactionIdForUpdate($payload['transaction_id']);

            if ($existingPayment !== null) {
                return [
                    'payment' => $this->confirmDuplicate($existingPayment, $order, $payload),
                    'duplicate' => true,
                ];
            }

            if ($order->status !== OrderStatus::Pending) {
                throw new PaymentConflictException('Order cannot be marked as paid from its current status.');
            }

            $this->validateAmount($order, $payload);

            $inserted = $this->payments->insertIfAbsent([
                'order_id' => $order->id,
                'transaction_id' => $payload['transaction_id'],
                'amount' => (string) $order->total_amount,
                'status' => 'success',
            ]);

            if (! $inserted) {
                $existingPayment = $this->payments->findByTransactionIdForUpdate($payload['transaction_id']);

                if ($existingPayment === null) {
                    throw new RuntimeException('Payment transaction could not be inserted or found.');
                }

                return [
                    'payment' => $this->confirmDuplicate($existingPayment, $order, $payload),
                    'duplicate' => true,
                ];
            }

            $this->orders->markPaid($order);
            $payment = $this->payments->findByTransactionIdForUpdate($payload['transaction_id'])
                ?? throw new RuntimeException('Inserted payment transaction could not be reloaded.');

            return ['payment' => $payment, 'duplicate' => false];
        }, attempts: 3);
    }

    /**
     * @param  array{transaction_id: string, order_id: int|string, amount?: string|int|float|null, status: string}  $payload
     */
    private function confirmDuplicate(Payment $payment, Order $order, array $payload): Payment
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            throw new PaymentConflictException('Transaction ID is already associated with another order.');
        }

        $this->validateAmount($order, $payload);

        return $payment;
    }

    /**
     * @param  array{amount?: string|int|float|null}  $payload
     */
    private function validateAmount(Order $order, array $payload): void
    {
        if (! array_key_exists('amount', $payload) || $payload['amount'] === null) {
            return;
        }

        if (bccomp((string) $payload['amount'], (string) $order->total_amount, 2) !== 0) {
            throw ValidationException::withMessages(['amount' => ['Payment amount does not match the order total.']]);
        }
    }
}
