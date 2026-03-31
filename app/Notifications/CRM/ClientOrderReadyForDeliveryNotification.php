<?php

declare(strict_types=1);

namespace App\Notifications\CRM;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClientOrderReadyForDeliveryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $orderId,
        private readonly string $orderReference,
        private readonly string $customerName,
    ) {}

    public static function fromModel(ClientOrder $order): self
    {
        return new self(
            orderId: $order->id,
            orderReference: $order->order_reference,
            customerName: $order->customer->name ?? 'Customer',
        );
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'client_order_ready_for_delivery',
            'order_id' => $this->orderId,
            'order_reference' => $this->orderReference,
            'message' => "Your order {$this->orderReference} is ready for delivery",
            'url' => "/client-portal/orders/{$this->orderId}",
        ];
    }
}
