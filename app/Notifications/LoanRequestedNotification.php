<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Loan\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to HR Managers when an employee submits a new loan application.
 */
final class LoanRequestedNotification extends Notification implements ShouldQueue
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
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Loan $loan): self
    {
        return new self(
            loanId: $loan->id,
            loanUlid: $loan->ulid,
            employeeId: $loan->employee_id,
            employeeName: $loan->employee->full_name,
            loanTypeName: $loan->loanType->name,
            referenceNo: $loan->reference_no,
            principalCentavos: $loan->principal_centavos,
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
        return [
            'type' => 'loan.requested',
            'title' => 'New Loan Application',
            'message' => sprintf(
                '%s has applied for a %s loan of ₱%s (ref: %s).',
                $this->employeeName,
                $this->loanTypeName,
                number_format($this->principalCentavos / 100, 2),
                $this->referenceNo,
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
