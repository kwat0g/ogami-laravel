<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Loan\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a loan has been disbursed (funds released to the employee).
 *
 * Audience:
 *   'employee'   → the employee receiving the loan funds
 *   'hr'         → HR Manager who approved the loan
 *   'accounting' → Accounting Manager who cleared the loan for disbursement
 */
final class LoanDisbursedNotification extends Notification implements ShouldQueue
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
        $employeeName = $this->loan->employee->full_name;
        $loanTypeName = $this->loan->loanType->name;
        $amount = number_format($this->loan->principal_centavos / 100, 2);
        $ref = $this->loan->reference_no;

        return match ($this->audience) {
            'hr' => [
                'type' => 'loan.disbursed',
                'title' => 'Loan Disbursed',
                'message' => sprintf(
                    'The %s loan for %s (ref: %s, ₱%s) has been successfully disbursed.',
                    $loanTypeName,
                    $employeeName,
                    $ref,
                    $amount,
                ),
                'action_url' => "/hr/loans/{$this->loan->ulid}",
                'loan_id' => $this->loan->id,
                'employee_id' => $this->loan->employee_id,
            ],
            'accounting' => [
                'type' => 'loan.disbursed',
                'title' => 'Loan Disbursed',
                'message' => sprintf(
                    'The %s loan for %s (ref: %s, ₱%s) has been disbursed. GL entries have been posted.',
                    $loanTypeName,
                    $employeeName,
                    $ref,
                    $amount,
                ),
                'action_url' => "/accounting/loans/{$this->loan->ulid}",
                'loan_id' => $this->loan->id,
                'employee_id' => $this->loan->employee_id,
            ],
            default => [
                'type' => 'loan.disbursed',
                'title' => 'Your Loan Has Been Disbursed',
                'message' => sprintf(
                    'Your %s loan of ₱%s (ref: %s) has been released. Please check with payroll for deduction start date.',
                    $loanTypeName,
                    $amount,
                    $ref,
                ),
                'action_url' => '/me/loans',
                'loan_id' => $this->loan->id,
            ],
        };
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
