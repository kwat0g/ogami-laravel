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
        private readonly Loan $loan,
        private readonly string $audience = 'hr',
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
        $loanTypeName = $this->loan->loanType->name;
        $outstanding = number_format($this->loan->outstanding_balance_centavos / 100, 2);
        $employeeName = $this->loan->employee->full_name;
        $ref = $this->loan->reference_no;

        if ($this->audience === 'accounting') {
            return [
                'type' => 'loan.written_off',
                'title' => 'Loan Written Off — GL Adjustment Required',
                'message' => sprintf(
                    'The %s loan for %s (ref: %s) has been written off. Outstanding balance of ₱%s may require a GL adjustment entry.',
                    $loanTypeName,
                    $employeeName,
                    $ref,
                    $outstanding,
                ),
                'action_url' => "/accounting/loans/{$this->loan->ulid}",
                'loan_id' => $this->loan->id,
                'employee_id' => $this->loan->employee_id,
            ];
        }

        return [
            'type' => 'loan.written_off',
            'title' => 'Loan Written Off — Balance Flagged for Review',
            'message' => sprintf(
                'The %s loan for %s (ref: %s) has been written off with ₱%s outstanding. Please review for final pay deduction (LN-009).',
                $loanTypeName,
                $employeeName,
                $ref,
                $outstanding,
            ),
            'action_url' => "/hr/loans/{$this->loan->ulid}",
            'loan_id' => $this->loan->id,
            'employee_id' => $this->loan->employee_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
