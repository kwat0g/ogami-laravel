<?php

declare(strict_types=1);

namespace App\Domains\Leave\Policies;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Leave Request Policy — requester-specific simplified approval chain.
 *
 * SOD-002: each approver must not be the employee whose leave it is.
 *
 * Step permissions:
 *   leaves.head_approve
 *   leaves.manager_approve
 *   leaves.hr_approve
 *   leaves.vp_approve
 */
final class LeaveRequestPolicy
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
     * Staff chain first-level approval.
     */
    public function headApprove(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->canActAtCurrentStep($user, $leaveRequest, 'leaves.head_approve', ['staff'], ['submitted']);
    }

    public function managerApprove(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->canActAtCurrentStep($user, $leaveRequest, 'leaves.manager_approve', ['head_officer'], ['submitted']);
    }

    public function hrApprove(User $user, LeaveRequest $leaveRequest): bool
    {
        return match ($leaveRequest->requester_type) {
            'staff' => $this->canActAtCurrentStep($user, $leaveRequest, 'leaves.hr_approve', ['staff'], ['head_approved']),
            'head_officer' => $this->canActAtCurrentStep($user, $leaveRequest, 'leaves.hr_approve', ['head_officer'], ['manager_approved']),
            'dept_manager' => $this->canActAtCurrentStep($user, $leaveRequest, 'leaves.hr_approve', ['dept_manager'], ['submitted']),
            default => false,
        };
    }

    public function vpApprove(User $user, LeaveRequest $leaveRequest): bool
    {
        return match ($leaveRequest->requester_type) {
            'dept_manager' => $this->canActAtCurrentStep($user, $leaveRequest, 'leaves.vp_approve', ['dept_manager'], ['hr_approved']),
            'hr_manager' => $this->canActAtCurrentStep($user, $leaveRequest, 'leaves.vp_approve', ['hr_manager'], ['submitted']),
            default => false,
        };
    }

    // ── Reject (any approver at their step) ──────────────────────────────────

    /**
     * Any current-step approver can reject.
     * Mapped to the review gate used by the reject controller action.
     */
    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        return match ($leaveRequest->status) {
            'submitted' => match ($leaveRequest->requester_type) {
                'staff' => $this->headApprove($user, $leaveRequest),
                'head_officer' => $this->managerApprove($user, $leaveRequest),
                'dept_manager' => $this->hrApprove($user, $leaveRequest),
                'hr_manager' => $this->vpApprove($user, $leaveRequest),
                default => false,
            },
            'head_approved' => $this->hrApprove($user, $leaveRequest),
            'manager_approved' => $this->hrApprove($user, $leaveRequest),
            'hr_approved' => $this->vpApprove($user, $leaveRequest),
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

    /**
     * @param  list<string>  $requesterTypes
     * @param  list<string>  $statuses
     */
    private function canActAtCurrentStep(User $user, LeaveRequest $leaveRequest, string $permission, array $requesterTypes, array $statuses): bool
    {
        if (! $user->hasPermissionTo($permission)) {
            return false;
        }

        if (! in_array($leaveRequest->requester_type, $requesterTypes, true)) {
            return false;
        }

        if (! in_array($leaveRequest->status, $statuses, true)) {
            return false;
        }

        if ($leaveRequest->submitted_by === $user->id) {
            return false;
        }

        $employeeUserId = $leaveRequest->employee?->user_id;

        return $employeeUserId === null || (int) $user->id !== (int) $employeeUserId;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(\App\Models\User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(\App\Models\User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }
}
