<?php

declare(strict_types=1);

namespace App\Notifications\QC;

use App\Domains\QC\Models\Inspection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class InspectionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $inspectionId,
        private readonly string $inspectionUlid,
        private readonly string $inspectionNumber,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Inspection $inspection): self
    {
        return new self(
            inspectionId: $inspection->id,
            inspectionUlid: $inspection->ulid,
            inspectionNumber: $inspection->inspection_number ?? "INS-{$inspection->id}",
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
            'type' => 'qc.inspection_failed',
            'title' => 'QC Inspection Failed',
            'message' => sprintf(
                'Inspection %s has FAILED. An NCR may be required.',
                $this->inspectionNumber,
            ),
            'action_url' => "/qc/inspections/{$this->inspectionUlid}",
            'inspection_id' => $this->inspectionId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
