<?php

declare(strict_types=1);

namespace App\Notifications\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies vendor users so they can view the new PO in the Vendor Portal.
 */
final class PurchaseOrderSentToVendorNotification extends Notification implements ShouldQueue
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
        return [
            'type' => 'purchase_order_received',
            'title' => "New Purchase Order — {$this->purchaseOrder->po_reference}",
            'body' => "You have received a new Purchase Order #{$this->purchaseOrder->po_reference} from Ogami Mfg. Please review and fulfill.",
            'po_id' => $this->purchaseOrder->id,
            'po_ulid' => $this->purchaseOrder->ulid,
            'po_number' => $this->purchaseOrder->po_reference,
            'total_amount' => $this->purchaseOrder->total_po_amount,
            'url' => "/vendor-portal/orders/{$this->purchaseOrder->ulid}",
        ];
    }
}
