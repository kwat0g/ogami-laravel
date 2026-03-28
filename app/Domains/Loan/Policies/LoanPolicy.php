<?php

declare(strict_types=1);

namespace App\Domains\Loan\Policies;

use App\Domains\HR\Models\Employee;
use App\Domains\Loan\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Loan Policy — matrix § HR Loan Management
 *
 * Workflow v1 (legacy 3-stage):
 *   SOD-004: hr_manager cannot approve a loan they also submitted.
 *   Stage 1 — Supervisor review
 *   Stage 2 — HR Manager approval (SOD-004)
 *   Stage 3 — Accounting Manager approval + disbursement
 *
 * Workflow v2 (5-stage):
 *   Stage 1 — Department Head note (loans.head_note)
 *   Stage 2 — Manager check (loans.manager_check, SOD)
 *   Stage 3 — Officer review (loans.officer_review)
 *   Stage 4 — VP approval (loans.vp_approve, SOD)
 *   Stage 5 — Disbursement
 */
final class LoanPolicy
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
        return $user->hasAnyPermission(['loans.view_own', 'loans.view_department', 'loans.view']);
    }

    public function viewDepartment(User $user): bool
    {
        return $user->hasPermissionTo('loans.view_department');
    }

    public function view(User $user, Loan $loan): bool
    {
        // Accounting Manager — global visibility needed for approval workflow
        if ($user->hasPermissionTo('loans.accounting_approve')) {
            return true;
        }

        // HR Manager / Supervisor — department scope
        if ($user->hasPermissionTo('loans.view_department')) {
            $deptId = $loan->employee->department_id;

            return $deptId === null || $user->hasDepartmentAccess((int) $deptId);
        }

        // Own loan only
        return $user->hasPermissionTo('loans.view_own')
            && $loan->employee->user_id === $user->id;
    }

    public function create(User $user, Employee $employee): bool
    {
        return $user->hasAnyPermission(['loans.apply', 'loans.create']);
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->hasAnyPermission(['loans.update', 'loans.apply'])
            && $loan->status === 'pending';
    }

    /** Stage 1: HR Supervisor review */
    public function supervisorReview(User $user, Loan $loan): bool
    {
        return $user->hasPermissionTo('loans.supervisor_review')
            && $user->hasDepartmentAccess((int) ($loan->employee->department_id ?? 0));
    }

    /**
     * Stage 2: HR Manager approval — SOD-004.
     * Approver must not have submitted the loan application themselves.
     */
    public function approve(User $user, Loan $loan): bool
    {
        if (! $user->hasAnyPermission(['loans.hr_approve', 'loans.approve'])) {
            return false;
        }

        $deptId = $loan->employee->department_id;
        if ($deptId !== null && ! $user->hasDepartmentAccess((int) $deptId)) {
            return false;
        }

        // SOD-004: approver cannot be the submitter
        return (int) $user->id !== (int) $loan->requested_by;
    }

    /** Stage 3: Finance Manager accounting approval */
    public function accountingApprove(User $user, Loan $loan): bool
    {
        return $user->hasPermissionTo('loans.accounting_approve');
    }

    public function reject(User $user, Loan $loan): bool
    {
        // Accounting managers can reject any loan at their stage without department check
        if ($user->hasPermissionTo('loans.accounting_approve')) {
            return true;
        }

        return $user->hasAnyPermission(['loans.hr_approve', 'loans.reject'])
            && $user->hasDepartmentAccess((int) ($loan->employee->department_id ?? 0));
    }

    public function disburse(User $user, Loan $loan): bool
    {
        // SoD: Disburser must differ from Accounting approver
        if ((int) $user->id === (int) $loan->accounting_approved_by) {
            return false;
        }

        // Both HR Manager and Accounting Manager can disburse
        return $user->hasAnyPermission(['loans.accounting_approve', 'loans.hr_approve']);
    }

    public function recordPayment(User $user, Loan $loan): bool
    {
        return $user->hasAnyPermission(['loans.accounting_approve', 'loans.hr_approve', 'loans.update']);
    }

    public function cancel(User $user, Loan $loan): bool
    {
        // Only cancellable before HR Manager approval
        if (! in_array($loan->status, ['pending', 'supervisor_approved', 'head_noted'], true)) {
            return false;
        }

        return $loan->employee->user_id === $user->id
            || $user->hasAnyPermission(['loans.hr_approve', 'loans.update']);
    }

    // ── Workflow v2 gates ─────────────────────────────────────────────────────

    /** Stage 1 (v2): Department Head notes the loan application. */
    public function headNote(User $user, Loan $loan): bool
    {
        if ($loan->workflow_version !== 2 || $loan->status !== 'pending') {
            return false;
        }

        if (! $user->hasPermissionTo('loans.head_note')) {
            return false;
        }

        return $user->hasDepartmentAccess((int) ($loan->employee->department_id ?? 0));
    }

    /** Stage 2 (v2): Manager checks the head-noted loan — SOD: must differ from head. */
    public function managerCheck(User $user, Loan $loan): bool
    {
        if ($loan->workflow_version !== 2 || $loan->status !== 'head_noted') {
            return false;
        }

        if (! $user->hasPermissionTo('loans.manager_check')) {
            return false;
        }

        // SoD: checker cannot be the head who noted it
        if ((int) $user->id === (int) $loan->head_noted_by) {
            return false;
        }

        return $user->hasDepartmentAccess((int) ($loan->employee->department_id ?? 0));
    }

    /** Stage 3 (v2): Officer reviews the manager-checked loan. */
    public function officerReview(User $user, Loan $loan): bool
    {
        if ($loan->workflow_version !== 2 || $loan->status !== 'manager_checked') {
            return false;
        }

        return $user->hasPermissionTo('loans.officer_review');
    }

    /** Stage 4 (v2): VP approves — SOD: must differ from officer reviewer. */
    public function vpApprove(User $user, Loan $loan): bool
    {
        if ($loan->workflow_version !== 2 || $loan->status !== 'officer_reviewed') {
            return false;
        }

        if (! $user->hasPermissionTo('loans.vp_approve')) {
            return false;
        }

        // SoD: VP cannot be the officer who reviewed it
        return (int) $user->id !== (int) $loan->officer_reviewed_by;
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
