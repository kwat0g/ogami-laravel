<?php

declare(strict_types=1);

namespace App\Notifications\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to QC team when a Goods Receipt is submitted for incoming quality control.
 * Uses ::fromModel() to avoid serializing Eloquent models in queued jobs.
 */
final class GrSubmittedForQcNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $grId,
        private readonly string $grReference,
        private readonly string $poReference,
        private readonly string $submittedByName,
        private readonly int $itemCount,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(GoodsReceipt $gr): self
    {
        $gr->loadMissing(['purchaseOrder', 'submittedForQcBy', 'items']);

        return new self(
            grId: $gr->id,
            grReference: $gr->gr_reference,
            poReference: $gr->purchaseOrder?->po_reference ?? 'N/A',
            submittedByName: $gr->submittedForQcBy?->name ?? 'System',
            itemCount: $gr->items->count(),
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
            'type' => 'procurement.gr_submitted_for_qc',
            'title' => 'Goods Receipt Submitted for QC',
            'message' => sprintf(
                '%s submitted GR %s (PO %s) for incoming quality control. %d item(s) require IQC inspection.',
                $this->submittedByName,
                $this->grReference,
                $this->poReference,
                $this->itemCount,
            ),
            'gr_id' => $this->grId,
            'gr_reference' => $this->grReference,
            'action_url' => "/procurement/goods-receipts/{$this->grId}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
