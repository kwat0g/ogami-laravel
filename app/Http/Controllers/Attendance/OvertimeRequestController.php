<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Models\OvertimeRequest;
use App\Domains\Attendance\Services\OvertimeRequestService;
use App\Domains\HR\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreOvertimeRequestRequest;
use App\Http\Resources\Attendance\OvertimeRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OvertimeRequestController extends Controller
{
    public function __construct(
        private readonly OvertimeRequestService $service,
    ) {}

    /**
     * GET /api/v1/attendance/overtime-requests
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OvertimeRequest::class);

        $requests = OvertimeRequest::with('employee')
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date_from'), fn ($q, $d) => $q->where('work_date', '>=', $d))
            ->when($request->query('date_to'), fn ($q, $d) => $q->where('work_date', '<=', $d))
            ->when($request->query('department_id'), fn ($q, $id) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $id)))
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('employee', function ($e) use ($search) {
                    $e->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate((int) $request->query('per_page', '15'));

        return OvertimeRequestResource::collection($requests);
    }

    /**
     * GET /api/v1/attendance/overtime-requests/team
     * Department-scoped overtime requests for team managers.
     */
    public function team(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewTeam', OvertimeRequest::class);

        $user = $request->user();
        $departmentIds = $user->departments()->pluck('departments.id')->toArray();

        $requests = OvertimeRequest::with('employee')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date_from'), fn ($q, $d) => $q->where('work_date', '>=', $d))
            ->when($request->query('date_to'), fn ($q, $d) => $q->where('work_date', '<=', $d))
            ->whereHas('employee', fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->latest()
            ->paginate((int) $request->query('per_page', '25'));

        return OvertimeRequestResource::collection($requests);
    }

    /**
     * POST /api/v1/attendance/overtime-requests
     */
    public function store(StoreOvertimeRequestRequest $request): JsonResponse
    {
        $employee = Employee::findOrFail($request->validated('employee_id'));
        $this->authorize('create', [OvertimeRequest::class, $employee]);

        $ot = $this->service->submit(
            $employee,
            $request->validated(),
            (int) $request->user()->id,
        );

        return (new OvertimeRequestResource($ot->load('employee')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/attendance/overtime-requests/{overtimeRequest}
     */
    public function show(OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('view', $overtimeRequest);

        return new OvertimeRequestResource($overtimeRequest->load('employee'));
    }

    /**
     * PATCH /api/v1/attendance/overtime-requests/{overtimeRequest}/supervisor-endorse
     * Supervisor first-level endorsement. Moves request to 'supervisor_approved'.
     */
    public function supervisorEndorse(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('supervise', $overtimeRequest);

        $updated = $this->service->supervisorEndorse(
            $overtimeRequest,
            (int) $request->user()->id,
            $request->input('remarks'),
        );

        return new OvertimeRequestResource($updated->load('employee'));
    }

    /**
     * PATCH /api/v1/attendance/overtime-requests/{overtimeRequest}/approve
     */
    public function approve(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('review', $overtimeRequest);

        $request->validate([
            'approved_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $updated = $this->service->approve(
            $overtimeRequest,
            (int) $request->user()->id,
            $request->integer('approved_minutes'),
            $request->input('remarks', ''),
        );

        return new OvertimeRequestResource($updated->load('employee'));
    }

    /**
     * PATCH /api/v1/attendance/overtime-requests/{overtimeRequest}/reject
     */
    public function reject(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('review', $overtimeRequest);

        $updated = $this->service->reject(
            $overtimeRequest,
            (int) $request->user()->id,
            $request->input('remarks', ''),
        );

        return new OvertimeRequestResource($updated->load('employee'));
    }

    /**
     * DELETE /api/v1/attendance/overtime-requests/{overtimeRequest}
     */
    public function cancel(OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->authorize('cancel', $overtimeRequest);

        $this->service->cancel($overtimeRequest);

        return response()->json(['message' => 'Overtime request cancelled.']);
    }

    /**
     * PATCH /api/v1/attendance/overtime-requests/{overtimeRequest}/executive-approve
     * Executive approval for manager-filed overtime requests.
     */
    public function executiveApprove(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('executiveApprove', $overtimeRequest);

        $request->validate([
            'approved_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $updated = $this->service->executiveApprove(
            $overtimeRequest,
            (int) $request->user()->id,
            $request->integer('approved_minutes'),
            $request->input('remarks', ''),
        );

        return new OvertimeRequestResource($updated->load('employee'));
    }

    /**
     * PATCH /api/v1/attendance/overtime-requests/{overtimeRequest}/executive-reject
     * Executive rejection for manager-filed overtime requests.
     */
    public function executiveReject(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('executiveApprove', $overtimeRequest);

        $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $updated = $this->service->executiveReject(
            $overtimeRequest,
            (int) $request->user()->id,
            $request->input('remarks'),
        );

        return new OvertimeRequestResource($updated->load('employee'));
    }

    /**
     * GET /api/v1/attendance/overtime-requests/pending-executive
     * Executive queue: manager-filed requests pending executive approval.
     */
    public function pendingExecutive(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewExecutiveQueue', OvertimeRequest::class);

        $requests = OvertimeRequest::with('employee')
            ->where('status', 'pending_executive')
            ->latest()
            ->paginate((int) $request->query('per_page', '25'));

        return OvertimeRequestResource::collection($requests);
    }

    /**
     * PATCH /api/v1/attendance/overtime-requests/{overtimeRequest}/officer-review
     * HR Officer review — step 4 of the 5-step OT approval flow.
     */
    public function officerReview(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('review', $overtimeRequest);

        $request->validate([
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $updated = $this->service->officerReview(
            $overtimeRequest,
            (int) $request->user()->id,
            $request->input('remarks', ''),
        );

        return new OvertimeRequestResource($updated->load('employee'));
    }

    /**
     * PATCH /api/v1/attendance/overtime-requests/{overtimeRequest}/vp-approve
     * VP final approval — step 5 of the 5-step OT approval flow.
     */
    public function vpApprove(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('vpApprove', $overtimeRequest);

        $request->validate([
            'approved_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:480'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $updated = $this->service->vpApprove(
            $overtimeRequest,
            (int) $request->user()->id,
            $request->integer('approved_minutes') ?: null,
            $request->input('remarks', ''),
        );

        return new OvertimeRequestResource($updated->load('employee'));
    }
}
