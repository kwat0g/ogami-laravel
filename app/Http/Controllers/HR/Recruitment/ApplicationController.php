<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Services\ApplicationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Recruitment\StoreApplicationRequest;
use App\Http\Resources\HR\Recruitment\ApplicationListResource;
use App\Http\Resources\HR\Recruitment\ApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Application::class);

        $result = $this->service->list(
            $request->only(['job_posting_id', 'status', 'candidate_id', 'search']),
            (int) $request->query('per_page', '25'),
        );

        return ApplicationListResource::collection($result);
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $this->authorize('create', Application::class);

        $posting = JobPosting::findOrFail($request->validated('job_posting_id'));

        $application = $this->service->apply(
            $posting,
            $request->validated('candidate'),
            $request->only(['cover_letter', 'source']),
            $request->file('resume'),
        );

        return (new ApplicationResource($application->load('candidate')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Application $application): ApplicationResource
    {
        $this->authorize('view', $application);

        return new ApplicationResource($this->service->show($application));
    }

    public function review(Request $request, Application $application): ApplicationResource
    {
        $this->authorize('review', $application);

        return new ApplicationResource($this->service->review($application, $request->user()));
    }

    public function shortlist(Request $request, Application $application): ApplicationResource
    {
        $this->authorize('shortlist', $application);

        return new ApplicationResource($this->service->shortlist($application, $request->user()));
    }

    public function reject(Request $request, Application $application): ApplicationResource
    {
        $this->authorize('reject', $application);

        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return new ApplicationResource(
            $this->service->reject($application, $request->user(), $request->input('reason')),
        );
    }

    public function withdraw(Request $request, Application $application): ApplicationResource
    {
        $this->authorize('view', $application);

        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return new ApplicationResource(
            $this->service->withdraw($application, $request->input('reason')),
        );
    }

    public function destroy(Request $request, Application $application): JsonResponse
    {
        $this->authorize('delete', $application);

        $this->service->delete($application);

        return response()->json([], 204);
    }
}
