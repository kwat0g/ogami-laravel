<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Domains\HR\Recruitment\Services\RequisitionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Recruitment\StoreRequisitionRequest;
use App\Http\Requests\HR\Recruitment\UpdateRequisitionRequest;
use App\Http\Resources\HR\Recruitment\RequisitionListResource;
use App\Http\Resources\HR\Recruitment\RequisitionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RequisitionController extends Controller
{
    public function __construct(
        private readonly RequisitionService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('recruitment.requisitions.view'), 403);

        $result = $this->service->list(
            $request->only(['status', 'department_id', 'requested_by', 'search']),
            (int) $request->query('per_page', '25'),
            $request->user(),
        );

        return RequisitionListResource::collection($result);
    }

    public function store(StoreRequisitionRequest $request): JsonResponse
    {
        $requisition = $this->service->create($request->validated(), $request->user());

        return (new RequisitionResource($requisition))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, JobRequisition $requisition): RequisitionResource
    {
        abort_unless($request->user()->can('recruitment.requisitions.view'), 403);

        return new RequisitionResource($this->service->show($requisition));
    }

    public function update(UpdateRequisitionRequest $request, JobRequisition $requisition): RequisitionResource
    {
        $requisition = $this->service->update($requisition, $request->validated(), $request->user());

        return new RequisitionResource($requisition);
    }

    public function submit(Request $request, JobRequisition $requisition): RequisitionResource
    {
        abort_unless($request->user()->can('recruitment.requisitions.submit'), 403);

        $requisition = $this->service->submit($requisition, $request->user());

        return new RequisitionResource($requisition);
    }

    public function approve(Request $request, JobRequisition $requisition): RequisitionResource
    {
        abort_unless($request->user()->can('recruitment.requisitions.approve'), 403);

        $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        $requisition = $this->service->approve($requisition, $request->user(), $request->input('remarks'));

        return new RequisitionResource($requisition);
    }

    public function reject(Request $request, JobRequisition $requisition): RequisitionResource
    {
        abort_unless($request->user()->can('recruitment.requisitions.reject'), 403);

        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $requisition = $this->service->reject($requisition, $request->user(), $request->input('reason'));

        return new RequisitionResource($requisition);
    }

    public function cancel(Request $request, JobRequisition $requisition): RequisitionResource
    {
        abort_unless($request->user()->can('recruitment.requisitions.cancel'), 403);

        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $requisition = $this->service->cancel($requisition, $request->user(), $request->input('reason'));

        return new RequisitionResource($requisition);
    }
}
