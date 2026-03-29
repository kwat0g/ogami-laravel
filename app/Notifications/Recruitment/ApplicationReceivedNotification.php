<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class ApplicationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $applicationId,
        private readonly string $applicationNumber,
        private readonly string $candidateName,
        private readonly string $positionTitle,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Application $app): self
    {
        return new self(
            applicationId: $app->id,
            applicationNumber: $app->application_number,
            candidateName: $app->candidate?->full_name ?? 'A candidate',
            positionTitle: $app->posting?->requisition?->position?->title ?? 'a position',
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
            'type' => 'recruitment.application.received',
            'title' => 'New Application',
            'message' => sprintf('%s applied for %s (%s).', $this->candidateName, $this->positionTitle, $this->applicationNumber),
            'application_id' => $this->applicationId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
