<?php

declare(strict_types=1);

namespace App\Domains\Leave\Policies;

use App\Domains\Leave\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Leave Balance Policy
 */
final class LeaveBalancePolicy
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
        return $user->hasAnyPermission(['leaves.view_own', 'leaves.view_team', 'leaves.view', 'leave_balances.view']);
    }

    public function view(User $user, LeaveBalance $leaveBalance): bool
    {
        // Own balance
        $employeeId = \App\Domains\HR\Models\Employee::where('user_id', $user->id)->value('id');
        if ($employeeId !== null && (int) $leaveBalance->employee_id === (int) $employeeId) {
            return $user->hasPermissionTo('leaves.view_own');
        }

        // Team view
        if ($user->hasPermissionTo('leaves.view_team')) {
            $deptId = $leaveBalance->employee?->department_id;

            return $deptId === null || $user->hasDepartmentAccess((int) $deptId);
        }

        return $user->hasPermissionTo('leave_balances.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('leave_balances.manage');
    }

    public function update(User $user, LeaveBalance $leaveBalance): bool
    {
        if (! $user->hasPermissionTo('leave_balances.manage')) {
            return false;
        }

        // Department-scoped for managers
        $deptId = $leaveBalance->employee?->department_id;
        if ($deptId !== null) {
            return $user->hasDepartmentAccess((int) $deptId);
        }

        return true;
    }

    public function delete(User $user, LeaveBalance $leaveBalance): bool
    {
        return $this->update($user, $leaveBalance);
    }
}
