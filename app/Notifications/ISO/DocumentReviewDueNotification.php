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
        private readonly int $documentId,
        private readonly string $documentUlid,
        private readonly string $title,
        private readonly ?string $currentVersion,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(ControlledDocument $document): self
    {
        return new self(
            documentId: $document->id,
            documentUlid: $document->ulid,
            title: $document->title,
            currentVersion: $document->current_version ?? null,
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
            'type' => 'iso.document_review_due',
            'title' => 'Document Review Due',
            'message' => sprintf(
                'Controlled document "%s" (Rev. %s) is due for periodic review.',
                $this->title,
                $this->currentVersion ?? '—',
            ),
            'action_url' => "/iso/documents/{$this->documentUlid}",
            'document_id' => $this->documentId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
