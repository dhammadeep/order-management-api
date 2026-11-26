<?php

declare(strict_types=1);

namespace App\Http\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate API login credentials.
 *
 * @author Dhammadeep
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
