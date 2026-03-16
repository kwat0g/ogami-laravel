<?php

declare(strict_types=1);

namespace App\Notifications\ISO;

use App\Domains\ISO\Models\ControlledDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class DocumentReviewDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ControlledDocument $document,
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
            'type' => 'iso.document_review_due',
            'title' => 'Document Review Due',
            'message' => sprintf(
                'Controlled document "%s" (Rev. %s) is due for periodic review.',
                $this->document->title,
                $this->document->current_version ?? '—',
            ),
            'action_url' => "/iso/documents/{$this->document->ulid}",
            'document_id' => $this->document->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
