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
        private readonly int $loanId,
        private readonly string $loanUlid,
        private readonly string $employeeName,
        private readonly string $loanTypeName,
        private readonly string $referenceNo,
        private readonly int $principalCentavos,
        private readonly string $audience = 'employee',
        private readonly ?string $remarks = null,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(
        Loan $loan,
        string $audience = 'employee',
        ?string $remarks = null
    ): self {
        return new self(
            loanId: $loan->id,
            loanUlid: $loan->ulid,
            employeeName: $loan->employee->full_name,
            loanTypeName: $loan->loanType->name,
            referenceNo: $loan->reference_no,
            principalCentavos: $loan->principal_centavos,
            audience: $audience,
            remarks: $remarks,
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

        if ($this->audience === 'accounting') {
            return [
                'type' => 'loan.pending_accounting',
                'title' => 'Loan Approved by HR — Awaiting Accounting Approval',
                'message' => sprintf(
                    'HR has approved a %s loan of ₱%s for %s (ref: %s). Please review and approve for disbursement.',
                    $this->loanTypeName,
                    $amount,
                    $this->employeeName,
                    $this->referenceNo,
                ),
                'action_url' => "/accounting/loans/{$this->loanUlid}",
                'loan_id' => $this->loanId,
            ];
        }

        return [
            'type' => 'loan.approved',
            'title' => 'Your Loan Application Has Been Approved',
            'message' => sprintf(
                'Your %s loan application of ₱%s (ref: %s) has been approved by HR.%s',
                $this->loanTypeName,
                $amount,
                $this->referenceNo,
                $this->remarks ? ' Remarks: '.$this->remarks : '',
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
