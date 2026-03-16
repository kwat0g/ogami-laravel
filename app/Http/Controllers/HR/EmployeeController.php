<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Services\EmployeeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Http\Requests\HR\UpdateEmployeeRequest;
use App\Http\Resources\HR\EmployeeListResource;
use App\Http\Resources\HR\EmployeeResource;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Thin HTTP adapter for Employee domain.
 * Validation → Service → Resource transform only; no business logic here.
 */
final class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    /**
     * GET /api/v1/hr/employees
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        $filters = $request->only([
            'department_id',
            'employment_type',
            'employment_status',
            'search',
        ]);

        if ($request->has('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        // HR Managers need to see all employees across departments
        $employees = $this->employeeService->listAll(
            $filters,
            (int) $request->query('per_page', '25'),
        );

        return EmployeeListResource::collection($employees);
    }

    /**
     * GET /api/v1/hr/employees/team
     * Department-scoped employee list for managers/supervisors.
     */
    public function team(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewTeam', Employee::class);

        $filters = $request->only([
            'employment_type',
            'employment_status',
            'search',
        ]);

        // Restrict to user's departments only
        $departmentIds = $request->user()->departments()->pluck('departments.id')->toArray();
        if (! empty($departmentIds)) {
            $filters['department_id'] = $departmentIds;
        }

        // Supervisors must not see themselves or higher-role employees (managers, executives, admins)
        /** @var Employee|null $currentEmployee */
        $currentEmployee = $request->user()->employee;
        $excludeEmployeeIds = $currentEmployee instanceof Employee ? [$currentEmployee->id] : [];

        $higherRoleUserIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['admin', 'executive', 'manager', 'officer'])
            ->where('model_has_roles.model_type', User::class)
            ->pluck('model_has_roles.model_id')
            ->all();

        if (! empty($higherRoleUserIds)) {
            $managerEmployeeIds = Employee::whereIn('user_id', $higherRoleUserIds)
                ->pluck('id')
                ->all();
            $excludeEmployeeIds = array_unique(array_merge($excludeEmployeeIds, $managerEmployeeIds));
        }

        if (! empty($excludeEmployeeIds)) {
            $filters['exclude_employee_ids'] = $excludeEmployeeIds;
        }

        $employees = $this->employeeService->listAll(
            $filters,
            (int) $request->query('per_page', '25'),
        );

        return EmployeeListResource::collection($employees);
    }

    /**
     * POST /api/v1/hr/employees
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeService->create($request->validated());

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/hr/employees/{employee}
     */
    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        $employee->load(['salaryGrade', 'department', 'position', 'supervisor', 'shiftAssignments.shiftSchedule']);

        return new EmployeeResource($employee);
    }

    /**
     * PATCH /api/v1/hr/employees/{employee}
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource|JsonResponse
    {
        try {
            $employee = $this->employeeService->update($employee, $request->validated());

            return new EmployeeResource($employee);
        } catch (UniqueConstraintViolationException $e) {
            // Safety-net: closure validators in UpdateEmployeeRequest already catch duplicates
            // at validation time. This block only fires if the DB constraint is hit directly
            // (e.g. concurrent save race condition).
            $errMsg = $e->getMessage();

            [$field, $message] = match (true) {
                str_contains($errMsg, 'sss_no_hash') => ['sss_no',        'This SSS number is already registered to another employee.'],
                str_contains($errMsg, 'tin_hash') => ['tin',           'This TIN is already registered to another employee.'],
                str_contains($errMsg, 'philhealth_no_hash') => ['philhealth_no', 'This PhilHealth number is already registered to another employee.'],
                str_contains($errMsg, 'pagibig_no_hash') => ['pagibig_no',    'This Pag-IBIG number is already registered to another employee.'],
                default => ['',             'A duplicate government ID conflict occurred.'],
            };

            $body = [
                'success' => false,
                'error_code' => 'DUPLICATE_GOVERNMENT_ID',
                'message' => $message,
            ];

            // Attach a field-level error key so the frontend form can highlight
            // the exact input that caused the conflict.
            if ($field !== '') {
                $body['errors'] = [$field => [$message]];
            }

            return response()->json($body, 422);
        }
    }

    /**
     * POST /api/v1/hr/employees/{employee}/transition
     * Body: { "to_state": "active" }
     */
    public function transition(Request $request, Employee $employee): EmployeeResource
    {
        $this->authorize('transition', $employee);

        $validated = $request->validate([
            'to_state' => ['required', 'string', 'in:active,on_leave,suspended,resigned,terminated'],
        ]);

        $employee = $this->employeeService->transition(
            $employee,
            $validated['to_state'],
            $request->user()->id,
        );

        return new EmployeeResource($employee);
    }
}
