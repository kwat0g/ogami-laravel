<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the standard email/password login payload.
 * The device_name field is used as the Sanctum token name.
 *
 * @property-read string $email
 * @property-read string $password
 * @property-read string|null $device_name
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function deviceName(): string
    {
        return $this->input('device_name', $this->userAgent() ?? 'unknown-device');
    }
}
