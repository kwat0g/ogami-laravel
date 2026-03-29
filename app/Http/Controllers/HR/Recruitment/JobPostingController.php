<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Domains\HR\Recruitment\Services\JobPostingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Recruitment\StoreJobPostingRequest;
use App\Http\Resources\HR\Recruitment\JobPostingListResource;
use App\Http\Resources\HR\Recruitment\JobPostingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class JobPostingController extends Controller
{
    public function __construct(
        private readonly JobPostingService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', JobPosting::class);

        $result = $this->service->list(
            $request->only(['status', 'job_requisition_id', 'search']),
            (int) $request->query('per_page', '25'),
        );

        return JobPostingListResource::collection($result);
    }

    public function store(StoreJobPostingRequest $request): JsonResponse
    {
        $requisition = JobRequisition::findOrFail($request->validated('job_requisition_id'));
        $posting = $this->service->createFromRequisition($requisition, $request->validated(), $request->user());

        return (new JobPostingResource($posting))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, JobPosting $posting): JobPostingResource
    {
        $this->authorize('viewAny', JobPosting::class);

        return new JobPostingResource($this->service->show($posting));
    }

    public function update(Request $request, JobPosting $posting): JobPostingResource
    {
        $this->authorize('update', $posting);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'min:50'],
            'requirements' => ['sometimes', 'string', 'min:20'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_internal' => ['sometimes', 'boolean'],
            'is_external' => ['sometimes', 'boolean'],
            'closes_at' => ['nullable', 'date', 'after:today'],
        ]);

        return new JobPostingResource($this->service->update($posting, $data));
    }

    public function publish(Request $request, JobPosting $posting): JobPostingResource
    {
        $this->authorize('publish', $posting);

        return new JobPostingResource($this->service->publish($posting, $request->user()));
    }

    public function close(Request $request, JobPosting $posting): JobPostingResource
    {
        $this->authorize('close', $posting);

        return new JobPostingResource($this->service->close($posting, $request->user()));
    }

    public function reopen(Request $request, JobPosting $posting): JobPostingResource
    {
        $this->authorize('publish', $posting);

        return new JobPostingResource($this->service->reopen($posting, $request->user()));
    }
}
