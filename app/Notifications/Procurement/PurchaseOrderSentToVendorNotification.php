<?php

declare(strict_types=1);

namespace App\Notifications\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies vendor users so they can view the new PO in the Vendor Portal.
 */
final class PurchaseOrderSentToVendorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Store only scalar values — never serialize Eloquent models into queue jobs.
    public function __construct(
        private readonly int $poId,
        private readonly string $poUlid,
        private readonly string $poReference,
        private readonly int $totalAmountCentavos,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(PurchaseOrder $po): self
    {
        return new self(
            poId: $po->id,
            poUlid: $po->ulid,
            poReference: $po->po_reference,
            totalAmountCentavos: (int) $po->total_po_amount,
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
            'type' => 'purchase_order_received',
            'title' => "New Purchase Order — {$this->poReference}",
            'body' => "You have received a new Purchase Order #{$this->poReference} from Ogami Mfg. Please review and fulfill.",
            'po_id' => $this->poId,
            'po_ulid' => $this->poUlid,
            'po_number' => $this->poReference,
            'total_amount' => $this->totalAmountCentavos,
            'url' => "/vendor-portal/orders/{$this->poUlid}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
