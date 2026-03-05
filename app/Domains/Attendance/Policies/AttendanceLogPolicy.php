<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Policies;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Attendance Log Policy — matrix § HR Attendance & Overtime
 *
 * View own: all roles except admin
 * View team: hr_manager (DEPT), hr_supervisor (DEPT),
 *            ops_manager (read DEPT), ops_supervisor (read DEPT)
 * Import CSV / resolve anomalies: hr_manager + hr_supervisor
 * No access: finance roles, staff (own via permission only)
 */
final class AttendanceLogPolicy
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
        return $user->hasAnyPermission(['attendance.view_own', 'attendance.view_team', 'attendance.view']);
    }

    public function viewTeam(User $user): bool
    {
        return $user->hasPermissionTo('attendance.view_team');
    }

    public function view(User $user, AttendanceLog $log): bool
    {
        // Team view: HR / Ops roles with RDAC
        if ($user->hasAnyPermission(['attendance.view_team'])) {
            $deptId = $log->employee?->department_id;

            return $deptId === null || $user->hasDepartmentAccess((int) $deptId);
        }

        // Own record only
        return $user->hasAnyPermission(['attendance.view_own', 'attendance.view'])
            && $log->employee?->user_id === $user->id;
    }

    /** Import CSV — HR Manager + HR Supervisor */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['attendance.import_csv', 'attendance.create']);
    }

    /** Resolve anomalies / manual override — HR Manager + HR Supervisor */
    public function update(User $user, AttendanceLog $log): bool
    {
        if (! $user->hasAnyPermission(['attendance.resolve_anomalies', 'attendance.update'])) {
            return false;
        }

        $deptId = $log->employee?->department_id;

        return $deptId === null || $user->hasDepartmentAccess((int) $deptId);
    }

    public function delete(User $user, AttendanceLog $log): bool
    {
        return $user->hasAnyPermission(['attendance.resolve_anomalies', 'attendance.delete'])
            && ($log->employee?->department_id === null
                || $user->hasDepartmentAccess((int) $log->employee->department_id));
    }
}
