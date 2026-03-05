<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Loan\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when their loan application is HR-approved.
 * Also sent to Accounting Managers to notify them a loan awaits accounting approval.
 *
 * Audience:
 *   'employee'   → employee whose loan was approved
 *   'accounting' → accounting managers awaiting their approval step
 */
final class LoanApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Loan $loan,
        private readonly string $audience = 'employee',
        private readonly ?string $remarks = null,
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
        if ($this->audience === 'accounting') {
            return [
                'type' => 'loan.pending_accounting',
                'title' => 'Loan Approved by HR — Awaiting Accounting Approval',
                'message' => sprintf(
                    'HR has approved a %s loan of ₱%s for %s (ref: %s). Please review and approve for disbursement.',
                    $this->loan->loanType->name,
                    number_format($this->loan->principal_centavos / 100, 2),
                    $this->loan->employee->full_name,
                    $this->loan->reference_no,
                ),
                'action_url' => "/accounting/loans/{$this->loan->ulid}",
                'loan_id' => $this->loan->id,
            ];
        }

        return [
            'type' => 'loan.approved',
            'title' => 'Your Loan Application Has Been Approved',
            'message' => sprintf(
                'Your %s loan application of ₱%s (ref: %s) has been approved by HR.%s',
                $this->loan->loanType->name,
                number_format($this->loan->principal_centavos / 100, 2),
                $this->loan->reference_no,
                $this->remarks ? ' Remarks: '.$this->remarks : '',
            ),
            'action_url' => '/me/loans',
            'loan_id' => $this->loan->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
