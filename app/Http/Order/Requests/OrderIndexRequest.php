<?php

declare(strict_types=1);

namespace App\Http\Order\Requests;

use App\Http\Order\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate order pagination input for either role.
 *
 * @author Dhammadeep
 */
final class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Order::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
