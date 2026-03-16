<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a journal entry is flagged as stale.
 *
 * Notifies the drafter (creator) that their draft JE has been inactive
 * and flagged as stale after the configured threshold period.
 */
final class JournalEntryStaleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  JournalEntry  $journalEntry  The stale journal entry
     * @param  int  $staleDays  Number of days of inactivity before flagging
     */
    public function __construct(
        private readonly JournalEntry $journalEntry,
        private readonly int $staleDays
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
        $daysInactive = (int) $this->journalEntry->updated_at->diffInDays(now());

        return [
            'type' => 'accounting.journal_entry_stale',
            'title' => 'Journal Entry Flagged as Stale',
            'message' => sprintf(
                'Journal Entry %s has been inactive for %d days and flagged as stale. Please review and submit or cancel.',
                $this->journalEntry->je_number ?? "#{$this->journalEntry->id}",
                $daysInactive
            ),
            'action_url' => "/accounting/journal-entries/{$this->journalEntry->ulid}",
            'journal_entry_id' => $this->journalEntry->id,
            'je_number' => $this->journalEntry->je_number,
            'description' => $this->journalEntry->description,
            'status' => $this->journalEntry->status,
            'stale_days' => $this->staleDays,
            'days_inactive' => $daysInactive,
            'created_at' => $this->journalEntry->created_at->toDateTimeString(),
            'updated_at' => $this->journalEntry->updated_at->toDateTimeString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
