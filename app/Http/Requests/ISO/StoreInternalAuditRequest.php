<?php

declare(strict_types=1);

namespace App\Http\Requests\ISO;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInternalAuditRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'audit_scope'     => 'required|string',
            'standard'        => 'required|string|max:100',
            'lead_auditor_id' => 'nullable|exists:users,id',
            'audit_date'      => 'required|date',
        ];
    }
}
