<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Models\PayrollRunExclusion;
use App\Domains\Payroll\StateMachines\PayrollRunStateMachine;
use App\Shared\Contracts\ServiceContract;

/**
 * Handles Step 2 of the payroll run wizard: Employee Scope configuration.
 *
 * Responsibilities:
 * - Save scope filters (departments, positions, employment types)
 * - Enforce manual exclusions with reasons
 * - Return live scope preview counts (RDAC-aware)
 * - Transition run to SCOPE_SET
 */
final class PayrollScopeService implements ServiceContract
{
    public function __construct(
        private readonly PayrollRunStateMachine $stateMachine,
    ) {}

    /**
     * Save scope settings and transition run to SCOPE_SET.
     *
     * @param array{
     *   departments?: int[],
     *   positions?: int[],
     *   employment_types?: string[],
     *   include_unpaid_leave?: bool,
     *   include_probation_end?: bool,
     * } $scope
     */
    public function confirmScope(PayrollRun $run, array $scope): PayrollRun
    {
        $run->scope_departments = $scope['departments'] ?? null;
        $run->scope_positions = $scope['positions'] ?? null;
        $run->scope_employment_types = $scope['employment_types'] ?? null;
        $run->scope_include_unpaid_leave = $scope['include_unpaid_leave'] ?? false;
        $run->scope_include_probation_end = $scope['include_probation_end'] ?? false;
        $run->scope_confirmed_at = now();
        $run->save();

        // Guard: only transition if not already SCOPE_SET (re-saves from scope page)
        if ($run->status !== 'SCOPE_SET') {
            $this->stateMachine->transition($run, 'SCOPE_SET');
        }

        return $run->fresh();
    }

    /**
     * Add a manual exclusion for one employee in this run.
     * The reason is required and stored permanently.
     */
    public function addExclusion(PayrollRun $run, int $employeeId, string $reason, int $excludedById): PayrollRunExclusion
    {
        return PayrollRunExclusion::firstOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employeeId],
            [
                'reason' => $reason,
                'excluded_by_id' => $excludedById,
                'excluded_at' => now(),
            ],
        );
    }

    /**
     * Remove a manual exclusion.
     */
    public function removeExclusion(PayrollRun $run, int $employeeId): void
    {
        PayrollRunExclusion::where('payroll_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->delete();
    }

    /**
     * Draft scope preview — same logic as scopePreview() but without an existing
     * PayrollRun record.  Used by Steps 1–2 of the wizard before the run is
     * committed to the database.
     *
     * @param  string  $cutoffEnd  YYYY-MM-DD — taken from Step 1 form data
     * @param  int[]  $excludeEmployeeIds  Locally-tracked draft exclusions
     */
    public function draftScopePreview(
        string $cutoffEnd,
        ?array $departmentIds,
        ?array $positionIds,
        ?array $employmentTypes,
        bool $includeUnpaidLeave,
        bool $includeProbationEnd,
        array $excludeEmployeeIds = [],
    ): array {
        $query = Employee::query()
            ->where('employment_status', 'active')
            ->where('date_hired', '<=', $cutoffEnd);

        if (! empty($departmentIds)) {
            $query->whereIn('department_id', $departmentIds);
        }
        if (! empty($positionIds)) {
            $query->whereIn('position_id', $positionIds);
        }
        if (! empty($employmentTypes)) {
            $query->whereIn('employment_type', $employmentTypes);
        }

        $totalInScope = (clone $query)->count();
        $manuallyExcluded = count($excludeEmployeeIds);
        $willBeComputed = max(0, $totalInScope - $manuallyExcluded);

        $deptData = Department::with(['employees' => function ($q) use ($cutoffEnd, $departmentIds) {
            $q->where('employment_status', 'active')
                ->where('date_hired', '<=', $cutoffEnd);
            if (! empty($departmentIds)) {
                $q->whereIn('department_id', $departmentIds);
            }
        }])
            ->when(! empty($departmentIds), fn ($q) => $q->whereIn('id', $departmentIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Department $d) use ($excludeEmployeeIds) {
                $eligible = $d->employees->count();
                $excluded = $d->employees->filter(fn ($e) => in_array($e->id, $excludeEmployeeIds))->count();

                return [
                    'department_id' => $d->id,
                    'department_name' => $d->name,
                    'eligible' => $eligible,
                    'excluded' => $excluded,
                    'in_scope' => max(0, $eligible - $excluded),
                ];
            })
            ->values()
            ->all();

        // Employees in effective scope (not excluded) with no bank account
        $missingBankEmployees = (clone $query)
            ->whereNotIn('id', $excludeEmployeeIds)
            ->where(fn ($q) => $q->whereNull('bank_account_no')->orWhere('bank_account_no', ''))
            ->with('department:id,name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code', 'department_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => "{$e->first_name} {$e->last_name}",
                'employee_code' => $e->employee_code,
                'department_name' => $e->department?->name ?? '—',
            ])
            ->values()
            ->all();

        return [
            'total_eligible' => $totalInScope,
            'manually_excluded' => $manuallyExcluded,
            'net_in_scope' => $willBeComputed,
            'by_department' => $deptData,
            'missing_bank_employees' => $missingBankEmployees,
        ];
    }

    /**
     * Returns a scope preview: how many employees will be included.
     *
     * @return array{
     *   total_in_scope: int,
     *   excluded_inactive: int,
     *   manually_excluded: int,
     *   will_be_computed: int,
     *   departments: list<array{id: int, name: string, in_scope_count: int}>,
     * }
     */
    public function scopePreview(
        PayrollRun $run,
        ?array $departmentIds,
        ?array $positionIds,
        ?array $employmentTypes,
        bool $includeUnpaidLeave,
        bool $includeProbationEnd,
    ): array {
        $query = Employee::query()
            ->where('employment_status', 'active')
            ->where('date_hired', '<=', $run->cutoff_end);

        if (! empty($departmentIds)) {
            $query->whereIn('department_id', $departmentIds);
        }
        if (! empty($positionIds)) {
            $query->whereIn('position_id', $positionIds);
        }
        if (! empty($employmentTypes)) {
            $query->whereIn('employment_type', $employmentTypes);
        }

        $totalInScope = (clone $query)->count();

        // Employees on unpaid leave who would normally be excluded
        $unpaidLeaveCount = 0;
        if (! $includeUnpaidLeave) {
            // Count employees currently on approved unpaid leave during cutoff
            $unpaidLeaveCount = (clone $query)
                ->whereHas('leaveRequests', function ($q) use ($run) {
                    $q->where('status', 'approved')
                        ->where('leave_type', 'unpaid')
                        ->where('start_date', '<=', $run->cutoff_end)
                        ->where('end_date', '>=', $run->cutoff_start);
                })
                ->count();
        }

        $manuallyExcluded = PayrollRunExclusion::where('payroll_run_id', $run->id)->count();

        $excludedInactive = Employee::where('employment_status', '!=', 'active')
            ->when(! empty($departmentIds), fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->count();

        $willBeComputed = max(0, $totalInScope - $manuallyExcluded);

        // Per-department breakdown with exclusion counts
        $excludedEmpIds = PayrollRunExclusion::where('payroll_run_id', $run->id)
            ->pluck('employee_id')
            ->toArray();

        $deptData = Department::with(['employees' => function ($q) use ($run, $departmentIds) {
            $q->where('employment_status', 'active')
                ->where('date_hired', '<=', $run->cutoff_end);
            if (! empty($departmentIds)) {
                $q->whereIn('department_id', $departmentIds);
            }
        }])
            ->when(! empty($departmentIds), fn ($q) => $q->whereIn('id', $departmentIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Department $d) use ($excludedEmpIds) {
                $eligible = $d->employees->count();
                $excluded = $d->employees->filter(fn ($e) => in_array($e->id, $excludedEmpIds))->count();
                $inScope = max(0, $eligible - $excluded);

                return [
                    'department_id' => $d->id,
                    'department_name' => $d->name,
                    'eligible' => $eligible,
                    'excluded' => $excluded,
                    'in_scope' => $inScope,
                ];
            })
            ->values()
            ->all();

        // Employees still in effective scope with no bank account (manually-excluded employees are filtered out)
        $missingBankEmployees = (clone $query)
            ->whereNotIn('id', $excludedEmpIds)
            ->where(fn ($q) => $q->whereNull('bank_account_no')->orWhere('bank_account_no', ''))
            ->with('department:id,name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code', 'department_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => "{$e->first_name} {$e->last_name}",
                'employee_code' => $e->employee_code,
                'department_name' => $e->department?->name ?? '—',
            ])
            ->values()
            ->all();

        return [
            'total_eligible' => $totalInScope,
            'manually_excluded' => $manuallyExcluded,
            'net_in_scope' => $willBeComputed,
            'by_department' => $deptData,
            'missing_bank_employees' => $missingBankEmployees,
        ];
    }
}
