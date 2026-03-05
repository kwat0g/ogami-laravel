<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize() via policy
    }

    public function rules(): array
    {
        return [
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
            'reviewer_remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
