<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

final class AttendanceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10 MB cap
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Only CSV files are accepted for attendance import.',
            'file.max' => 'The import file may not exceed 10 MB.',
        ];
    }
}
