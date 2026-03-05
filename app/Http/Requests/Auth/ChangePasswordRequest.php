<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the change-password payload.
 *
 * Rules mirror the changePasswordSchema in frontend/src/schemas/auth.ts:
 *   - current_password — required, verified against the stored hash at service layer
 *   - password         — min 8, mixed case, digit, special character
 *   - password_confirmation — must match password
 *
 * @property-read string $current_password
 * @property-read string $password
 * @property-read string $password_confirmation
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user(); // Requires authenticated user
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'confirmed',
                'regex:/[A-Z]/',    // uppercase
                'regex:/[a-z]/',    // lowercase
                'regex:/[0-9]/',    // digit
                'regex:/[^A-Za-z0-9]/', // special character
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.',
        ];
    }
}
