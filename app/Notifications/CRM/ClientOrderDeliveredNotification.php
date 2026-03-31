<?php

declare(strict_types=1);

namespace App\Notifications\CRM;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClientOrderDeliveredNotification extends Notification implements ShouldQueue
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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your Order Has Been Delivered: {$this->orderReference}")
            ->greeting("Hello {$this->customerName},")
            ->line("Your order {$this->orderReference} has been delivered.")
            ->line('Please log in to your portal to verify receipt of all items and acknowledge delivery.')
            ->action('Verify Delivery', url("/client-portal/orders/{$this->orderId}"))
            ->line('Thank you for your business!');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'client_order_delivered',
            'order_id' => $this->orderId,
            'order_reference' => $this->orderReference,
            'message' => "Your order {$this->orderReference} has been delivered. Please verify receipt.",
            'url' => "/client-portal/orders/{$this->orderId}",
        ];
    }
}
