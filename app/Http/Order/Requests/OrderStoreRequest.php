<?php

declare(strict_types=1);

namespace App\Http\Order\Requests;

use App\Http\Order\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate customer order input and enforce the create policy before controller execution.
 *
 * @author Dhammadeep
 */
final class OrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct:strict', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
