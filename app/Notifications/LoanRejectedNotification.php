<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Loan\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when their loan application is rejected.
 */
final class LoanRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Loan $loan,
        private readonly string $remarks,
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
        return [
            'type' => 'loan.rejected',
            'title' => 'Your Loan Application Was Not Approved',
            'message' => sprintf(
                'Your %s loan application of ₱%s (ref: %s) has been rejected. Reason: %s',
                $this->loan->loanType->name,
                number_format($this->loan->principal_centavos / 100, 2),
                $this->loan->reference_no,
                $this->remarks,
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
