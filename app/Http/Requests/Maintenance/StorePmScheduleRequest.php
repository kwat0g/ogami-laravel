<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

final class StorePmScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'task_name'      => 'required|string|max:200',
            'frequency_days' => 'required|integer|min:1',
            'last_done_on'   => 'nullable|date',
        ];
    }
}
