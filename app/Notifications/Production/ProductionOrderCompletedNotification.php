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
        private readonly ProductionOrder $order,
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
            'type' => 'production.order_completed',
            'title' => 'Production Order Completed',
            'message' => sprintf(
                'Production order %s has been completed. Output has been logged.',
                $this->order->order_number ?? "PO-{$this->order->id}",
            ),
            'action_url' => "/production/orders/{$this->order->ulid}",
            'production_order_id' => $this->order->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
