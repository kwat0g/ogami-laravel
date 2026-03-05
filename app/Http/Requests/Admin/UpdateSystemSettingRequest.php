<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates system setting updates.
 * Requires 'system.edit_settings' permission.
 */
final class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('system.edit_settings');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isBulk = $this->has('settings');

        if ($isBulk) {
            return [
                'settings' => ['required', 'array', 'min:1'],
                'settings.*.key' => ['required', 'string', 'max:100'],
                'settings.*.value' => ['required'],
            ];
        }

        return [
            'value' => ['required'],
            'reason' => ['sometimes', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.required' => 'At least one setting must be provided for bulk update.',
            'settings.*.key.required' => 'Setting key is required.',
            'settings.*.value.required' => 'Setting value is required.',
            'value.required' => 'Setting value is required.',
        ];
    }
}
