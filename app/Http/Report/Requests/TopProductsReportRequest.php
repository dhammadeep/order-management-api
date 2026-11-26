<?php

declare(strict_types=1);

namespace App\Http\Report\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate the date interval for the top-products report.
 *
 * @author Dhammadeep
 */
final class TopProductsReportRequest extends FormRequest
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
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }
}
