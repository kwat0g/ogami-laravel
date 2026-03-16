<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent across the payroll approval chain and to employees on payslip publication.
 *
 * Audience values:
 *   'payslip'              → employee: their payslip is available
 *   'HR'                   → accounting manager: HR has approved, awaiting acctg sign-off
 *   'HR_RETURNED'          → initiator: HR returned the run for revision
 *   'ACCOUNTING'           → initiator + HR approver: accounting has given final approval
 *   'ACCOUNTING_RETURNED'  → initiator + HR approver: accounting returned for rework (not rejected)
 *   'ACCOUNTING_REJECTED'  → initiator + HR approver: accounting permanently rejected the run
 *   'DISBURSED'            → initiator + HR approver + accounting approver: payroll funds released
 */
final class PayrollApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PayrollRun $run,
        /** 'payslip' targeting individual employee, 'approval' targeting HR Manager */
        private readonly string $audience = 'payslip',
    ) {
        $this->queue = 'notifications';
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        if ($this->audience === 'payslip') {
            return [
                'type' => 'payroll.payslip_ready',
                'title' => 'Your Payslip is Ready',
                'message' => sprintf(
                    'Your payslip for %s (pay date: %s) is now available for download.',
                    $this->run->pay_period_label,
                    $this->run->pay_date
                        ? Carbon::parse($this->run->pay_date)->toFormattedDateString()
                        : 'TBD',
                ),
                'action_url' => '/self-service/payslips',
                'payroll_run_id' => $this->run->id,
            ];
        }

        $payDate = $this->run->pay_date
            ? Carbon::parse($this->run->pay_date)->toFormattedDateString()
            : 'TBD';

        return match ($this->audience) {
            'HR' => [
                'type' => 'payroll.hr_approved',
                'title' => 'Payroll Run HR-Approved — Awaiting Your Accounting Sign-Off',
                'message' => sprintf(
                    'HR has approved payroll run %s (%s). Please review and give final accounting approval. Pay date: %s.',
                    $this->run->reference_no,
                    $this->run->pay_period_label,
                    $payDate,
                ),
                'action_url' => "/payroll/runs/{$this->run->ulid}/acctg-review",
                'payroll_run_id' => $this->run->id,
            ],
            'HR_RETURNED' => [
                'type' => 'payroll.returned',
                'title' => 'Payroll Run Returned for Revision',
                'message' => sprintf(
                    'Payroll run %s (%s) was returned for revision. Please review comments and resubmit.',
                    $this->run->reference_no,
                    $this->run->pay_period_label,
                ),
                'action_url' => "/payroll/runs/{$this->run->ulid}",
                'payroll_run_id' => $this->run->id,
            ],
            'ACCOUNTING' => [
                'type' => 'payroll.accounting_approved',
                'title' => 'Payroll Run Fully Approved',
                'message' => sprintf(
                    'Payroll run %s (%s) received final accounting approval. Pay date: %s. Proceed to disburse.',
                    $this->run->reference_no,
                    $this->run->pay_period_label,
                    $payDate,
                ),
                'action_url' => "/payroll/runs/{$this->run->ulid}/disburse",
                'payroll_run_id' => $this->run->id,
            ],
            'ACCOUNTING_RETURNED' => [
                'type' => 'payroll.accounting_returned',
                'title' => 'Payroll Run Returned by Accounting for Rework',
                'message' => sprintf(
                    'Payroll run %s (%s) was returned by Accounting for corrections. Please review comments and resubmit.',
                    $this->run->reference_no,
                    $this->run->pay_period_label,
                ),
                'action_url' => "/payroll/runs/{$this->run->ulid}",
                'payroll_run_id' => $this->run->id,
            ],
            'ACCOUNTING_REJECTED' => [
                'type' => 'payroll.accounting_rejected',
                'title' => 'Payroll Run Rejected by Accounting',
                'message' => sprintf(
                    'Payroll run %s (%s) was permanently rejected by Accounting. A new run must be initiated.',
                    $this->run->reference_no,
                    $this->run->pay_period_label,
                ),
                'action_url' => "/payroll/runs/{$this->run->ulid}",
                'payroll_run_id' => $this->run->id,
            ],
            'DISBURSED' => [
                'type' => 'payroll.disbursed',
                'title' => 'Payroll Run Disbursed',
                'message' => sprintf(
                    'Payroll run %s (%s) funds have been disbursed. Pay date: %s.',
                    $this->run->reference_no,
                    $this->run->pay_period_label,
                    $payDate,
                ),
                'action_url' => "/payroll/runs/{$this->run->ulid}",
                'payroll_run_id' => $this->run->id,
            ],
            default => [
                'type' => 'payroll.approved',
                'title' => 'Payroll Run Approved',
                'message' => sprintf(
                    'Payroll run %s (%s) has been approved. Pay date: %s.',
                    $this->run->reference_no,
                    $this->run->pay_period_label,
                    $payDate,
                ),
                'action_url' => "/payroll/runs/{$this->run->ulid}",
                'payroll_run_id' => $this->run->id,
            ],
        };
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
