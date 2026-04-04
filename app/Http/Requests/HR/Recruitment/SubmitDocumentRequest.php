<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submitDocument', PreEmploymentChecklist::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ];
    }
}
