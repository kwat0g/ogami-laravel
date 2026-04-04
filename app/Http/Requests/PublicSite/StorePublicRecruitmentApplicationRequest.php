<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;

final class StorePublicRecruitmentApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'posting_ulid' => ['required', 'ulid', 'exists:job_postings,ulid'],
            'candidate.first_name' => ['required', 'string', 'max:100'],
            'candidate.last_name' => ['required', 'string', 'max:100'],
            'candidate.email' => ['required', 'email', 'max:255'],
            'candidate.phone' => ['required', 'string', 'max:30'],
            'candidate.address' => ['nullable', 'string', 'max:2000'],
            'candidate.linkedin_url' => ['nullable', 'url', 'max:500'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }
}
