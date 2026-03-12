<?php

declare(strict_types=1);

namespace App\Notifications\Accounting;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class RecurringJournalGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $generatedCount,
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
            'type' => 'accounting.recurring_generated',
            'title' => 'Recurring Journals Generated',
            'message' => sprintf(
                '%d recurring journal entries have been auto-generated and posted.',
                $this->generatedCount,
            ),
            'action_url' => '/accounting/journal-entries',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
