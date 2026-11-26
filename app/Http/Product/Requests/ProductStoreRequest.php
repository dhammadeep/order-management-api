<?php

declare(strict_types=1);

namespace App\Http\Product\Requests;

use App\Http\Product\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate product creation input.
 *
 * @author Dhammadeep
 */
final class ProductStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'status' => ['required', 'string', Rule::in(array_column(ProductStatus::cases(), 'value'))],
        ];
    }
}
