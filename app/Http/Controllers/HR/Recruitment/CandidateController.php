<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\Candidate;
use App\Http\Controllers\Controller;
use App\Http\Resources\HR\Recruitment\CandidateResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CandidateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('recruitment.candidates.view'), 403); // Candidate has no policy - keep inline

        $candidates = Candidate::with([])
            ->when($request->input('search'), fn ($q, $s) => $q->whereRaw(
                "LOWER(first_name || ' ' || last_name) LIKE ?",
                ['%' . strtolower($s) . '%']
            )->orWhere('email', 'ILIKE', "%{$s}%"))
            ->when($request->input('source'), fn ($q, $s) => $q->where('source', $s))
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', '25'));

        return CandidateResource::collection($candidates);
    }

    public function show(Request $request, Candidate $candidate): CandidateResource
    {
        abort_unless($request->user()->can('recruitment.candidates.view'), 403); // Candidate has no policy - keep inline

        return new CandidateResource($candidate->load('applications.posting.requisition.position'));
    }

    public function store(Request $request): CandidateResource
    {
        abort_unless($request->user()->can('recruitment.candidates.manage'), 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:candidates,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'source' => ['sometimes', 'string'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        $candidate = Candidate::create($data);

        return new CandidateResource($candidate);
    }

    public function update(Request $request, Candidate $candidate): CandidateResource
    {
        abort_unless($request->user()->can('recruitment.candidates.manage'), 403); // Candidate has no policy - keep inline

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        $candidate->update($data);

        return new CandidateResource($candidate->fresh());
    }
}
