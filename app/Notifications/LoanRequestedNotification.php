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

    public function __construct(private readonly Loan $loan)
    {
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
        return [
            'type' => 'loan.requested',
            'title' => 'New Loan Application',
            'message' => sprintf(
                '%s has applied for a %s loan of ₱%s (ref: %s).',
                $this->loan->employee->full_name,
                $this->loan->loanType->name,
                number_format($this->loan->principal_centavos / 100, 2),
                $this->loan->reference_no,
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
