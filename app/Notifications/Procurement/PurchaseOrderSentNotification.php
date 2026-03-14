<?php

declare(strict_types=1);

namespace App\Notifications\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies warehouse staff (users with goods_receipts.create permission) when a
 * Purchase Order has been sent to a vendor so they can prepare to receive goods.
 *
 * PROC-WH-001: Sent PO triggers receiving preparation in the warehouse.
 */
final class PurchaseOrderSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PurchaseOrder $purchaseOrder,
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
        $vendor = $this->purchaseOrder->vendor?->name ?? '(unknown vendor)';

        return [
            'type'           => 'purchase_order_sent',
            'title'          => "Purchase Order Sent — {$this->purchaseOrder->po_reference}",
            'body'           => "PO #{$this->purchaseOrder->po_reference} to {$vendor} has been sent. Prepare to receive incoming goods.",
            'po_id'          => $this->purchaseOrder->id,
            'po_ulid'        => $this->purchaseOrder->ulid,
            'po_number'      => $this->purchaseOrder->po_reference,
            'vendor'         => $vendor,
            'total_amount'   => $this->purchaseOrder->total_po_amount,
            'url'            => "/procurement/purchase-orders/{$this->purchaseOrder->ulid}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
