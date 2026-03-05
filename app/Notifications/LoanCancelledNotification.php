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
        $loanTypeName = $this->loan->loanType->name;
        $amount = number_format($this->loan->principal_centavos / 100, 2);
        $ref = $this->loan->reference_no;

        if ($this->audience === 'hr') {
            return [
                'type' => 'loan.cancelled',
                'title' => 'Loan Application Cancelled',
                'message' => sprintf(
                    '%s has cancelled their %s loan application of ₱%s (ref: %s). No further action required.',
                    $this->loan->employee->full_name,
                    $loanTypeName,
                    $amount,
                    $ref,
                ),
                'action_url' => '/hr/loans',
                'loan_id' => $this->loan->id,
                'employee_id' => $this->loan->employee_id,
            ];
        }

        return [
            'type' => 'loan.cancelled',
            'title' => 'Your Loan Application Has Been Cancelled',
            'message' => sprintf(
                'Your %s loan application of ₱%s (ref: %s) has been cancelled. You may submit a new application if needed.',
                $loanTypeName,
                $amount,
                $ref,
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
