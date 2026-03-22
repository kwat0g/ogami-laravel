<?php

declare(strict_types=1);

namespace App\Notifications\CRM;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClientOrderApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $orderId,
        private readonly string $orderReference,
        private readonly string $customerName,
        private readonly ?string $agreedDeliveryDate,
        private readonly string $itemsList,
    ) {}

    public static function fromModel(ClientOrder $order): self
    {
        $lines = [];
        foreach ($order->items as $item) {
            $lines[] = "- {$item->item_description} (Qty: {$item->quantity})";
        }

        return new self(
            orderId: $order->id,
            orderReference: $order->order_reference,
            customerName: $order->customer->name,
            agreedDeliveryDate: $order->agreed_delivery_date?->format('F j, Y'),
            itemsList: implode("\n", $lines),
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deliveryDate = $this->agreedDeliveryDate ?? 'To be confirmed';

        return (new MailMessage)
            ->subject("Your Order Has Been Approved: {$this->orderReference}")
            ->greeting("Hello {$this->customerName},")
            ->line("Your order {$this->orderReference} has been approved.")
            ->line('Order Details:')
            ->line($this->itemsList)
            ->line("Expected Delivery: {$deliveryDate}")
            ->action('View Order', url("/client-portal/orders/{$this->orderId}"))
            ->line('Thank you for your business!');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'client_order_approved',
            'order_id' => $this->orderId,
            'order_reference' => $this->orderReference,
            'message' => "Your order {$this->orderReference} has been approved",
            'url' => "/client-portal/orders/{$this->orderId}",
        ];
    }
}
