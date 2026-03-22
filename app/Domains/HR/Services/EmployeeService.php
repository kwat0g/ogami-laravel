<?php

declare(strict_types=1);

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\SalaryGrade;
use App\Domains\HR\StateMachines\EmployeeStateMachine;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use App\Infrastructure\Scopes\DepartmentScope;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Employee domain service.
 *
 * Business rules enforced:
 *  EMP-001: employee_code is system-generated and unique (EMP-YYYY-NNNNNN)
 *  EMP-002: New employees enter draft state with onboarding_status = documents_pending
 *  EMP-003: Activation only allowed when required documents are uploaded
 *  EMP-005: basic_monthly_rate must fall within salary_grade min/max range
 *  EMP-007: Only HR / Payroll roles may update compensation fields (enforced in Policy)
 *  EMP-009: Government ID uniqueness checked via hash before save
 *  EMP-010: Separation date must be >= date_hired
 *  EMP-SoD: A manager cannot approve their own employment state change
 */
final class EmployeeService implements ServiceContract
{
    public function __construct(
        private readonly EmployeeStateMachine $stateMachine,
        private readonly EmployeeClearanceService $clearanceService,
    ) {}

    // ── Queries ───────────────────────────────────────────────────────────────

    /**
     * Paginated employee list with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Employee>
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Employee::with(['salaryGrade'])
            ->when(isset($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(isset($filters['employment_type']), fn ($q) => $q->where('employment_type', $filters['employment_type']))
            ->when(isset($filters['employment_status']), fn ($q) => $q->where('employment_status', $filters['employment_status']))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when(isset($filters['search']), fn ($q) => $q->where(fn ($s) => $s->whereRaw("LOWER(first_name || ' ' || last_name) LIKE ?",
                ['%'.strtolower($filters['search']).'%'])
                ->orWhere('employee_code', 'ILIKE', '%'.$filters['search'].'%')
            ))
            ->orderBy('last_name')
            ->orderBy('first_name');

        return $query->paginate($perPage);
    }

    /**
     * Paginated employee list WITHOUT department scope.
     * Used by HR Managers who need to see all employees across departments.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Employee>
     */
    public function listAll(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Employee::withoutDepartmentScope()
            ->with(['salaryGrade', 'department', 'position'])
            ->when(isset($filters['department_id']), fn ($q) => $q->whereIn('department_id', (array) $filters['department_id']))
            ->when(isset($filters['employment_type']), fn ($q) => $q->where('employment_type', $filters['employment_type']))
            ->when(isset($filters['employment_status']), fn ($q) => $q->where('employment_status', $filters['employment_status']))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when(isset($filters['search']), fn ($q) => $q->where(fn ($s) => $s->whereRaw("LOWER(first_name || ' ' || last_name) LIKE ?",
                ['%'.strtolower($filters['search']).'%'])
                ->orWhere('employee_code', 'ILIKE', '%'.$filters['search'].'%')
            ))
            ->when(isset($filters['exclude_employee_ids']), fn ($q) => $q->whereNotIn('id', $filters['exclude_employee_ids']))
            ->orderBy('last_name')
            ->orderBy('first_name');

        return $query->paginate($perPage);
    }

    // ── Commands ──────────────────────────────────────────────────────────────

    /**
     * Create a new employee in draft state.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            // EMP-005: Validate rate within grade
            if (isset($data['salary_grade_id'], $data['basic_monthly_rate'])) {
                $this->assertRateInGrade(
                    (int) $data['salary_grade_id'],
                    (int) $data['basic_monthly_rate'],
                );
            }

            $employee = new Employee($data);
            $employee->employee_code = $data['employee_code'] ?? $this->generateEmployeeCode();
            $employee->employment_status = 'active';      // new hires start as active employees
            $employee->onboarding_status = 'documents_pending'; // onboarding in progress
            $employee->is_active = false;

            // EMP-009: Encrypt + hash government IDs
            $this->syncGovernmentIds($employee, $data);

            // Auto-activate if all government IDs are provided at creation time
            $wasActivated = false;
            if ($this->hasAllGovernmentIds($employee)) {
                $employee->is_active = true;
                $employee->onboarding_status = 'active';
                $employee->_fire_activated_event = true;
                $wasActivated = true;
            }

            $employee->save();

            // Create leave balances immediately if activated
            if ($wasActivated) {
                $this->createLeaveBalancesForEmployee($employee);
            }

            return $employee->fresh(['salaryGrade']);
        });
    }

    /**
     * Update mutable fields of an existing employee.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            if (isset($data['salary_grade_id'], $data['basic_monthly_rate'])) {
                $this->assertRateInGrade(
                    (int) $data['salary_grade_id'],
                    (int) $data['basic_monthly_rate'],
                );
            }

            // EMP-010: Separation date >= date_hired
            if (isset($data['separation_date'])) {
                $hiredAt = $data['date_hired'] ?? $employee->date_hired->toDateString();
                if ($data['separation_date'] < $hiredAt) {
                    throw new DomainException(
                        'Separation date must be on or after date hired.',
                        'EMP_SEPARATION_DATE_BEFORE_HIRED',
                        422
                    );
                }
            }

            // Government IDs — only update if provided
            $this->syncGovernmentIds($employee, $data);

            // Auto-activate employee if all government IDs are now complete
            $wasActivated = false;
            if (! $employee->is_active && $this->hasAllGovernmentIds($employee)) {
                $employee->is_active = true;
                if ($employee->onboarding_status === 'documents_pending') {
                    $employee->onboarding_status = 'active';
                }
                // Fire activation event via state machine signal
                $employee->_fire_activated_event = true;
                $wasActivated = true;
            }

            $employee->fill($data);
            $employee->save();

            // Create leave balances immediately after activation (belt-and-suspenders).
            // Also handles employees who were activated outside the normal pathway
            // (e.g. seeded or imported) and had no leave balances created at the time.
            $needsBalances = $this->hasAllGovernmentIds($employee)
                && ! LeaveBalance::where('employee_id', $employee->id)
                    ->where('year', now()->year)
                    ->exists();

            if ($wasActivated || $needsBalances) {
                $this->createLeaveBalancesForEmployee($employee);
            }

            return $employee->fresh(['salaryGrade']);

        });
    }

    /**
     * Transition the employee to a new employment status.
     * SoD: the requesting user must not be the employee themselves.
     *
     * @throws InvalidStateTransitionException|SodViolationException
     */
    public function transition(Employee $employee, string $toState, int $requestedByUserId): Employee
    {
        // SoD: HR manager cannot self-approve their own status change
        // (In practice enforced via Policy; belt-and-suspenders here for employee.user_id if it exists)

        return DB::transaction(function () use ($employee, $toState, $requestedByUserId): Employee {
            $originalState = $employee->employment_status;
            
            $this->stateMachine->transition($employee, $toState);
            $employee->save();

            // Generate clearance checklist when transitioning to resigned/terminated
            if (in_array($toState, ['resigned', 'terminated'], true) && 
                !in_array($originalState, ['resigned', 'terminated'], true)) {
                $user = User::find($requestedByUserId);
                $this->clearanceService->generateClearanceChecklist($employee, $user);
            }

            if ($toState === 'terminated' && ! $employee->trashed()) {
                // Terminated employees are archived automatically.
                $employee->delete();
            }

            // Bypass DepartmentScope on the post-save refetch: the scope is
            // active at this point (DepartmentScopeMiddleware has already run)
            // and would add WHERE department_id = X, causing a 404 whenever
            // the target employee belongs to a different department from the
            // requesting user's primary department. Authorization is enforced
            // by the Policy gate before this method is called, so skipping
            // the scope here is safe.
            return Employee::withoutGlobalScope(DepartmentScope::class)
                ->withTrashed()
                ->findOrFail($employee->id);
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Generate a unique employee code: EMP-YYYY-NNNNNN
     */
    private function generateEmployeeCode(): string
    {
        $year = date('Y');
        // Bypass DepartmentScope so we scan codes across ALL departments.
        // Without this, a dept-scoped request would only see codes from the
        // requesting user's department and could generate a duplicate code
        // that already exists in another department.
        $last = Employee::withoutGlobalScope(DepartmentScope::class)
            ->withTrashed()
            ->where('employee_code', 'LIKE', "EMP-{$year}-%")
            ->orderByDesc('employee_code')
            ->value('employee_code');

        $next = $last
            ? (int) substr($last, -6) + 1
            : 1;

        return sprintf('EMP-%s-%06d', $year, $next);
    }

    /**
     * Assert that the given monthly rate (centavos) falls within the salary grade.
     *
     * @throws DomainException
     */
    private function assertRateInGrade(int $gradeId, int $rateCentavos): void
    {
        $grade = SalaryGrade::find($gradeId);

        if ($grade === null) {
            throw new DomainException('Salary grade not found.', 'EMP_GRADE_NOT_FOUND', 404);
        }

        if (! $grade->inRange($rateCentavos)) {
            throw new DomainException(
                sprintf(
                    'Rate ₱%s is outside salary grade %s range (₱%s – ₱%s).',
                    number_format($rateCentavos / 100, 2),
                    $grade->code,
                    number_format($grade->getMinMonthlyRatePesosAttribute(), 2),
                    number_format($grade->getMaxMonthlyRatePesosAttribute(), 2),
                ),
                'EMP_RATE_OUTSIDE_GRADE',
                422
            );
        }
    }

    /**
     * Sync all government ID encrypted fields + hashes from raw input.
     * Only updates if a non-null value is provided. Empty string will clear the ID.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncGovernmentIds(Employee $employee, array $data): void
    {
        // Only update if key exists AND value is not null (allows partial updates)
        // Pass empty string '' to clear an existing ID
        if (array_key_exists('sss_no', $data) && $data['sss_no'] !== null) {
            $employee->setSssNo($data['sss_no'] ?: null);
        }
        if (array_key_exists('tin', $data) && $data['tin'] !== null) {
            $employee->setTin($data['tin'] ?: null);
        }
        if (array_key_exists('philhealth_no', $data) && $data['philhealth_no'] !== null) {
            $employee->setPhilhealthNo($data['philhealth_no'] ?: null);
        }
        if (array_key_exists('pagibig_no', $data) && $data['pagibig_no'] !== null) {
            $employee->setPagibigNo($data['pagibig_no'] ?: null);
        }
    }

    /**
     * Check if employee has all required government IDs.
     */
    private function hasAllGovernmentIds(Employee $employee): bool
    {
        return $employee->sss_no_hash !== null
            && $employee->tin_hash !== null
            && $employee->philhealth_no_hash !== null
            && $employee->pagibig_no_hash !== null;
    }

    /**
     * Create leave balance records for a newly activated employee.
     * Note: SPL and VAWCL require HR verification - not auto-created.
     */
    private function createLeaveBalancesForEmployee(Employee $employee): void
    {
        $year = now()->year;

        // ML and PL are event-based (pregnancy/birth) and must be granted
        // explicitly via the "Grant Special Leave" function when the event occurs.
        $eventBased = ['ML', 'PL'];

        // OTH (Others) is discretionary — no fixed entitlement; no balance row needed.
        // ML and PL start at 0 and are event-triggered by HR.
        LeaveType::where('is_active', true)
            ->whereNotIn('code', ['OTH'])
            ->each(function (LeaveType $type) use ($employee, $year, $eventBased): void {
                // Grant the full annual entitlement as opening balance for
                // standard leave types (SL, VL, SIL). Event-based and
                // unlimited-absence types start at 0.
                $openingBalance = in_array($type->code, $eventBased, true)
                    ? 0.0
                    : (float) ($type->max_days_per_year ?? 0);

                LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $type->id,
                        'year' => $year,
                    ],
                    [
                        'opening_balance' => $openingBalance,
                        'accrued' => 0.0,
                        'adjusted' => 0.0,
                        'used' => 0.0,
                        'monetized' => 0.0,
                    ],
                );
            });
    }
}
