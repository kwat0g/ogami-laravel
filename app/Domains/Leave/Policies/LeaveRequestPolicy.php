<?php

declare(strict_types=1);

namespace App\Domains\Leave\Policies;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Leave Request Policy — 4-step approval chain (form AD-084-00).
 *
 * SOD-002: each approver must not be the employee whose leave it is.
 *
 * Step permissions:
 *   Step 2 — leaves.head_approve    (head role)
 *   Step 3 — leaves.manager_check   (plant_manager, manager)
 *   Step 4 — leaves.ga_process      (ga_officer)
 *   Step 5 — leaves.vp_note         (vice_president)
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
        if ($user->hasAnyPermission(['leaves.view_team', 'leaves.view'])) {
            $deptId = $leaveRequest->employee?->department_id;

            return $deptId === null || $user->hasDepartmentAccess((int) $deptId);
        }

        return $this->isOwnLeave($user, $leaveRequest);
    }

    public function create(User $user, Employee $employee): bool
    {
        if ($employee->user_id === $user->id) {
            return $user->hasAnyPermission(['leaves.file_own', 'leaves.create']);
        }

        return $user->hasPermissionTo('leaves.file_on_behalf')
            && $user->hasDepartmentAccess((int) $employee->department_id);
    }

    // ── Step 2 — Department Head approves ────────────────────────────────────

    /**
     * Department Head first-level approval.
     * Requires: leaves.head_approve, status = submitted, not own leave.
     */
    public function headApprove(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->hasPermissionTo('leaves.head_approve')) {
            return false;
        }

        if ($leaveRequest->status !== 'submitted') {
            return false;
        }

        // SOD-002
        $employeeUserId = $leaveRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    // ── Step 3 — Plant Manager checks ────────────────────────────────────────

    /**
     * Plant Manager check.
     * Requires: leaves.manager_check, status = head_approved, not own leave.
     */
    public function managerCheck(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->hasPermissionTo('leaves.manager_check')) {
            return false;
        }

        if ($leaveRequest->status !== 'head_approved') {
            return false;
        }

        $employeeUserId = $leaveRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    // ── Step 4 — GA Officer processes ────────────────────────────────────────

    /**
     * GA Officer processing.
     * Requires: leaves.ga_process, status = manager_checked, not own leave.
     */
    public function gaProcess(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->hasPermissionTo('leaves.ga_process')) {
            return false;
        }

        if ($leaveRequest->status !== 'manager_checked') {
            return false;
        }

        $employeeUserId = $leaveRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    // ── Step 5 — VP notes ────────────────────────────────────────────────────

    /**
     * Vice President final note.
     * Requires: leaves.vp_note, status = ga_processed, not own leave.
     */
    public function vpNote(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->hasPermissionTo('leaves.vp_note')) {
            return false;
        }

        if ($leaveRequest->status !== 'ga_processed') {
            return false;
        }

        $employeeUserId = $leaveRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    // ── Reject (any approver at their step) ──────────────────────────────────

    /**
     * Any current-step approver can reject.
     * Mapped to the review gate used by the reject controller action.
     */
    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        return match ($leaveRequest->status) {
            'submitted' => $this->headApprove($user, $leaveRequest),
            'head_approved' => $this->managerCheck($user, $leaveRequest),
            'manager_checked' => $this->gaProcess($user, $leaveRequest),
            'ga_processed' => $this->vpNote($user, $leaveRequest),
            default => false,
        };
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->hasPermissionTo('leaves.cancel')) {
            $deptId = $leaveRequest->employee?->department_id;
            if ($deptId !== null) {
                return $user->hasDepartmentAccess((int) $deptId);
            }

            return true;
        }

        return $this->isOwnLeave($user, $leaveRequest)
            && $leaveRequest->isCancellable();
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function isOwnLeave(User $user, LeaveRequest $leaveRequest): bool
    {
        $employeeId = Employee::where('user_id', $user->id)->value('id');

        return $employeeId !== null && (int) $leaveRequest->employee_id === (int) $employeeId;
    }
}
