<?php

declare(strict_types=1);

namespace App\Http\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate the provider's signed payment notification payload.
 *
 * @author Dhammadeep
 */
final class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string', 'max:191'],
            'order_id' => ['required', 'integer', 'min:1', Rule::exists('orders', 'id')],
            'amount' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'status' => ['required', 'string', Rule::in(['success'])],
        ];
    }
}
