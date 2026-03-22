<?php

declare(strict_types=1);

namespace App\Notifications\Production;

use App\Domains\Production\Models\ProductionOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class ProductionOrderCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $productionOrderId,
        private readonly string $productionOrderUlid,
        private readonly string $orderNumber,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(ProductionOrder $order): self
    {
        return new self(
            productionOrderId: $order->id,
            productionOrderUlid: $order->ulid,
            orderNumber: $order->order_number ?? "PO-{$order->id}",
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
            'type' => 'production.order_completed',
            'title' => 'Production Order Completed',
            'message' => sprintf(
                'Production order %s has been completed. Output has been logged.',
                $this->orderNumber,
            ),
            'action_url' => "/production/orders/{$this->productionOrderUlid}",
            'production_order_id' => $this->productionOrderId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
