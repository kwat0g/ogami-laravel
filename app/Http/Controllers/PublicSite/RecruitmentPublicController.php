<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domains\HR\Recruitment\Enums\CandidateSource;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Services\ApplicationService;
use App\Domains\HR\Recruitment\Services\JobPostingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\StorePublicRecruitmentApplicationRequest;
use App\Http\Resources\HR\Recruitment\JobPostingListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RecruitmentPublicController extends Controller
{
    public function __construct(
        private readonly JobPostingService $postingService,
        private readonly ApplicationService $applicationService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->postingService->listPublicActive((int) $request->query('per_page', '20'));

        return JobPostingListResource::collection($result);
    }

    public function store(StorePublicRecruitmentApplicationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $posting = JobPosting::query()
            ->where('ulid', $validated['posting_ulid'])
            ->firstOrFail();

        $application = $this->applicationService->apply(
            $posting,
            [
                ...$validated['candidate'],
                'source' => CandidateSource::JobBoard->value,
            ],
            [
                'cover_letter' => $validated['cover_letter'] ?? null,
                'source' => CandidateSource::JobBoard->value,
            ],
            $request->file('resume'),
        );

        return response()->json([
            'data' => [
                'application_ulid' => $application->ulid,
                'application_number' => $application->application_number,
                'status' => $application->status->value,
                'message' => 'Application submitted successfully.',
            ],
        ], 201);
    }
}
