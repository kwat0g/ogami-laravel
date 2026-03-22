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

    public function __construct(
        private readonly int $journalEntryId,
        private readonly string $journalEntryUlid,
        private readonly ?string $jeNumber,
        private readonly ?string $description,
        private readonly string $status,
        private readonly string $createdAt,
        private readonly string $updatedAt,
        private readonly int $daysInactive,
        private readonly int $staleDays,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(JournalEntry $journalEntry, int $staleDays): self
    {
        return new self(
            journalEntryId: $journalEntry->id,
            journalEntryUlid: $journalEntry->ulid,
            jeNumber: $journalEntry->je_number ?? null,
            description: $journalEntry->description ?? null,
            status: $journalEntry->status,
            createdAt: $journalEntry->created_at->toDateTimeString(),
            updatedAt: $journalEntry->updated_at->toDateTimeString(),
            daysInactive: (int) $journalEntry->updated_at->diffInDays(now()),
            staleDays: $staleDays,
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
            'type' => 'accounting.journal_entry_stale',
            'title' => 'Journal Entry Flagged as Stale',
            'message' => sprintf(
                'Journal Entry %s has been inactive for %d days and flagged as stale. Please review and submit or cancel.',
                $this->jeNumber ?? "#{$this->journalEntryId}",
                $this->daysInactive
            ),
            'action_url' => "/accounting/journal-entries/{$this->journalEntryUlid}",
            'journal_entry_id' => $this->journalEntryId,
            'je_number' => $this->jeNumber,
            'description' => $this->description,
            'status' => $this->status,
            'stale_days' => $this->staleDays,
            'days_inactive' => $this->daysInactive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
