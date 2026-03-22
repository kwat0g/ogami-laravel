<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Loan\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a loan is written off (LN-009).
 * Alerts HR and Accounting that the outstanding balance needs to be reviewed
 * and potentially recovered from the employee's final pay.
 *
 * Audience:
 *   'hr'         → HR managers — outstanding balance flagged for review
 *   'accounting' → Accounting managers — GL adjustment may be required
 */
final class LoanWrittenOffNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $loanId,
        private readonly string $loanUlid,
        private readonly int $employeeId,
        private readonly string $employeeName,
        private readonly string $loanTypeName,
        private readonly string $referenceNo,
        private readonly int $outstandingBalanceCentavos,
        private readonly string $audience = 'hr',
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Loan $loan, string $audience = 'hr'): self
    {
        return new self(
            loanId: $loan->id,
            loanUlid: $loan->ulid,
            employeeId: $loan->employee_id,
            employeeName: $loan->employee->full_name,
            loanTypeName: $loan->loanType->name,
            referenceNo: $loan->reference_no,
            outstandingBalanceCentavos: $loan->outstanding_balance_centavos,
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
        $outstanding = number_format($this->outstandingBalanceCentavos / 100, 2);

        if ($this->audience === 'accounting') {
            return [
                'type' => 'loan.written_off',
                'title' => 'Loan Written Off — GL Adjustment Required',
                'message' => sprintf(
                    'The %s loan for %s (ref: %s) has been written off. Outstanding balance of ₱%s may require a GL adjustment entry.',
                    $this->loanTypeName,
                    $this->employeeName,
                    $this->referenceNo,
                    $outstanding,
                ),
                'action_url' => "/accounting/loans/{$this->loanUlid}",
                'loan_id' => $this->loanId,
                'employee_id' => $this->employeeId,
            ];
        }

        return [
            'type' => 'loan.written_off',
            'title' => 'Loan Written Off — Balance Flagged for Review',
            'message' => sprintf(
                'The %s loan for %s (ref: %s) has been written off with ₱%s outstanding. Please review for final pay deduction (LN-009).',
                $this->loanTypeName,
                $this->employeeName,
                $this->referenceNo,
                $outstanding,
            ),
            'action_url' => "/hr/loans/{$this->loanUlid}",
            'loan_id' => $this->loanId,
            'employee_id' => $this->employeeId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
