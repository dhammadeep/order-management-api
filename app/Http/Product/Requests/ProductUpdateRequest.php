<?php

declare(strict_types=1);

namespace App\Http\Product\Requests;

use App\Http\Product\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate partial product updates.
 *
 * @author Dhammadeep
 */
final class ProductUpdateRequest extends FormRequest
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
        $productId = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
            'status' => ['sometimes', 'required', 'string', Rule::in(array_column(ProductStatus::cases(), 'value'))],
        ];
    }
}
