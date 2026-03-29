<?php

declare(strict_types=1);

namespace App\Http\Requests\HR\Recruitment;

use App\Domains\HR\Recruitment\Enums\CandidateSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.applications.review');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_posting_id' => ['required', 'integer', 'exists:job_postings,id'],
            // Candidate fields (create-or-find by email)
            'candidate.first_name' => ['required', 'string', 'max:100'],
            'candidate.last_name' => ['required', 'string', 'max:100'],
            'candidate.email' => ['required', 'email', 'max:255'],
            'candidate.phone' => ['nullable', 'string', 'max:30'],
            'candidate.address' => ['nullable', 'string'],
            'candidate.source' => ['sometimes', 'string', Rule::in(CandidateSource::values())],
            'candidate.linkedin_url' => ['nullable', 'url', 'max:500'],
            // Application fields
            'cover_letter' => ['nullable', 'string'],
            'source' => ['sometimes', 'string', Rule::in(CandidateSource::values())],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }
}
