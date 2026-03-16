<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the GA Officer's POST body for PATCH /leave/requests/{id}/ga-process.
 *
 * Required field: action_taken — must be one of the three values that match
 * the "FOR HR PERSONNEL USE" box on physical form AD-084-00.
 */
final class ProcessLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate handled in controller via $this->authorize()
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action_taken' => ['required', 'string', 'in:approved_with_pay,approved_without_pay,disapproved'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'action_taken.required' => 'An action must be selected (approved with pay, without pay, or disapproved).',
            'action_taken.in' => 'Action taken must be "approved_with_pay", "approved_without_pay", or "disapproved".',
        ];
    }
}
