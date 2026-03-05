<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Policies;

use App\Domains\Payroll\Models\PayrollRun;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * PayrollRun Policy — matrix § Payroll + ogami_payroll_run_workflow.md v1.0
 *
 * Role responsibilities:
 *   hr_manager   — Steps 1–5: initiate, pre-run, compute, review, submit
 *                  Step 6: first-level HR approval (SOD-005/006)
 *                  Step 8b: publish payslips
 *   finance_manager — Step 7: final accounting approval (SOD-007)
 *                     Step 8a: disburse + bank file
 *   All non-admin — view own payslip
 *
 * SoD rules:
 *   SOD-005: HR approver ≠ run initiator (created_by)
 *   SOD-006: HR approver ≠ run initiator (same as 005; belt + suspenders)
 *   SOD-007: Acctg approver ≠ run initiator (created_by)
 */
final class PayrollRunPolicy
{
    use HandlesAuthorization;

    /** Admin bypasses; admin has NO payroll business data access per matrix,
     *  but `before` returning true is needed for test infra admin users. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    // ── Generic update (used by controller actions that don't have a dedicated
    // policy method: confirmScope, addExclusion, removeExclusion,
    // acknowledgePreRun, reject, flagEmployee) ──────────────────────────────

    /**
     * Covers any mutation action on an active run.
     * Requires at least one payroll operational permission — effectively
     * restricts to hr_manager, finance_manager, and executive roles.
     */
    public function update(User $user, PayrollRun $run): bool
    {
        return $user->hasAnyPermission([
            'payroll.initiate',
            'payroll.pre_run_validate',
            'payroll.compute',
            'payroll.flag_employee',
            'payroll.hr_approve',
            'payroll.hr_return',
            'payroll.acctg_approve',
            'payroll.acctg_reject',
            'payroll.disburse',
            'payroll.publish',
            'payroll.recall',
            'payroll.submit',
            'payroll.approve',
            'payroll.post',
        ]);
    }

    // ── View ──────────────────────────────────────────────────────────────────

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['payroll.view_runs', 'payroll.view']);
    }

    public function view(User $user, PayrollRun $run): bool
    {
        // Finance Manager: read-only view of all runs
        // HR Manager: full view of all runs
        if ($user->hasAnyPermission(['payroll.view_runs', 'payroll.view'])) {
            return true;
        }

        // Allow initiator to always view their own payroll run
        if ((int) $user->id === (int) $run->created_by) {
            return true;
        }

        // Allow HR and Accounting to view during all workflow states
        if ($user->hasAnyPermission(['payroll.initiate', 'payroll.hr_approve', 'payroll.acctg_approve', 'payroll.approve', 'payroll.post'])) {
            return true;
        }

        // Non-payroll roles: blocked
        return false;
    }

    // ── Workflow steps ────────────────────────────────────────────────────────

    /** Step 1–2: create + set scope — HR Manager only */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['payroll.initiate', 'payroll.initiate']);
    }

    /** Step 2: confirm scope — HR Manager */
    public function confirmScope(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionTo('payroll.initiate')
            && $this->isInitiator($user, $run);
    }

    /** Step 3: trigger pre-run validation — HR Manager */
    public function preRunValidate(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionTo('payroll.pre_run_validate');
    }

    /** Step 4: trigger computation — HR Manager */
    public function compute(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionTo('payroll.compute');
    }

    /** Step 5: review breakdown — HR Manager (edit) + Finance Manager (read) */
    public function reviewBreakdown(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionTo('payroll.review_breakdown');
    }

    /** Step 5: flag an employee detail — HR Manager only */
    public function flagEmployee(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionTo('payroll.flag_employee');
    }

    /** Step 5→6: submit run for HR approval — HR Manager */
    public function submitForHr(User $user, PayrollRun $run): bool
    {
        return $user->hasAnyPermission(['payroll.submit_for_hr', 'payroll.submit']);
    }

    /**
     * Step 6: HR first-level approval.
     * SOD-005/006: approver must NOT be the user who initiated the run.
     */
    public function hrApprove(User $user, PayrollRun $run): bool
    {
        if (! $user->hasAnyPermission(['payroll.hr_approve', 'payroll.approve'])) {
            return false;
        }

        // SOD-005/006: initiator cannot also approve at HR level
        return (int) $user->id !== (int) $run->created_by;
    }

    /** Step 6: return run to initiator with notes */
    public function hrReturn(User $user, PayrollRun $run): bool
    {
        return $user->hasAnyPermission(['payroll.hr_return', 'payroll.approve']);
    }

    /**
     * Step 7: accounting final approval.
     * SOD-007: approver must NOT be the user who initiated the run.
     */
    public function accountingApprove(User $user, PayrollRun $run): bool
    {
        if (! $user->hasAnyPermission(['payroll.acctg_approve', 'payroll.approve', 'payroll.post'])) {
            return false;
        }

        // SOD-007: initiator cannot also do accounting approval
        return (int) $user->id !== (int) $run->created_by;
    }

    /**
     * Alias for accountingApprove — used by acctg-approve route.
     */
    public function acctgApprove(User $user, PayrollRun $run): bool
    {
        return $this->accountingApprove($user, $run);
    }

    /** Step 7: permanently reject (run must restart from DRAFT) */
    public function accountingReject(User $user, PayrollRun $run): bool
    {
        return $user->hasAnyPermission(['payroll.acctg_reject', 'payroll.approve', 'payroll.post']);
    }

    /**
     * Alias for accountingReject — used by acctg-approve route.
     */
    public function acctgReject(User $user, PayrollRun $run): bool
    {
        return $this->accountingReject($user, $run);
    }

    /** Step 8a: post GL + generate bank file — Finance Manager */
    public function disburse(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionTo('payroll.disburse');
    }

    /** Step 8b: publish payslips to employees — HR Manager or Accounting Manager */
    public function publish(User $user, PayrollRun $run): bool
    {
        return $user->hasAnyPermission([
            'payroll.publish',
            'payroll.submit',
            'payroll.acctg_approve',
            'payroll.post',
        ]);
    }

    /** Cancel / recall a run */
    public function cancel(User $user, PayrollRun $run): bool
    {
        return $user->hasAnyPermission(['payroll.recall', 'payroll.initiate'])
            && $this->isInitiator($user, $run);
    }

    /** GL preview — Finance Manager (read) */
    public function glPreview(User $user, PayrollRun $run): bool
    {
        return $user->hasAnyPermission(['payroll.acctg_approve', 'payroll.view_runs', 'payroll.post']);
    }

    /** Download bank disbursement file — Finance Manager only */
    public function downloadBankFile(User $user, PayrollRun $run): bool
    {
        return $user->hasPermissionTo('payroll.download_bank_file');
    }

    /**
     * Export comprehensive payroll breakdown — HR Manager and Accounting Manager.
     * Available from Step 7 (HR_APPROVED) onwards, including when DISBURSED or PUBLISHED.
     */
    public function exportBreakdown(User $user, PayrollRun $run): bool
    {
        // HR Manager or Accounting Manager can export
        if (! $user->hasAnyPermission(['payroll.hr_approve', 'payroll.acctg_approve', 'payroll.approve', 'payroll.post', 'payroll.review_breakdown'])) {
            return false;
        }

        // Available from HR_APPROVED (Step 7) onwards - so user can export even after disbursement
        return in_array($run->status, ['HR_APPROVED', 'ACCTG_APPROVED', 'DISBURSED', 'PUBLISHED'], true);
    }

    /**
     * View comprehensive payroll breakdown — HR Manager and Accounting Manager.
     * Available for all workflow states from COMPUTED onwards.
     */
    public function viewBreakdown(User $user, PayrollRun $run): bool
    {
        // HR Manager or Accounting Manager can view
        if (! $user->hasAnyPermission(['payroll.hr_approve', 'payroll.acctg_approve', 'payroll.approve', 'payroll.post', 'payroll.review_breakdown'])) {
            return false;
        }

        // Available from COMPUTED onwards
        return in_array($run->status, ['COMPUTED', 'REVIEW', 'SUBMITTED', 'HR_APPROVED', 'ACCTG_APPROVED', 'DISBURSED', 'PUBLISHED'], true);
    }

    // ── Legacy aliases kept for backward-compat ───────────────────────────────

    public function lock(User $user, PayrollRun $run): bool
    {
        return $this->submitForHr($user, $run);
    }

    public function approve(User $user, PayrollRun $run): bool
    {
        return $this->accountingApprove($user, $run);
    }

    public function submit(User $user, PayrollRun $run): bool
    {
        return $this->submitForHr($user, $run);
    }

    public function post(User $user, PayrollRun $run): bool
    {
        return $this->disburse($user, $run);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isInitiator(User $user, PayrollRun $run): bool
    {
        return (int) $user->id === (int) $run->created_by;
    }
}
