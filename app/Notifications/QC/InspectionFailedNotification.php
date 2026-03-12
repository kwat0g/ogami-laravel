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
        private readonly Inspection $inspection,
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
            'type' => 'qc.inspection_failed',
            'title' => 'QC Inspection Failed',
            'message' => sprintf(
                'Inspection %s has FAILED. An NCR may be required.',
                $this->inspection->inspection_number ?? "INS-{$this->inspection->id}",
            ),
            'action_url' => "/qc/inspections/{$this->inspection->ulid}",
            'inspection_id' => $this->inspection->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
