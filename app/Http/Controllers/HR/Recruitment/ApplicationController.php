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
        abort_unless($request->user()->can('recruitment.applications.view'), 403);

        $result = $this->service->list(
            $request->only(['job_posting_id', 'status', 'candidate_id', 'search']),
            (int) $request->query('per_page', '25'),
        );

        return ApplicationListResource::collection($result);
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $posting = JobPosting::findOrFail($request->validated('job_posting_id'));

        $application = $this->service->apply(
            $posting,
            $request->validated('candidate'),
            $request->only(['cover_letter', 'source']),
        );

        return (new ApplicationResource($application->load('candidate')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Application $application): ApplicationResource
    {
        abort_unless($request->user()->can('recruitment.applications.view'), 403);

        return new ApplicationResource($this->service->show($application));
    }

    public function review(Request $request, Application $application): ApplicationResource
    {
        abort_unless($request->user()->can('recruitment.applications.review'), 403);

        return new ApplicationResource($this->service->review($application, $request->user()));
    }

    public function shortlist(Request $request, Application $application): ApplicationResource
    {
        abort_unless($request->user()->can('recruitment.applications.shortlist'), 403);

        return new ApplicationResource($this->service->shortlist($application, $request->user()));
    }

    public function reject(Request $request, Application $application): ApplicationResource
    {
        abort_unless($request->user()->can('recruitment.applications.reject'), 403);

        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return new ApplicationResource(
            $this->service->reject($application, $request->user(), $request->input('reason')),
        );
    }

    public function withdraw(Request $request, Application $application): ApplicationResource
    {
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return new ApplicationResource(
            $this->service->withdraw($application, $request->input('reason')),
        );
    }
}
