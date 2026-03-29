<?php

declare(strict_types=1);

namespace App\Notifications\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to warehouse/procurement staff when QC inspection fails on a Goods Receipt.
 * Uses ::fromModel() to avoid serializing Eloquent models in queued jobs.
 */
final class GrQcFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $grId,
        private readonly string $grReference,
        private readonly string $poReference,
        private readonly string $vendorName,
        private readonly ?string $qcNotes,
        private readonly int $failedItemCount,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(GoodsReceipt $gr): self
    {
        $gr->loadMissing(['purchaseOrder.vendor', 'items']);

        $failedItems = $gr->items->filter(fn ($i) => $i->qc_status === 'failed')->count();

        return new self(
            grId: $gr->id,
            grReference: $gr->gr_reference,
            poReference: $gr->purchaseOrder?->po_reference ?? 'N/A',
            vendorName: $gr->purchaseOrder?->vendor?->name ?? 'Unknown vendor',
            qcNotes: $gr->qc_notes,
            failedItemCount: $failedItems,
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
        $message = sprintf(
            'QC inspection FAILED for GR %s (PO %s, vendor: %s). %d item(s) did not pass inspection.',
            $this->grReference,
            $this->poReference,
            $this->vendorName,
            $this->failedItemCount,
        );

        if ($this->qcNotes) {
            $message .= " Notes: {$this->qcNotes}";
        }

        return [
            'type' => 'procurement.gr_qc_failed',
            'title' => 'GR Quality Control Failed',
            'message' => $message,
            'gr_id' => $this->grId,
            'gr_reference' => $this->grReference,
            'vendor_name' => $this->vendorName,
            'failed_item_count' => $this->failedItemCount,
            'action_url' => "/procurement/goods-receipts/{$this->grId}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
