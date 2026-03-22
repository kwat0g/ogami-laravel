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
        private readonly int $ncrId,
        private readonly string $ncrUlid,
        private readonly string $ncrNumber,
        private readonly string $defectCategory,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(NonConformanceReport $ncr): self
    {
        return new self(
            ncrId: $ncr->id,
            ncrUlid: $ncr->ulid,
            ncrNumber: $ncr->ncr_number ?? "NCR-{$ncr->id}",
            defectCategory: $ncr->defect_category ?? 'Unspecified',
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
            'type' => 'qc.ncr_created',
            'title' => 'New Non-Conformance Report',
            'message' => sprintf(
                'NCR %s has been raised. Category: %s. Immediate review required.',
                $this->ncrNumber,
                $this->defectCategory,
            ),
            'action_url' => "/qc/ncrs/{$this->ncrUlid}",
            'ncr_id' => $this->ncrId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
