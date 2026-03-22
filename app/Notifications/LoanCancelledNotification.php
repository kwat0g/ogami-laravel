<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Loan\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a loan application is cancelled.
 *
 * Audience:
 *   'employee' → the employee whose loan was cancelled
 *   'hr'       → HR managers who had the pending loan in their queue
 */
final class LoanCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $loanId,
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
                'type' => 'loan.cancelled',
                'title' => 'Loan Application Cancelled',
                'message' => sprintf(
                    '%s has cancelled their %s loan application of ₱%s (ref: %s). No further action required.',
                    $this->employeeName,
                    $this->loanTypeName,
                    $amount,
                    $this->referenceNo,
                ),
                'action_url' => '/hr/loans',
                'loan_id' => $this->loanId,
                'employee_id' => $this->employeeId,
            ];
        }

        return [
            'type' => 'loan.cancelled',
            'title' => 'Your Loan Application Has Been Cancelled',
            'message' => sprintf(
                'Your %s loan application of ₱%s (ref: %s) has been cancelled. You may submit a new application if needed.',
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
