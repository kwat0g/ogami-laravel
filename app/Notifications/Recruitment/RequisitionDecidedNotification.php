<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\JobRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class RequisitionDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $requisitionId,
        private readonly string $requisitionNumber,
        private readonly string $decision,
        private readonly string $decidedBy,
        private readonly ?string $reason,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(JobRequisition $req, string $decision, string $decidedBy, ?string $reason = null): self
    {
        return new self(
            requisitionId: $req->id,
            requisitionNumber: $req->requisition_number,
            decision: $decision,
            decidedBy: $decidedBy,
            reason: $reason,
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
        $action = $this->decision === 'approved' ? 'approved' : 'rejected';

        return [
            'type' => "recruitment.requisition.{$action}",
            'title' => sprintf('Requisition %s', ucfirst($action)),
            'message' => sprintf(
                'Requisition %s has been %s by %s.%s',
                $this->requisitionNumber,
                $action,
                $this->decidedBy,
                $this->reason ? " Reason: {$this->reason}" : '',
            ),
            'requisition_id' => $this->requisitionId,
            'requisition_number' => $this->requisitionNumber,
            'decision' => $this->decision,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
