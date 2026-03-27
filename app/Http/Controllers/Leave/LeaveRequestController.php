<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leave;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Leave\Services\LeaveRequestService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ProcessLeaveRequestRequest;
use App\Http\Requests\Leave\StoreLeaveRequestRequest;
use App\Http\Resources\Leave\LeaveBalanceResource;
use App\Http\Resources\Leave\LeaveRequestResource;
use App\Models\HolidayCalendar;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Leave request lifecycle controller.
 * Thin adapter: validation → service → resource.
 */
final class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestService $service,
    ) {}

    /**
     * GET /api/v1/leave/requests
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $requests = LeaveRequest::with(['leaveType', 'employee'])
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('year'), fn ($q, $y) => $q->whereYear('date_from', $y))
            ->when($request->query('department_id'), fn ($q, $id) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $id)))
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('employee', function ($e) use ($search) {
                    $e->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate((int) $request->query('per_page', '15'));

        return LeaveRequestResource::collection($requests);
    }

    /**
     * POST /api/v1/leave/requests
     */
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $employee = Employee::findOrFail($request->validated('employee_id'));
        $this->authorize('create', [LeaveRequest::class, $employee]);

        $leaveRequest = $this->service->submit(
            $employee,
            $request->validated(),
            (int) $request->user()->id,
        );

        return (new LeaveRequestResource($leaveRequest->load('leaveType')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/leave/requests/{leaveRequest}
     */
    public function show(LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('view', $leaveRequest);

        return new LeaveRequestResource($leaveRequest->load('leaveType'));
    }

    /**
     * PATCH /api/v1/leave/requests/{leaveRequest}/head-approve
     * Step 2 — Department Head approval.
     */
    public function headApprove(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('headApprove', $leaveRequest);

        $updated = $this->service->headApprove(
            $leaveRequest,
            (int) $request->user()->id,
            $request->input('remarks'),
        );

        return new LeaveRequestResource($updated->load('leaveType'));
    }

    /**
     * PATCH /api/v1/leave/requests/{leaveRequest}/manager-check
     * Step 3 — Plant Manager check.
     */
    public function managerCheck(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('managerCheck', $leaveRequest);

        $updated = $this->service->managerCheck(
            $leaveRequest,
            (int) $request->user()->id,
            $request->input('remarks'),
        );

        return new LeaveRequestResource($updated->load('leaveType'));
    }

    /**
     * PATCH /api/v1/leave/requests/{leaveRequest}/ga-process
     * Step 4 — GA Officer processes (sets action_taken + balance snapshot).
     */
    public function gaProcess(ProcessLeaveRequestRequest $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('gaProcess', $leaveRequest);

        $updated = $this->service->gaProcess(
            $leaveRequest,
            (int) $request->user()->id,
            $request->validated('action_taken'),
            $request->input('remarks'),
        );

        return new LeaveRequestResource($updated->load('leaveType'));
    }

    /**
     * PATCH /api/v1/leave/requests/{leaveRequest}/vp-note
     * Step 5 — Vice President notes (deducts balance for approved_with_pay).
     */
    public function vpNote(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('vpNote', $leaveRequest);

        $updated = $this->service->vpNote(
            $leaveRequest,
            (int) $request->user()->id,
            $request->input('remarks'),
        );

        return new LeaveRequestResource($updated->load('leaveType'));
    }

    /**
     * PATCH /api/v1/leave/requests/{leaveRequest}/reject
     * Any current-step approver can reject.
     */
    public function reject(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('review', $leaveRequest);

        $updated = $this->service->reject(
            $leaveRequest,
            (int) $request->user()->id,
            $request->input('remarks', ''),
        );

        return new LeaveRequestResource($updated->load('leaveType'));
    }

    /**
     * PATCH /api/v1/leave/requests/batch-head-approve
     * Batch head-approve multiple pending leave requests.
     */
    public function batchHeadApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids'     => ['required', 'array', 'min:1', 'max:50'],
            'ids.*'   => ['integer', 'exists:leave_requests,id'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $userId  = (int) $request->user()->id;
        $remarks = $request->input('remarks');
        $results = ['approved' => [], 'failed' => []];

        foreach ($request->input('ids') as $id) {
            try {
                $leaveRequest = LeaveRequest::findOrFail($id);
                $this->authorize('headApprove', $leaveRequest);
                $this->service->headApprove($leaveRequest, $userId, $remarks);
                $results['approved'][] = $id;
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'id'      => $id,
                    'reason'  => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => count($results['approved']) . ' request(s) approved.',
            'results' => $results,
        ]);
    }

    /**
     * PATCH /api/v1/leave/requests/batch-reject
     * Batch reject multiple pending leave requests.
     */
    public function batchReject(Request $request): JsonResponse
    {
        $request->validate([
            'ids'     => ['required', 'array', 'min:1', 'max:50'],
            'ids.*'   => ['integer', 'exists:leave_requests,id'],
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $userId  = (int) $request->user()->id;
        $remarks = $request->input('remarks');
        $results = ['rejected' => [], 'failed' => []];

        foreach ($request->input('ids') as $id) {
            try {
                $leaveRequest = LeaveRequest::findOrFail($id);
                $this->authorize('review', $leaveRequest);
                $this->service->reject($leaveRequest, $userId, $remarks);
                $results['rejected'][] = $id;
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'id'      => $id,
                    'reason'  => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => count($results['rejected']) . ' request(s) rejected.',
            'results' => $results,
        ]);
    }

    /**
     * DELETE /api/v1/leave/requests/{leaveRequest}
     */
    public function cancel(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('cancel', $leaveRequest);

        $this->service->cancel($leaveRequest);

        return response()->json(['message' => 'Leave request cancelled.']);
    }

    /**
     * GET /api/v1/leave/balances
     * Filter by employee_id and/or year.
     */
    public function balances(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveBalance::class);

        $year = (int) $request->query('year', date('Y'));
        $departmentId = $request->query('department_id');
        $employeeId = $request->query('employee_id');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', '15');

        // Get all leave types for reference
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();

        // Base query: get all active employees with their leave balances for the year
        // Use withoutDepartmentScope to show employees from all departments (HR view)
        // Only show employees who are fully active (is_active = true)
        $query = Employee::withoutDepartmentScope()
            ->where('employees.is_active', true)
            ->when($employeeId, fn ($q, $id) => $q->where('employees.id', $id))
            ->when($departmentId, fn ($q) => $q->where('employees.department_id', $departmentId))
            ->when($search, function ($q, $search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('employees.first_name', 'ilike', "%{$search}%")
                        ->orWhere('employees.last_name', 'ilike', "%{$search}%");
                });
            })
            ->with(['department', 'leaveBalances' => fn ($q) => $q->where('year', $year)->with('leaveType')])
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');

        $employees = $query->paginate($perPage);

        // Transform to include balance info for each employee
        $data = $employees->map(function ($employee) use ($year, $leaveTypes) {
            $balances = [];

            foreach ($leaveTypes as $leaveType) {
                $balance = $employee->leaveBalances->firstWhere('leave_type_id', $leaveType->id);

                $balances[] = [
                    'leave_type_id' => $leaveType->id,
                    'leave_type_name' => $leaveType->name,
                    'leave_type_code' => $leaveType->code,
                    'has_balance' => $balance !== null,
                    'balance' => $balance ? $balance->balance : 0,
                    'opening_balance' => $balance ? $balance->opening_balance : 0,
                    'accrued' => $balance ? $balance->accrued : 0,
                    'adjusted' => $balance ? $balance->adjusted : 0,
                    'used' => $balance ? $balance->used : 0,
                    'balance_id' => $balance ? $balance->id : null,
                ];
            }

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'department_id' => $employee->department_id,
                'department_name' => $employee->department?->name,
                'year' => $year,
                'balances' => $balances,
                'total_balance' => collect($balances)->sum('balance'),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
            'leave_types' => $leaveTypes->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'code' => $t->code]),
        ]);
    }

    /**
     * POST /api/v1/leave/balances
     * Create or update a leave balance entry.
     * Uses firstOrCreate to allow HR to grant special leave (SPL, VAWCL)
     * even if balance record already exists with 0 values.
     */
    public function storeBalance(Request $request): JsonResponse
    {
        $this->authorize('create', LeaveBalance::class);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'required|integer|min:2000|max:2100',
            'opening_balance' => 'required|numeric|min:0',
            'accrued' => 'nullable|numeric|min:0',
            'adjusted' => 'nullable|numeric',
            'used' => 'nullable|numeric|min:0',
        ]);

        $validated['accrued'] = $validated['accrued'] ?? 0;
        $validated['adjusted'] = $validated['adjusted'] ?? 0;
        $validated['used'] = $validated['used'] ?? 0;

        // Use firstOrCreate to handle both new and existing records
        $balance = LeaveBalance::firstOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'year' => $validated['year'],
            ],
            [
                'opening_balance' => $validated['opening_balance'],
                'accrued' => $validated['accrued'],
                'adjusted' => $validated['adjusted'],
                'used' => $validated['used'],
            ]
        );

        // If record already existed, update the opening_balance (for granting special leave)
        if (! $balance->wasRecentlyCreated) {
            $balance->opening_balance = $validated['opening_balance'];
            $balance->save();
        }

        return (new LeaveBalanceResource($balance->load(['leaveType', 'employee'])))
            ->response()
            ->setStatusCode($balance->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * PATCH /api/v1/leave/balances/{leaveBalance}
     * Update an existing leave balance.
     */
    public function updateBalance(Request $request, LeaveBalance $leaveBalance): LeaveBalanceResource
    {
        $this->authorize('update', $leaveBalance);

        $validated = $request->validate([
            'opening_balance' => 'sometimes|numeric|min:0',
            'accrued' => 'sometimes|numeric|min:0',
            'adjusted' => 'sometimes|numeric',
            'used' => 'sometimes|numeric|min:0',
        ]);

        $leaveBalance->update($validated);

        return new LeaveBalanceResource($leaveBalance->load(['leaveType', 'employee']));
    }

    /**
     * GET /api/v1/leave/requests/team
     * Department-scoped leave requests for team managers.
     */
    public function team(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewTeam', LeaveRequest::class);

        $user = $request->user();
        $departmentIds = $user->departments()->pluck('departments.id')->toArray();

        $requests = LeaveRequest::with(['leaveType', 'employee'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('year'), fn ($q, $y) => $q->whereYear('date_from', $y))
            ->whereHas('employee', fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->latest()
            ->paginate((int) $request->query('per_page', '25'));

        return LeaveRequestResource::collection($requests);
    }

    /**
     * GET /api/v1/leave/calendar
     *
     * Returns a monthly calendar view of approved leave requests and public
     * holidays for the given department and month.
     *
     * Query params:
     *   year         int  (default: current year)
     *   month        int  (default: current month, 1–12)
     *   department_id int (optional; defaults to user's own department)
     */
    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $year = $request->integer('year', now()->year);
        $month = max(1, min(12, $request->integer('month', now()->month)));
        $departmentId = $request->integer('department_id', 0) ?: null;

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        // Approved leave requests in the month, optionally scoped to a department
        $requests = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'approved')
            ->where('date_from', '<=', $monthEnd)
            ->where('date_to', '>=', $monthStart)
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->orderBy('date_from')
            ->get()
            ->map(fn (LeaveRequest $lr) => [
                'id' => $lr->id,
                'employee_id' => $lr->employee_id,
                'employee_name' => $lr->employee ? "{$lr->employee->first_name} {$lr->employee->last_name}" : null,
                'leave_type' => $lr->leaveType?->name,
                'date_from' => $lr->date_from->toDateString(),
                'date_to' => $lr->date_to->toDateString(),
                'leave_days' => $lr->leave_days,
                'is_paid' => (bool) $lr->leaveType?->is_paid,
            ]);

        // Public holidays in the month
        $holidays = HolidayCalendar::whereBetween('holiday_date', [$monthStart, $monthEnd])
            ->orderBy('holiday_date')
            ->get(['holiday_date', 'name', 'holiday_type'])
            ->map(fn ($h) => [
                'date' => $h->holiday_date->toDateString(),
                'name' => $h->name,
                'type' => $h->holiday_type,
            ]);

        return response()->json([
            'data' => [
                'year' => $year,
                'month' => $month,
                'month_start' => $monthStart,
                'month_end' => $monthEnd,
                'leave_events' => $requests,
                'holidays' => $holidays,
            ],
        ]);
    }
}
