<?php

declare(strict_types=1);

namespace App\Notifications\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to Purchasing Officers when a Goods Receipt results in partial
 * delivery — some PO items have outstanding pending quantities.
 *
 * Helps prevent orders "falling through the cracks" when vendors
 * don't deliver the full quantity.
 */
final class PartialReceiptDiscrepancyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $grId,
        private readonly string $grReference,
        private readonly int $poId,
        private readonly string $poReference,
        private readonly string $vendorName,
        private readonly array $pendingItems,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModels(GoodsReceipt $gr, PurchaseOrder $po, array $pendingItems): self
    {
        return new self(
            grId: $gr->id,
            grReference: $gr->gr_reference,
            poId: $po->id,
            poReference: $po->po_reference,
            vendorName: $po->vendor?->name ?? 'Unknown vendor',
            pendingItems: $pendingItems,
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
        $itemCount = count($this->pendingItems);
        $itemSummary = collect($this->pendingItems)
            ->take(3)
            ->map(fn ($i) => "{$i['description']} ({$i['pending_qty']} pending)")
            ->implode(', ');

        if ($itemCount > 3) {
            $itemSummary .= " + " . ($itemCount - 3) . " more";
        }

        return [
            'type' => 'procurement.partial_receipt',
            'title' => 'Partial Delivery Detected',
            'message' => sprintf(
                'GR %s for PO %s (%s): %d item(s) have outstanding quantities. %s',
                $this->grReference,
                $this->poReference,
                $this->vendorName,
                $itemCount,
                $itemSummary,
            ),
            'gr_id' => $this->grId,
            'po_id' => $this->poId,
            'pending_items' => $this->pendingItems,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
