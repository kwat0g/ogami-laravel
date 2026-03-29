<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\JobRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class RequisitionSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $requisitionId,
        private readonly string $requisitionNumber,
        private readonly string $requesterName,
        private readonly string $positionTitle,
        private readonly string $departmentName,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(JobRequisition $req): self
    {
        return new self(
            requisitionId: $req->id,
            requisitionNumber: $req->requisition_number,
            requesterName: $req->requester?->name ?? 'Someone',
            positionTitle: $req->position?->title ?? 'a position',
            departmentName: $req->department?->name ?? 'a department',
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
            'type' => 'recruitment.requisition.submitted',
            'title' => 'New Requisition for Approval',
            'message' => sprintf(
                '%s submitted requisition %s for %s (%s).',
                $this->requesterName,
                $this->requisitionNumber,
                $this->positionTitle,
                $this->departmentName,
            ),
            'requisition_id' => $this->requisitionId,
            'requisition_number' => $this->requisitionNumber,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
