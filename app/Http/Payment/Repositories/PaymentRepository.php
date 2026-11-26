<?php

declare(strict_types=1);

namespace App\Http\Payment\Repositories;

use App\Http\Payment\Models\Payment;

/**
 * Database access for provider payment transaction records.
 *
 * @author Dhammadeep
 */
final class PaymentRepository
{
    public function findByTransactionIdForUpdate(string $transactionId): ?Payment
    {
        return Payment::query()
            ->where('transaction_id', $transactionId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Insert one successful payment if its unique provider transaction ID is unused.
     *
     * @param  array{order_id: int, transaction_id: string, amount: string, status: string}  $attributes
     */
    public function insertIfAbsent(array $attributes): bool
    {
        $now = now();

        return Payment::query()->insertOrIgnore([
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;
    }
}
