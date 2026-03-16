<?php

declare(strict_types=1);

namespace App\Http\Requests\QC;

use Illuminate\Foundation\Http\FormRequest;

final class IssueCapaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'type' => 'required|in:corrective,preventive',
            'description' => 'required|string',
            'due_date' => 'required|date|after_or_equal:today',
            'assigned_to_id' => 'nullable|exists:users,id',
        ];
    }
}
