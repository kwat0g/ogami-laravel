<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class PreEmploymentCompleteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $checklistId,
        private readonly string $candidateName,
        private readonly string $positionTitle,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(PreEmploymentChecklist $checklist): self
    {
        return new self(
            checklistId: $checklist->id,
            candidateName: $checklist->application?->candidate?->full_name ?? 'A candidate',
            positionTitle: $checklist->application?->posting?->requisition?->position?->title ?? 'a position',
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
            'type' => 'recruitment.preemployment.complete',
            'title' => 'Pre-Employment Complete',
            'message' => sprintf('Pre-employment requirements for %s (%s) are now complete. Ready for hiring.', $this->candidateName, $this->positionTitle),
            'checklist_id' => $this->checklistId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
