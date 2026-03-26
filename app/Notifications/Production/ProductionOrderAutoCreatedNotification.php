<?php

declare(strict_types=1);

namespace App\Notifications\Production;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Production\Models\ProductionOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies the production team when a Production Order is auto-created
 * from a Client Order approval, so they know they have new work.
 */
final class ProductionOrderAutoCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProductionOrder $productionOrder,
        private readonly ClientOrder $clientOrder,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $po = $this->productionOrder;
        $co = $this->clientOrder;
        $productName = $po->productItem?->name ?? 'Unknown Product';

        return (new MailMessage)
            ->subject("New Production Order Auto-Created — {$po->po_reference}")
            ->greeting('New Production Order')
            ->line("A production order has been automatically created from Client Order **{$co->order_reference}**.")
            ->line("**Product:** {$productName}")
            ->line("**Quantity:** {$po->qty_required}")
            ->line("**Target Start:** {$po->target_start_date?->format('M d, Y')}")
            ->line("**Target End:** {$po->target_end_date?->format('M d, Y')}")
            ->action('View Production Order', url('/production/orders'))
            ->line('Please review and release the production order when ready.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'production_order_auto_created',
            'production_order_id' => $this->productionOrder->id,
            'production_order_ulid' => $this->productionOrder->ulid,
            'production_order_reference' => $this->productionOrder->po_reference,
            'client_order_id' => $this->clientOrder->id,
            'client_order_reference' => $this->clientOrder->order_reference,
            'product_name' => $this->productionOrder->productItem?->name,
            'qty_required' => $this->productionOrder->qty_required,
            'target_start_date' => $this->productionOrder->target_start_date?->toDateString(),
            'target_end_date' => $this->productionOrder->target_end_date?->toDateString(),
            'message' => "Production order {$this->productionOrder->po_reference} auto-created from client order {$this->clientOrder->order_reference}.",
        ];
    }

    public static function fromEvent(ProductionOrder $po, ClientOrder $co): self
    {
        return new self($po, $co);
    }
}
