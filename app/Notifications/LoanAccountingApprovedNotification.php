<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Loan\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when Accounting has approved their loan (ready for disbursement).
 * Also sent to the HR Manager and the person who approved the loan to inform them.
 *
 * Audience:
 *   'employee' → the employee whose loan was cleared for disbursement
 *   'hr'       → HR manager who originally approved the loan
 */
final class LoanAccountingApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Loan $loan,
        private readonly string $audience = 'employee',
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
        if ($this->audience === 'hr') {
            return [
                'type' => 'loan.accounting_approved',
                'title' => 'Loan Cleared for Disbursement',
                'message' => sprintf(
                    'The %s loan for %s (ref: %s, ₱%s) has been approved by Accounting and is ready for disbursement.',
                    $this->loan->loanType->name,
                    $this->loan->employee->full_name,
                    $this->loan->reference_no,
                    number_format($this->loan->principal_centavos / 100, 2),
                ),
                'action_url' => "/hr/loans/{$this->loan->ulid}",
                'loan_id' => $this->loan->id,
            ];
        }

        return [
            'type' => 'loan.ready_for_disbursement',
            'title' => 'Your Loan Has Been Fully Approved',
            'message' => sprintf(
                'Your %s loan of ₱%s (ref: %s) has been approved by Accounting and is now ready for disbursement.',
                $this->loan->loanType->name,
                number_format($this->loan->principal_centavos / 100, 2),
                $this->loan->reference_no,
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
