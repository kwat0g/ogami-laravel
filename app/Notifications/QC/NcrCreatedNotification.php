<?php

declare(strict_types=1);

namespace App\Notifications\QC;

use App\Domains\QC\Models\NonConformanceReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class NcrCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NonConformanceReport $ncr,
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
            'type' => 'qc.ncr_created',
            'title' => 'New Non-Conformance Report',
            'message' => sprintf(
                'NCR %s has been raised. Category: %s. Immediate review required.',
                $this->ncr->ncr_number ?? "NCR-{$this->ncr->id}",
                $this->ncr->defect_category ?? 'Unspecified',
            ),
            'action_url' => "/qc/ncrs/{$this->ncr->ulid}",
            'ncr_id' => $this->ncr->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
