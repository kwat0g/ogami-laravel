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
        private readonly int $entryId,
        private readonly string $entryUlid,
        private readonly ?string $entryNumber,
        private readonly ?string $reference,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(JournalEntry $entry): self
    {
        return new self(
            entryId: $entry->id,
            entryUlid: $entry->ulid,
            entryNumber: $entry->entry_number ?? null,
            reference: $entry->reference ?? null,
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
            'type' => 'accounting.journal_posted',
            'title' => 'Journal Entry Posted',
            'message' => sprintf(
                'Journal entry %s has been posted. Reference: %s.',
                $this->entryNumber ?? "JE-{$this->entryId}",
                $this->reference ?? '—',
            ),
            'action_url' => "/accounting/journal-entries/{$this->entryUlid}",
            'journal_entry_id' => $this->entryId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
