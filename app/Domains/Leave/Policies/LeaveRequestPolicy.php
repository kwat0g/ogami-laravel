<?php

declare(strict_types=1);

namespace App\Domains\Leave\Policies;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Leave Request Policy — matrix § HR Leave Management
 *
 * SOD-002: approver cannot approve their own leave request.
 * RDAC: hr_manager and ops_manager can only approve within their departments.
 * Approve right: hr_manager (DEPT), ops_manager (DEPT).
 * File-on-behalf: hr_manager (DEPT), hr_supervisor (DEPT).
 */
final class LeaveRequestPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['leaves.view_own', 'leaves.view_team', 'leaves.view']);
    }

    public function viewTeam(User $user): bool
    {
        return $user->hasPermissionTo('leaves.view_team');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        // HR and Ops managers/supervisors: team scope
        if ($user->hasAnyPermission(['leaves.view_team'])) {
            $deptId = $leaveRequest->employee?->department_id;

            return $deptId === null || $user->hasDepartmentAccess((int) $deptId);
        }

        // Own leave only
        return $this->isOwnLeave($user, $leaveRequest);
    }

    public function create(User $user, Employee $employee): bool
    {
        // Filing for self
        if ($employee->user_id === $user->id) {
            return $user->hasAnyPermission(['leaves.file_own', 'leaves.create']);
        }

        // Filing on behalf of another employee (HR roles only, DEPT-scoped)
        return $user->hasPermissionTo('leaves.file_on_behalf')
            && $user->hasDepartmentAccess((int) $employee->department_id);
    }

    /**
     * Supervisor first-level approval.
     * Supervisor can approve requests from their direct reports.
     */
    public function supervise(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->hasPermissionTo('leaves.supervise')) {
            return false;
        }

        $deptId = $leaveRequest->employee?->department_id;
        if ($deptId !== null && ! $user->hasDepartmentAccess((int) $deptId)) {
            return false;
        }

        // Must be pending (submitted) status
        if ($leaveRequest->status !== 'submitted') {
            return false;
        }

        // SOD-002: cannot approve own leave
        $employeeUserId = $leaveRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    /**
     * Approve — SOD-002: approver must not be the employee whose leave it is.
     * Manager final approval (after supervisor approval).
     * Supervisors can also reject at the submitted stage.
     */
    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        $hasManagerPerm = $user->hasAnyPermission(['leaves.approve', 'leaves.reject']);
        $hasSupervisePerm = $user->hasPermissionTo('leaves.supervise');

        // Supervisor can only reject/review at 'submitted' stage
        if (! $hasManagerPerm && $hasSupervisePerm && $leaveRequest->status !== 'submitted') {
            return false;
        }

        if (! $hasManagerPerm && ! $hasSupervisePerm) {
            return false;
        }

        $deptId = $leaveRequest->employee?->department_id;
        if ($deptId !== null && ! $user->hasDepartmentAccess((int) $deptId)) {
            return false;
        }

        // Must be supervisor_approved or submitted (for backward compatibility)
        if (! in_array($leaveRequest->status, ['supervisor_approved', 'submitted'], true)) {
            return false;
        }

        // SOD-002: cannot approve own leave
        $employeeUserId = $leaveRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->hasPermissionTo('leaves.cancel')) {
            // HR role: can cancel any in their dept
            $deptId = $leaveRequest->employee?->department_id;
            if ($deptId !== null) {
                return $user->hasDepartmentAccess((int) $deptId);
            }

            return true;
        }

        // Staff: own pending requests only
        return $this->isOwnLeave($user, $leaveRequest)
            && in_array($leaveRequest->status, ['draft', 'submitted'], true);
    }

    /**
     * Executive approval for manager-filed requests.
     * Only executives can approve/reject manager requests.
     */
    public function executiveApprove(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->hasPermissionTo('leaves.executive_approve')) {
            return false;
        }

        // Must be a manager-level request pending executive approval
        if (! in_array($leaveRequest->requester_role, ['manager'], true)) {
            return false;
        }

        if ($leaveRequest->status !== 'pending_executive') {
            return false;
        }

        // SOD-002: cannot approve own request
        return (int) $user->id !== (int) $leaveRequest->submitted_by;
    }

    /**
     * View executive approval queue.
     */
    public function viewExecutiveQueue(User $user): bool
    {
        return $user->hasPermissionTo('leaves.executive_approve');
    }

    private function isOwnLeave(User $user, LeaveRequest $leaveRequest): bool
    {
        $employeeId = \App\Domains\HR\Models\Employee::where('user_id', $user->id)->value('id');

        return $employeeId !== null && (int) $leaveRequest->employee_id === (int) $employeeId;
    }
}
