<?php

declare(strict_types=1);

namespace App\Notifications\CRM;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClientOrderSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $orderId,
        private readonly string $orderReference,
        private readonly string $customerName,
        private readonly string $formattedTotal,
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
            formattedTotal: $order->getFormattedTotal(),
            itemsList: implode("\n", $lines),
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Client Order: {$this->orderReference}")
            ->greeting('Hello Sales Team,')
            ->line("A new order has been submitted by {$this->customerName}.")
            ->line("Order Reference: {$this->orderReference}")
            ->line("Total Amount: {$this->formattedTotal}")
            ->line('Items:')
            ->line($this->itemsList)
            ->action('Review Order', url("/sales/client-orders/{$this->orderId}"))
            ->line('Please review and approve or negotiate as needed.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'client_order_submitted',
            'order_id' => $this->orderId,
            'order_reference' => $this->orderReference,
            'customer_name' => $this->customerName,
            'total_amount' => $this->formattedTotal,
            'message' => "New order {$this->orderReference} from {$this->customerName}",
            'url' => "/sales/client-orders/{$this->orderId}",
        ];
    }
}
