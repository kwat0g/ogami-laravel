<?php

declare(strict_types=1);

namespace App\Domains\HR\Policies;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Employee Policy — ogami_role_permission_matrix.md § HR Employee Management
 *
 * RDAC enforcement: every action requiring DEPT scope checks
 * $user->hasDepartmentAccess($employee->department_id).
 *
 * SOD-001:  employees.activate — the user who CREATED the record cannot activate it.
 * SELF-001: Cannot edit your own salary / compensation.
 * SELF-002: Cannot terminate or suspend your own employee record.
 */
final class EmployeePolicy
{
    use HandlesAuthorization;

    /** Admin bypasses all policy checks. Executive also bypasses for read-only paths. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    // ── View ──────────────────────────────────────────────────────────────────

    /**
     * View list: HR Manager (all DEPT), HR Supervisor (DEPT),
     * Ops Manager (name/pos only — DEPT), Ops Supervisor (name/pos only — DEPT),
     * Executive (ALL — read-only).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('employees.view');
    }

    /**
     * View team: department-scoped access for managers/supervisors.
     * Only employees within the user's department(s).
     */
    public function viewTeam(User $user): bool
    {
        return $user->hasPermissionTo('employees.view_team');
    }

    /** Full record: HR Manager + HR Supervisor (ALL DEPT). Name/pos: ops roles + executive. */
    public function view(User $user, Employee $employee): bool
    {
        if (! $user->hasPermissionTo('employees.view')) {
            return false;
        }

        // Executive sees all departments
        if ($user->hasRole('executive')) {
            return true;
        }

        // HR roles (with employees.update) can view all employees across departments
        if ($user->hasPermissionTo('employees.update')) {
            return true;
        }

        // Manager + Supervisor: DEPT-scoped access
        if ($user->hasAnyRole(['manager', 'plant_manager', 'production_manager', 'qc_manager', 'mold_manager', 'officer', 'ga_officer', 'purchasing_officer', 'impex_officer', 'head'])) {
            return $user->hasDepartmentAccess((int) $employee->department_id);
        }

        // Staff: own record only
        if ($user->hasRole('staff')) {
            return $employee->user_id === $user->id;
        }

        return false;
    }

    /** View full record (salary, gov IDs context) — HR Manager + HR Supervisor (ALL DEPT) */
    public function viewFullRecord(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('employees.view_full_record');
    }

    /** View compensation — HR Manager only (ALL DEPT) */
    public function viewSalary(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('employees.view_salary');
    }

    /** ── Create / Edit ──────────────────────────────────────────────────── */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        if (! $user->hasPermissionTo('employees.update')) {
            return false;
        }

        // HR Manager (with employees.update) can update employees in ANY department
        // Other roles are restricted to their assigned departments
        return true;
    }

    /** EMP-007: only hr_manager may change compensation fields.
     *  SELF-001: cannot edit your own salary. */
    public function updateCompensation(User $user, Employee $employee): bool
    {
        if (! $user->hasPermissionTo('employees.update_salary')) {
            return false;
        }

        // SELF-001: cannot edit own salary
        return ! $this->isOwnEmployee($user, $employee);
    }

    /**
     * SOD-001: activate employee.
     * The activating user must NOT be the user who created the record.
     */
    public function activate(User $user, Employee $employee): bool
    {
        if (! $user->hasPermissionTo('employees.activate')) {
            return false;
        }

        // SOD-001: initiator cannot activate their own submission
        return (int) $user->id !== (int) $employee->created_by;
    }

    /** State transition (suspend / terminate).
     *  SELF-002: cannot terminate or suspend your own employee record. */
    public function transition(User $user, Employee $employee): bool
    {
        if (! ($user->hasPermissionTo('employees.suspend') || $user->hasPermissionTo('employees.terminate'))) {
            return false;
        }

        // SELF-002: cannot terminate/suspend own record
        return ! $this->isOwnEmployee($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        // Hard deletes blocked — soft delete only via terminate
        return false;
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('employees.update');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('employees.export');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /** True when the employee record belongs to the acting user. */
    private function isOwnEmployee(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && (int) $employee->user_id === (int) $user->id;
    }
}
