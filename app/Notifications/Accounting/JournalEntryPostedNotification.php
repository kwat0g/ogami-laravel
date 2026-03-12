<?php

declare(strict_types=1);

namespace App\Notifications\Accounting;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class JournalEntryPostedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly JournalEntry $entry,
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
            'type' => 'accounting.journal_posted',
            'title' => 'Journal Entry Posted',
            'message' => sprintf(
                'Journal entry %s has been posted. Reference: %s.',
                $this->entry->entry_number ?? "JE-{$this->entry->id}",
                $this->entry->reference ?? '—',
            ),
            'action_url' => "/accounting/journal-entries/{$this->entry->ulid}",
            'journal_entry_id' => $this->entry->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
