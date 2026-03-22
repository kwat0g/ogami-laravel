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

        return match ($this->audience) {
            'hr' => [
                'type' => 'loan.disbursed',
                'title' => 'Loan Disbursed',
                'message' => sprintf(
                    'The %s loan for %s (ref: %s, ₱%s) has been successfully disbursed.',
                    $this->loanTypeName,
                    $this->employeeName,
                    $this->referenceNo,
                    $amount,
                ),
                'action_url' => "/hr/loans/{$this->loanUlid}",
                'loan_id' => $this->loanId,
                'employee_id' => $this->employeeId,
            ],
            'accounting' => [
                'type' => 'loan.disbursed',
                'title' => 'Loan Disbursed',
                'message' => sprintf(
                    'The %s loan for %s (ref: %s, ₱%s) has been disbursed. GL entries have been posted.',
                    $this->loanTypeName,
                    $this->employeeName,
                    $this->referenceNo,
                    $amount,
                ),
                'action_url' => "/accounting/loans/{$this->loanUlid}",
                'loan_id' => $this->loanId,
                'employee_id' => $this->employeeId,
            ],
            default => [
                'type' => 'loan.disbursed',
                'title' => 'Your Loan Has Been Disbursed',
                'message' => sprintf(
                    'Your %s loan of ₱%s (ref: %s) has been released. Please check with payroll for deduction start date.',
                    $this->loanTypeName,
                    $amount,
                    $this->referenceNo,
                ),
                'action_url' => '/me/loans',
                'loan_id' => $this->loanId,
            ],
        };
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
