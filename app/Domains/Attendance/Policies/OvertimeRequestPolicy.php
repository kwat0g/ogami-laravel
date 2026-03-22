<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Policies;

use App\Domains\Attendance\Models\OvertimeRequest;
use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Overtime Request Policy — matrix § HR Attendance & Overtime
 *
 * Approval workflow:
 *   Staff      : pending → supervise() → supervisor_approved → review() → approved
 *   Supervisor : pending → review() → approved (no supervisor step)
 *   Manager    : pending_executive → executiveApprove() → approved
 *
 * SOD-003: approver cannot approve their own OT request (employee.user_id check).
 */
final class OvertimeRequestPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['overtime.view', 'overtime.create']);
    }

    public function viewTeam(User $user): bool
    {
        return $user->hasPermissionTo('overtime.view');
    }

    public function viewExecutiveQueue(User $user): bool
    {
        return $user->hasPermissionTo('overtime.executive_approve');
    }

    public function view(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($user->hasPermissionTo('overtime.view')) {
            $deptId = $overtimeRequest->employee?->department_id;

            return $deptId === null || $user->hasDepartmentAccess((int) $deptId);
        }

        return $overtimeRequest->employee?->user_id === $user->id;
    }

    public function create(User $user, Employee $employee): bool
    {
        return $user->hasAnyPermission(['overtime.submit', 'overtime.create']);
    }

    public function update(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $user->hasAnyPermission(['overtime.submit', 'overtime.update'])) {
            return false;
        }

        return $overtimeRequest->status === 'pending';
    }

    /**
     * Supervisor first-level endorsement.
     * ONLY for staff-role requests that are still in 'pending' status.
     * Supervisor and Manager requests do not go through this step.
     * SOD-003: supervisor must not be the requesting employee.
     */
    public function supervise(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $user->hasPermissionTo('overtime.supervise')) {
            return false;
        }

        $deptId = $overtimeRequest->employee?->department_id;
        if ($deptId !== null && ! $user->hasDepartmentAccess((int) $deptId)) {
            return false;
        }

        // Only staff (or null/legacy) requests require supervisor endorsement
        $role = $overtimeRequest->requester_role;
        if ($role !== null && $role !== 'staff') {
            return false;
        }

        // Must be in pending status
        if ($overtimeRequest->status !== 'pending') {
            return false;
        }

        // SOD-003: cannot endorse own request
        $employeeUserId = $overtimeRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    /**
     * Manager (department) final approval / rejection.
     * - Staff requests  : must be supervisor_approved (enforces mandatory endorsement).
     * - Supervisor requests: must be pending (direct manager approval, no endorsement step).
     * SOD-003: approver must not be the requesting employee.
     */
    public function review(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $user->hasAnyPermission(['overtime.approve', 'overtime.reject'])) {
            return false;
        }

        $deptId = $overtimeRequest->employee?->department_id;
        if ($deptId !== null && ! $user->hasDepartmentAccess((int) $deptId)) {
            return false;
        }

        // Cannot review manager requests (those go to executive)
        if ($overtimeRequest->requester_role === 'manager') {
            return false;
        }

        // Enforce per-role reviewable state
        $role = $overtimeRequest->requester_role;
        $status = $overtimeRequest->status;

        // Staff (or null/legacy): must have been endorsed by supervisor first
        if (($role === 'staff' || $role === null) && $status !== 'supervisor_approved') {
            return false;
        }

        // Supervisor: manager reviews directly from pending
        if ($role === 'supervisor' && $status !== 'pending') {
            return false;
        }

        // SOD-003: cannot approve own request
        $employeeUserId = $overtimeRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    /**
     * Executive approval/rejection for manager-filed requests.
     * Only users with `overtime.executive_approve` permission can call this.
     */
    public function executiveApprove(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $user->hasPermissionTo('overtime.executive_approve')) {
            return false;
        }

        // Must be a manager-level request pending executive approval
        if ($overtimeRequest->requester_role !== 'manager') {
            return false;
        }

        if ($overtimeRequest->status !== 'pending_executive') {
            return false;
        }

        // SOD-003: cannot approve own request
        $employeeUserId = $overtimeRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    public function cancel(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $overtimeRequest->isCancellable()) {
            return false;
        }

        return $overtimeRequest->employee?->user_id === $user->id
            || $user->hasAnyPermission(['overtime.submit', 'overtime.update']);
    }

    /**
     * VP final approval gate — step 5 of the 5-step OT approval flow.
     * Requires overtime.executive_approve permission. SOD-003 applies.
     */
    public function vpApprove(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $user->hasPermissionTo('overtime.executive_approve')) {
            return false;
        }

        if ($overtimeRequest->status !== 'officer_reviewed') {
            return false;
        }

        $employeeUserId = $overtimeRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }
}
