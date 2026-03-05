<?php

declare(strict_types=1);

namespace App\Http\Requests\ISO;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAuditFindingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'finding_type' => 'required|in:nonconformity,observation,opportunity',
            'clause_ref'   => 'nullable|string|max:50',
            'description'  => 'required|string',
            'severity'     => 'required|in:minor,major',
        ];
    }
}
