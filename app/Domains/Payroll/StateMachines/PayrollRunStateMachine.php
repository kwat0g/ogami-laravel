<?php

declare(strict_types=1);

namespace App\Domains\Payroll\StateMachines;

use App\Domains\Payroll\Models\PayrollRun;
use App\Shared\Exceptions\DomainException;

/**
 * PayrollRun state machine — 8-step workflow (Workflow Design v1.0).
 *
 * Valid transitions:
 *
 *   DRAFT           → SCOPE_SET         (scope confirmed)
 *   SCOPE_SET       → DRAFT             (go back to edit)
 *   SCOPE_SET       → PRE_RUN_CHECKED   (all PR checks pass + warnings acked)
 *   PRE_RUN_CHECKED → SCOPE_SET         (go back to scope)
 *   PRE_RUN_CHECKED → PROCESSING        (begin computation)
 *   PROCESSING      → COMPUTED          (all employees processed)
 *   PROCESSING      → FAILED            (computation batch failed)
 *   COMPUTED        → REVIEW            (initiator starts review)
 *   COMPUTED        → DRAFT             (return to restart)
 *   REVIEW          → SUBMITTED         (submit for HR approval)
 *   REVIEW          → DRAFT             (return to restart)
 *   SUBMITTED       → HR_APPROVED       (HR Manager approves — SoD gated)
 *   SUBMITTED       → RETURNED          (HR Manager returns with reason)
 *   SUBMITTED       → REJECTED          (Accounting Manager permanently rejects — bypasses HR)
 *   HR_APPROVED     → ACCTG_APPROVED    (Accounting Manager approves — SoD gated)
 *   HR_APPROVED     → REJECTED          (Accounting Manager permanently rejects)
 *   ACCTG_APPROVED  → DISBURSED         (bank file generated + GL posted)
 *   DISBURSED       → PUBLISHED         (payslips released to employees)
 *   RETURNED        → DRAFT             (must restart from Step 1)
 *   REJECTED        → DRAFT             (must restart from Step 1)
 *   FAILED          → PRE_RUN_CHECKED   (retry computation)
 *
 * Legacy transitions (kept for backward compatibility with old status values):
 *   draft       → locked|cancelled
 *   locked      → processing|draft|cancelled
 *   processing  → completed|failed|cancelled
 *   completed   → submitted|cancelled
 *   submitted   → approved|completed
 *   approved    → posted
 *   failed      → locked
 *   posted      → []
 *   cancelled   → []
 */
final class PayrollRunStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        // ── New v1.0 workflow states ────────────────────────────────────────
        'DRAFT' => ['SCOPE_SET', 'cancelled'],
        'SCOPE_SET' => ['DRAFT', 'PRE_RUN_CHECKED', 'cancelled'],
        'PRE_RUN_CHECKED' => ['SCOPE_SET', 'PROCESSING', 'cancelled'],
        'PROCESSING' => ['COMPUTED', 'FAILED', 'cancelled'],
        'COMPUTED' => ['REVIEW', 'DRAFT', 'cancelled', 'SUBMITTED'],
        'REVIEW' => ['SUBMITTED', 'DRAFT', 'cancelled'],
        'SUBMITTED' => ['HR_APPROVED', 'RETURNED', 'REJECTED', 'cancelled'],
        'HR_APPROVED' => ['ACCTG_APPROVED', 'REJECTED', 'cancelled'],
        'ACCTG_APPROVED' => ['VP_APPROVED', 'REJECTED', 'cancelled'],
        'VP_APPROVED' => ['DISBURSED', 'cancelled'],
        'DISBURSED' => ['PUBLISHED'],
        'PUBLISHED' => [],
        'FAILED' => ['PRE_RUN_CHECKED', 'DRAFT', 'cancelled'],
        'RETURNED' => ['DRAFT', 'cancelled'],
        'REJECTED' => ['DRAFT', 'cancelled'],

        // ── M1 FIX: Legacy lowercase values (DEPRECATED — scheduled for removal) ──
        // These legacy statuses exist for backward compatibility with runs
        // created before the v1.0 workflow migration. New runs should ONLY use
        // the UPPERCASE status values above. Migration path:
        //   1. Run a one-time data fix: UPDATE payroll_runs SET status = UPPER(status)
        //   2. Remove these entries after all legacy runs are migrated
        'draft' => ['locked', 'cancelled', 'SCOPE_SET'],
        'locked' => ['processing', 'draft', 'cancelled'],
        'processing' => ['completed', 'failed', 'cancelled', 'COMPUTED', 'FAILED'],
        'completed' => ['submitted', 'cancelled', 'REVIEW'],
        'submitted' => ['approved', 'completed', 'HR_APPROVED', 'RETURNED'],
        'approved' => ['posted', 'ACCTG_APPROVED'],
        'failed' => ['locked', 'PRE_RUN_CHECKED'],
        'posted' => [],
        'cancelled' => [],
    ];

    public function canTransition(PayrollRun $run, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$run->status] ?? [], true);
    }

    /**
     * @throws DomainException
     */
    public function transition(PayrollRun $run, string $to): void
    {
        if (! $this->canTransition($run, $to)) {
            throw new DomainException(
                "Cannot transition payroll run from '{$run->status}' to '{$to}'.",
                'PR_INVALID_TRANSITION',
                422,
                ['current' => $run->status, 'requested' => $to],
            );
        }

        $run->status = $to;

        match ($to) {
            'locked' => ($run->locked_at = now()),
            'posted' => ($run->posted_at = now()),
            'PROCESSING' => ($run->computation_started_at = now()),
            'COMPUTED' => ($run->computation_completed_at = now()),
            'VP_APPROVED' => ($run->vp_approved_at = now()),
            'DISBURSED' => ($run->posted_at = now()),
            'PUBLISHED' => ($run->published_at = now()),
            default => null,
        };

        $run->save();
    }

    /** Returns all statuses this run can move to from its current state. */
    public function allowedNext(PayrollRun $run): array
    {
        return self::TRANSITIONS[$run->status] ?? [];
    }
}
