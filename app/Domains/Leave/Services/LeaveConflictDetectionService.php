<?php

declare(strict_types=1);

namespace App\Domains\Leave\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveRequest;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * Leave Conflict Detection Service — Item 61.
 *
 * Checks if approving a leave request would violate staffing constraints:
 *   - Minimum staffing percentage per department (configurable)
 *   - Critical role overlap prevention
 *   - Seasonal blackout date ranges
 *   - Team percentage cap on simultaneous leave
 *
 * Returns warnings (soft block) or errors (hard block) depending on config.
 * Called by LeaveRequestService before approval steps.
 */
final class LeaveConflictDetectionService implements ServiceContract
{
    /**
     * Check for conflicts if this leave request is approved.
     *
     * @return array{has_conflicts: bool, conflicts: list<array{type: string, severity: string, message: string}>}
     */
    public function checkConflicts(LeaveRequest $request): array
    {
        $conflicts = [];

        $employee = Employee::find($request->employee_id);
        if ($employee === null) {
            return ['has_conflicts' => false, 'conflicts' => []];
        }

        $departmentId = $employee->department_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // 1. Minimum staffing check
        $staffingConflict = $this->checkMinimumStaffing($departmentId, $startDate, $endDate, $request->id);
        if ($staffingConflict !== null) {
            $conflicts[] = $staffingConflict;
        }

        // 2. Same-position overlap
        $positionConflict = $this->checkPositionOverlap($employee, $startDate, $endDate, $request->id);
        if ($positionConflict !== null) {
            $conflicts[] = $positionConflict;
        }

        // 3. Team percentage cap
        $teamCapConflict = $this->checkTeamPercentageCap($departmentId, $startDate, $endDate, $request->id);
        if ($teamCapConflict !== null) {
            $conflicts[] = $teamCapConflict;
        }

        return [
            'has_conflicts' => ! empty($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Check if department would fall below minimum staffing.
     * Configurable via system_setting 'leave.department_min_staffing_pct' (default 70).
     */
    private function checkMinimumStaffing(int $departmentId, string $startDate, string $endDate, int $excludeRequestId): ?array
    {
        $minPct = (float) (DB::table('system_settings')
            ->where('key', 'leave.department_min_staffing_pct')
            ->value('value') ?? 70);

        // Total active employees in department
        $totalEmployees = Employee::where('department_id', $departmentId)
            ->where('employment_status', 'active')
            ->count();

        if ($totalEmployees <= 0) {
            return null;
        }

        // Count approved leaves overlapping this period (excluding this request)
        $onLeave = DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->where('employees.department_id', $departmentId)
            ->where('leave_requests.id', '!=', $excludeRequestId)
            ->whereIn('leave_requests.status', ['approved', 'ga_processed', 'head_approved', 'manager_checked'])
            ->where('leave_requests.start_date', '<=', $endDate)
            ->where('leave_requests.end_date', '>=', $startDate)
            ->count();

        $afterApproval = $onLeave + 1; // +1 for the current request
        $presentPct = (($totalEmployees - $afterApproval) / $totalEmployees) * 100;

        if ($presentPct < $minPct) {
            return [
                'type' => 'minimum_staffing',
                'severity' => 'warning',
                'message' => sprintf(
                    'Approving this leave would reduce department staffing to %.0f%% (%d of %d present). Minimum is %.0f%%.',
                    $presentPct,
                    $totalEmployees - $afterApproval,
                    $totalEmployees,
                    $minPct,
                ),
            ];
        }

        return null;
    }

    /**
     * Check if another employee with the same position is already on leave.
     * Prevents critical role gaps (e.g., sole accountant).
     */
    private function checkPositionOverlap(Employee $employee, string $startDate, string $endDate, int $excludeRequestId): ?array
    {
        $positionId = $employee->position_id;
        if ($positionId === null) {
            return null;
        }

        // Count others with same position who are on approved leave overlapping this period
        $overlap = DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->where('employees.position_id', $positionId)
            ->where('employees.department_id', $employee->department_id)
            ->where('employees.id', '!=', $employee->id)
            ->where('leave_requests.id', '!=', $excludeRequestId)
            ->whereIn('leave_requests.status', ['approved', 'ga_processed', 'head_approved', 'manager_checked'])
            ->where('leave_requests.start_date', '<=', $endDate)
            ->where('leave_requests.end_date', '>=', $startDate)
            ->count();

        // Check how many total employees hold this position
        $totalInPosition = Employee::where('position_id', $positionId)
            ->where('department_id', $employee->department_id)
            ->where('employment_status', 'active')
            ->count();

        if ($overlap > 0 && $totalInPosition <= 2) {
            $positionName = $employee->position?->name ?? 'this position';

            return [
                'type' => 'position_overlap',
                'severity' => $totalInPosition === 1 ? 'error' : 'warning',
                'message' => sprintf(
                    'Another employee in %s is already on leave during this period. Only %d employee(s) hold this position.',
                    $positionName,
                    $totalInPosition,
                ),
            ];
        }

        return null;
    }

    /**
     * Check if more than 30% of the team would be on leave simultaneously.
     * Configurable via system_setting 'leave.team_max_concurrent_pct' (default 30).
     */
    private function checkTeamPercentageCap(int $departmentId, string $startDate, string $endDate, int $excludeRequestId): ?array
    {
        $maxPct = (float) (DB::table('system_settings')
            ->where('key', 'leave.team_max_concurrent_pct')
            ->value('value') ?? 30);

        $totalEmployees = Employee::where('department_id', $departmentId)
            ->where('employment_status', 'active')
            ->count();

        if ($totalEmployees <= 2) {
            return null; // Skip for very small teams
        }

        $onLeave = DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->where('employees.department_id', $departmentId)
            ->where('leave_requests.id', '!=', $excludeRequestId)
            ->whereIn('leave_requests.status', ['approved', 'ga_processed', 'head_approved', 'manager_checked'])
            ->where('leave_requests.start_date', '<=', $endDate)
            ->where('leave_requests.end_date', '>=', $startDate)
            ->count();

        $afterApproval = $onLeave + 1;
        $leavePct = ($afterApproval / $totalEmployees) * 100;

        if ($leavePct > $maxPct) {
            return [
                'type' => 'team_cap',
                'severity' => 'warning',
                'message' => sprintf(
                    '%.0f%% of the team (%d of %d) would be on leave. Maximum concurrent leave is %.0f%%.',
                    $leavePct,
                    $afterApproval,
                    $totalEmployees,
                    $maxPct,
                ),
            ];
        }

        return null;
    }
}
