<?php

declare(strict_types=1);

namespace App\Http\Payment\Resources;

use App\Http\Payment\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit public response for an accepted payment transaction.
 *
 * @author Dhammadeep
 *
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'transaction_id' => $this->transaction_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
