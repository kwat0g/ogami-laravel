<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Models\PayrollRunApproval;
use App\Domains\Payroll\StateMachines\PayrollRunStateMachine;
use App\Models\User;
use App\Notifications\PayrollApprovedNotification;
use App\Notifications\PayrollCancelledNotification;
use App\Notifications\PayrollSubmittedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * PayrollWorkflowService — Steps 5 through 8 of the payroll run wizard.
 *
 * Handles the approval chain and publication:
 *   Step 5 → 6: Submit run for HR Manager review
 *   Step 6: HR Manager approve or return
 *   Step 7: Accounting Manager final approve or reject
 *   Step 8: Disburse (GL post + bank file) + Publish payslips
 *
 * SoD enforcement:
 *   SOD-005/006: hr_approved_by_id != initiated_by_id (DB CHECK + service layer)
 *   SOD-007:     acctg_approved_by_id != initiated_by_id (DB CHECK + service layer)
 */
final class PayrollWorkflowService implements ServiceContract
{
    public function __construct(
        private readonly PayrollRunStateMachine $stateMachine,
        private readonly PayrollPostingService $postingService,
    ) {}

    /**
     * Step 5 → Accounting: Submit the reviewed run for Accounting Manager approval.
     * Transitions COMPUTED|REVIEW → SUBMITTED.
     * HR Review is intentionally skipped — the HR Manager initiates payroll runs,
     * so SoD requires Accounting Manager (not HR) as the approver.
     */
    public function submitForHrApproval(PayrollRun $run, int $submitterId): PayrollRun
    {
        // Idempotent — already submitted (e.g. double-click or page refresh)
        if (strtoupper((string) $run->status) === 'SUBMITTED') {
            return $run;
        }

        // Auto-advance COMPUTED → REVIEW if not already in REVIEW
        if (strtoupper((string) $run->status) === 'COMPUTED') {
            $this->stateMachine->transition($run, 'REVIEW');
        }

        $this->stateMachine->transition($run, 'SUBMITTED');

        // Notify only Accounting Officers who can actually approve (SoD check)
        try {
            $acctgManagers = User::role('officer')
                ->where('id', '!=', $run->created_by)
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('payroll.acctg_approve'));
            if ($acctgManagers->isNotEmpty()) {
                Notification::send($acctgManagers, PayrollSubmittedNotification::fromModel($run));
            }
        } catch (\Throwable) {
            // Non-fatal — notification failure should not block the workflow
        }

        return $run->fresh();
    }

    /**
     * Step 6: HR Manager approves.
     * Transitions SUBMITTED → HR_APPROVED.
     * Enforces SOD-005/006.
     *
     * @param array{
     *   checkboxes_checked: string[],
     *   comments?: string|null,
     * } $data
     *
     * @throws SodViolationException
     */
    public function hrApprove(PayrollRun $run, int $approverId, array $data): PayrollRun
    {
        // Idempotent — already HR-approved (e.g. double-click or page refresh)
        if (strtoupper((string) $run->status) === 'HR_APPROVED') {
            return $run;
        }

        if ($run->initiated_by_id && $approverId === (int) $run->initiated_by_id) {
            throw new SodViolationException(
                'SOD-005: The HR Manager who approves cannot be the same person who initiated this run.',
            );
        }

        DB::transaction(function () use ($run, $approverId, $data) {
            $run->hr_approved_by_id = $approverId;
            $run->hr_approved_at = now();
            $run->save();

            PayrollRunApproval::create([
                'payroll_run_id' => $run->id,
                'stage' => 'HR_REVIEW',
                'action' => 'APPROVED',
                'actor_id' => $approverId,
                'comments' => $data['comments'] ?? null,
                'checkboxes_checked' => $data['checkboxes_checked'] ?? [],
                'acted_at' => now(),
            ]);

            $this->stateMachine->transition($run, 'HR_APPROVED');
        });

        // Notify Accounting Officer(s) who can actually approve (SoD check)
        try {
            $acctgManagers = User::role('officer')
                ->where('id', '!=', $run->created_by)
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('payroll.acctg_approve'));
            if ($acctgManagers->isNotEmpty()) {
                Notification::send($acctgManagers, PayrollApprovedNotification::fromModel($run, 'HR'));
            }
        } catch (\Throwable) {
        }

        return $run->fresh();
    }

    /**
     * Step 6 (return path): HR Manager returns run to initiator.
     * Transitions SUBMITTED → RETURNED → DRAFT.
     *
     * @throws \InvalidArgumentException
     */
    public function hrReturn(PayrollRun $run, int $actorId, string $comments): PayrollRun
    {
        // Idempotent — already returned/reset to DRAFT
        if (strtoupper((string) $run->status) === 'DRAFT') {
            return $run;
        }

        if (empty(trim($comments))) {
            throw new DomainException(
                'A comment explaining the reason for return is required.',
                'PR_RETURN_COMMENT_REQUIRED',
                422,
            );
        }

        DB::transaction(function () use ($run, $actorId, $comments) {
            PayrollRunApproval::create([
                'payroll_run_id' => $run->id,
                'stage' => 'HR_REVIEW',
                'action' => 'RETURNED',
                'actor_id' => $actorId,
                'comments' => $comments,
                'acted_at' => now(),
            ]);

            $this->stateMachine->transition($run, 'RETURNED');
            $this->stateMachine->transition($run, 'DRAFT');
        });

        // Notify initiator
        try {
            if ($run->initiated_by_id) {
                $initiator = User::find($run->initiated_by_id);
                if ($initiator) {
                    $initiator->notify(PayrollApprovedNotification::fromModel($run, 'HR_RETURNED'));
                }
            }
        } catch (\Throwable) {
        }

        return $run->fresh();
    }

    /**
     * Step 7: Accounting Manager approval.
     * Transitions HR_APPROVED → ACCTG_APPROVED.
     * Enforces SOD-007.
     *
     * @param array{
     *   checkboxes_checked: string[],
     *   comments?: string|null,
     * } $data
     *
     * @throws SodViolationException
     */
    public function acctgApprove(PayrollRun $run, int $approverId, array $data): PayrollRun
    {
        // Idempotent — already approved (e.g. double-click or page refresh)
        if (strtoupper((string) $run->status) === 'ACCTG_APPROVED') {
            return $run;
        }

        if ($run->initiated_by_id && $approverId === (int) $run->initiated_by_id) {
            throw new SodViolationException(
                'SOD-007: The Accounting Manager who approves cannot be the same person who initiated this run.',
            );
        }

        DB::transaction(function () use ($run, $approverId, $data) {
            $run->acctg_approved_by_id = $approverId;
            $run->acctg_approved_at = now();
            $run->save();

            PayrollRunApproval::create([
                'payroll_run_id' => $run->id,
                'stage' => 'ACCOUNTING',
                'action' => 'APPROVED',
                'actor_id' => $approverId,
                'comments' => $data['comments'] ?? null,
                'checkboxes_checked' => $data['checkboxes_checked'] ?? [],
                'acted_at' => now(),
            ]);

            $this->stateMachine->transition($run, 'ACCTG_APPROVED');
        });

        // Notify initiator + HR approver that accounting has approved, VP review needed
        try {
            $acctgManagers = User::whereIn('id', array_filter([
                $run->initiated_by_id,
                $run->hr_approved_by_id,
            ]))->get();
            $notif = PayrollApprovedNotification::fromModel($run, 'ACCOUNTING');
            Notification::send($acctgManagers, $notif);
        } catch (\Throwable) {
        }

        return $run->fresh();
    }

    /**
     * Step 7 (reject path): Accounting Manager permanently rejects.
     * Transitions HR_APPROVED → REJECTED → DRAFT.
     * This is permanent — the run must restart from Step 1.
     *
     * @throws \InvalidArgumentException
     */
    public function acctgReject(PayrollRun $run, int $actorId, string $reason): PayrollRun
    {
        // Idempotent — already rejected/reset (e.g. notification crash caused retry)
        $status = strtoupper((string) $run->status);
        if ($status === 'DRAFT' || $status === 'REJECTED') {
            return $run;
        }

        if (empty(trim($reason))) {
            throw new DomainException(
                'A rejection reason is required for permanent rejection.',
                'PR_REJECTION_REASON_REQUIRED',
                422,
            );
        }

        DB::transaction(function () use ($run, $actorId, $reason) {
            PayrollRunApproval::create([
                'payroll_run_id' => $run->id,
                'stage' => 'ACCOUNTING',
                'action' => 'REJECTED',
                'actor_id' => $actorId,
                'comments' => $reason,
                'acted_at' => now(),
            ]);

            $this->stateMachine->transition($run, 'REJECTED');
            $this->stateMachine->transition($run, 'DRAFT');
        });

        // Notify initiator + HR approver
        try {
            $notify = User::whereIn('id', array_filter([
                $run->initiated_by_id,
                $run->hr_approved_by_id,
            ]))->get();
            $notif = PayrollApprovedNotification::fromModel($run, 'ACCOUNTING_REJECTED');
            Notification::send($notify, $notif);
        } catch (\Throwable) {
        }

        return $run->fresh();
    }

    /**
     * Step 7 (return path): Accounting Manager returns for rework.
     * Transitions HR_APPROVED → RETURNED → DRAFT.
     * Unlike rejection, this allows the run to be corrected and resubmitted.
     *
     * @throws \InvalidArgumentException
     */
    public function acctgReturn(PayrollRun $run, int $actorId, string $comments): PayrollRun
    {
        // Idempotent — already returned/reset to DRAFT
        $status = strtoupper((string) $run->status);
        if ($status === 'DRAFT' || $status === 'RETURNED') {
            return $run;
        }

        if (empty(trim($comments))) {
            throw new DomainException(
                'A comment explaining the reason for return is required.',
                'PR_RETURN_COMMENT_REQUIRED',
                422,
            );
        }

        DB::transaction(function () use ($run, $actorId, $comments) {
            PayrollRunApproval::create([
                'payroll_run_id' => $run->id,
                'stage' => 'ACCOUNTING',
                'action' => 'RETURNED',
                'actor_id' => $actorId,
                'comments' => $comments,
                'acted_at' => now(),
            ]);

            $this->stateMachine->transition($run, 'RETURNED');
            $this->stateMachine->transition($run, 'DRAFT');
        });

        // Notify initiator + HR approver
        try {
            $notify = User::whereIn('id', array_filter([
                $run->initiated_by_id,
                $run->hr_approved_by_id,
            ]))->get();
            $notif = PayrollApprovedNotification::fromModel($run, 'ACCOUNTING_RETURNED');
            Notification::send($notify, $notif);
        } catch (\Throwable) {
        }

        return $run->fresh();
    }

    /**
     * Step 7b: VP final approval.
     * Transitions ACCTG_APPROVED → VP_APPROVED.
     * Enforces SOD-008: VP who approves cannot be the same person who initiated.
     *
     * @param array{
     *   checkboxes_checked?: string[],
     *   comments?: string|null,
     * } $data
     *
     * @throws SodViolationException
     */
    public function vpApprove(PayrollRun $run, int $approverId, array $data): PayrollRun
    {
        // Idempotent — already VP-approved
        if (strtoupper((string) $run->status) === 'VP_APPROVED') {
            return $run;
        }

        if ($run->initiated_by_id && $approverId === (int) $run->initiated_by_id) {
            throw new SodViolationException(
                'SOD-008: The VP who approves cannot be the same person who initiated this run.',
            );
        }

        DB::transaction(function () use ($run, $approverId, $data) {
            $run->vp_approved_by_id = $approverId;
            $run->vp_approved_at = now();
            $run->save();

            PayrollRunApproval::create([
                'payroll_run_id' => $run->id,
                'stage' => 'VP_REVIEW',
                'action' => 'APPROVED',
                'actor_id' => $approverId,
                'comments' => $data['comments'] ?? null,
                'checkboxes_checked' => $data['checkboxes_checked'] ?? [],
                'acted_at' => now(),
            ]);

            $this->stateMachine->transition($run, 'VP_APPROVED');
        });

        // Notify initiator, HR approver, and Accounting approver
        try {
            $notify = User::whereIn('id', array_filter([
                $run->initiated_by_id,
                $run->hr_approved_by_id,
                $run->acctg_approved_by_id,
            ]))->get();
            $notif = PayrollApprovedNotification::fromModel($run, 'VP');
            Notification::send($notify, $notif);
        } catch (\Throwable) {
        }

        return $run->fresh();
    }

    /**
     * Step 8a: Disburse — generate bank file, post GL, generate payslip PDFs.
     * Transitions VP_APPROVED → DISBURSED.
     */
    public function disburse(PayrollRun $run): PayrollRun
    {
        // Idempotent — already disbursed (e.g. double-click or page refresh)
        if (strtoupper((string) $run->status) === 'DISBURSED') {
            return $run;
        }

        DB::transaction(function () use ($run) {
            // Post to GL (PayrollAutoPostService handles this)
            $this->postingService->postPayrollRun($run);

            $this->stateMachine->transition($run, 'DISBURSED');
        });

        $run = $run->fresh();

        // Notify initiator, HR approver, and Accounting approver that funds have been released
        try {
            $notify = User::whereIn('id', array_filter([
                $run->initiated_by_id,
                $run->hr_approved_by_id,
                $run->acctg_approved_by_id,
            ]))->get();
            if ($notify->isNotEmpty()) {
                Notification::send($notify, PayrollApprovedNotification::fromModel($run, 'DISBURSED'));
            }
        } catch (\Throwable) {
        }

        return $run;
    }

    /**
     * Step 8b: Publish payslips to employees.
     * Transitions DISBURSED → PUBLISHED.
     *
     * @param array{
     *   publish_at?: string|null,  // null = immediate
     *   notify_email?: bool,
     *   notify_in_app?: bool,
     * } $options
     */
    public function publish(PayrollRun $run, array $options = []): PayrollRun
    {
        // Idempotent — already published
        if (strtoupper((string) $run->status) === 'PUBLISHED') {
            return $run;
        }

        $publishAt = ! empty($options['publish_at']) ? $options['publish_at'] : null;

        if ($publishAt && $publishAt > now()->toDateTimeString()) {
            // Schedule for future
            $run->publish_scheduled_at = $publishAt;
            $run->save();

            // In a real implementation, a scheduled job would trigger this.
            // For now we transition immediately to simplify the state machine.
        }

        $this->stateMachine->transition($run, 'PUBLISHED');

        // Notify each employee in the run that their payslip is ready.
        // Use individual notify() calls so one failure never blocks the rest.
        try {
            $employeeIds = $run->details()->pluck('employee_id')->filter()->unique()->values()->all();
            if (! empty($employeeIds)) {
                // employees.user_id → users.id (not users.employee_id)
                $users = User::whereHas('employee', fn ($q) => $q->whereIn('id', $employeeIds))->get();
                foreach ($users as $user) {
                    try {
                        $user->notify(PayrollApprovedNotification::fromModel($run, 'payslip'));
                    } catch (\Throwable) {
                        // Non-fatal — one user failing must not block others
                    }
                }
            }
        } catch (\Throwable) {
            // Non-fatal
        }

        return $run->fresh();
    }

    /**
     * Flag or un-flag an individual employee's payroll detail for review.
     */
    public function flagEmployee(PayrollRun $run, int $detailId, string $flag, ?string $note): void
    {
        $run->details()
            ->where('id', $detailId)
            ->update([
                'employee_flag' => $flag,
                'review_note' => $note,
            ]);
    }

    /**
     * Cancel a payroll run that has not yet been disbursed.
     * Transitions any pre-disburse status → cancelled.
     * Notifies the initiator and, if already HR-approved, the HR approver as well.
     *
     * @throws \InvalidArgumentException
     */
    public function cancel(PayrollRun $run, int $actorId, ?string $reason = null): PayrollRun
    {
        // Idempotent — already cancelled (e.g. double-click or page refresh)
        if (strtoupper((string) $run->status) === 'CANCELLED') {
            return $run;
        }

        // Cannot cancel a run that has already been disbursed or published
        if (in_array(strtoupper((string) $run->status), ['DISBURSED', 'PUBLISHED'], true)) {
            throw new DomainException(
                sprintf('Payroll run in status "%s" cannot be cancelled.', $run->status),
                'PR_NOT_CANCELLABLE',
                409,
            );
        }

        DB::transaction(function () use ($run, $actorId, $reason) {
            PayrollRunApproval::create([
                'payroll_run_id' => $run->id,
                'stage' => 'CANCEL',
                'action' => 'CANCELLED',
                'actor_id' => $actorId,
                'comments' => $reason,
                'acted_at' => now(),
            ]);

            $this->stateMachine->transition($run, 'cancelled');
        });

        // Notify initiator and (if already HR-approved) the HR approver
        try {
            $notify = User::whereIn('id', array_filter([
                $run->initiated_by_id,
                $run->hr_approved_by_id,
            ]))->where('id', '!=', $actorId)->get();

            if ($notify->isNotEmpty()) {
                Notification::send($notify, PayrollCancelledNotification::fromModel($run, $reason));
            }
        } catch (\Throwable) {
            // Non-fatal
        }

        return $run->fresh();
    }
}
