<?php

declare(strict_types=1);

namespace App\Http\Requests\ISO;

use Illuminate\Foundation\Http\FormRequest;

final class StoreControlledDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'title'           => 'required|string|max:300',
            'category'        => 'nullable|string|max:100',
            'document_type'   => 'required|in:procedure,work_instruction,form,manual,policy,record',
            'owner_id'        => 'nullable|exists:users,id',
            'current_version' => 'nullable|string|max:20',
            'status'          => 'in:draft,under_review,approved,obsolete',
            'effective_date'  => 'nullable|date',
            'review_date'     => 'nullable|date',
        ];
    }
}
