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

    // Store only scalar values — never serialize Eloquent models into queue jobs.
    // Storing models causes ModelNotFoundException when the record is soft-deleted
    // by the time the worker processes the job.
    public function __construct(
        private readonly int $poId,
        private readonly string $poUlid,
        private readonly string $poReference,
        private readonly string $vendorName,
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
            vendorName: $po->vendor?->name ?? '(unknown vendor)',
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
            'type' => 'purchase_order_sent',
            'title' => "Purchase Order Sent — {$this->poReference}",
            'body' => "PO #{$this->poReference} to {$this->vendorName} has been sent. Prepare to receive incoming goods.",
            'po_id' => $this->poId,
            'po_ulid' => $this->poUlid,
            'po_number' => $this->poReference,
            'vendor' => $this->vendorName,
            'total_amount' => $this->totalAmountCentavos,
            'url' => "/procurement/purchase-orders/{$this->poUlid}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
