<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Daily AP digest notification sent to Accounting Managers.
 *
 * Contains summary of pending, approved, overdue, and due-this-week invoices.
 */
final class ApDailyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  object  $summary  Object with pending_count, approved_count, overdue_count,
     *                           due_this_week_count, outstanding_balance_centavos
     * @param  string  $date  Date string for the digest (Y-m-d format)
     */
    public function __construct(
        private readonly object $summary,
        private readonly string $date
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
        $outstandingPesos = (float) ($this->summary->outstanding_balance ?? 0);

        return [
            'type' => 'ap.daily_digest',
            'title' => 'AP Daily Digest',
            'message' => sprintf(
                'AP Summary for %s: %d pending, %d approved, %d overdue, %d due this week. Outstanding balance: ₱%s',
                $this->date,
                (int) ($this->summary->pending_count ?? 0),
                (int) ($this->summary->approved_count ?? 0),
                (int) ($this->summary->overdue_count ?? 0),
                (int) ($this->summary->due_this_week_count ?? 0),
                number_format($outstandingPesos, 2)
            ),
            'action_url' => '/accounting/ap/invoices',
            'summary' => [
                'date' => $this->date,
                'pending_count' => (int) ($this->summary->pending_count ?? 0),
                'approved_count' => (int) ($this->summary->approved_count ?? 0),
                'overdue_count' => (int) ($this->summary->overdue_count ?? 0),
                'due_this_week_count' => (int) ($this->summary->due_this_week_count ?? 0),
                'outstanding_balance' => $outstandingPesos,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
