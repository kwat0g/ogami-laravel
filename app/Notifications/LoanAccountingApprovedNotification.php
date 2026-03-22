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
        private readonly int $loanId,
        private readonly string $loanUlid,
        private readonly int $employeeId,
        private readonly string $employeeName,
        private readonly string $loanTypeName,
        private readonly string $referenceNo,
        private readonly int $principalCentavos,
        private readonly string $audience = 'employee',
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Loan $loan, string $audience = 'employee'): self
    {
        return new self(
            loanId: $loan->id,
            loanUlid: $loan->ulid,
            employeeId: $loan->employee_id,
            employeeName: $loan->employee->full_name,
            loanTypeName: $loan->loanType->name,
            referenceNo: $loan->reference_no,
            principalCentavos: $loan->principal_centavos,
            audience: $audience,
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $amount = number_format($this->principalCentavos / 100, 2);

        if ($this->audience === 'hr') {
            return [
                'type' => 'loan.accounting_approved',
                'title' => 'Loan Cleared for Disbursement',
                'message' => sprintf(
                    'The %s loan for %s (ref: %s, ₱%s) has been approved by Accounting and is ready for disbursement.',
                    $this->loanTypeName,
                    $this->employeeName,
                    $this->referenceNo,
                    $amount,
                ),
                'action_url' => "/hr/loans/{$this->loanUlid}",
                'loan_id' => $this->loanId,
            ];
        }

        return [
            'type' => 'loan.ready_for_disbursement',
            'title' => 'Your Loan Has Been Fully Approved',
            'message' => sprintf(
                'Your %s loan of ₱%s (ref: %s) has been approved by Accounting and is now ready for disbursement.',
                $this->loanTypeName,
                $amount,
                $this->referenceNo,
            ),
            'action_url' => '/me/loans',
            'loan_id' => $this->loanId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
