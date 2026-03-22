<?php

declare(strict_types=1);

namespace App\Notifications\CRM;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClientOrderRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $orderId,
        private readonly string $orderReference,
        private readonly string $customerName,
        private readonly string $reason,
        private readonly ?string $notes,
    ) {}

    public static function fromModel(ClientOrder $order, string $reason, ?string $notes): self
    {
        return new self(
            orderId: $order->id,
            orderReference: $order->order_reference,
            customerName: $order->customer->name,
            reason: $reason,
            notes: $notes,
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Order Update: {$this->orderReference}")
            ->greeting("Hello {$this->customerName},")
            ->line("We regret to inform you that your order {$this->orderReference} could not be processed.")
            ->line("Reason: {$this->getReasonText()}")
            ->action('View Order', url("/client-portal/orders/{$this->orderId}"));

        if ($this->notes) {
            $mail->line('Additional Information:')
                ->line($this->notes);
        }

        $mail->line('Please contact our sales team if you have any questions.');

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'client_order_rejected',
            'order_id' => $this->orderId,
            'order_reference' => $this->orderReference,
            'reason' => $this->reason,
            'message' => "Your order {$this->orderReference} was rejected: {$this->getReasonText()}",
            'url' => "/client-portal/orders/{$this->orderId}",
        ];
    }

    private function getReasonText(): string
    {
        $reasons = [
            'stock_unavailable' => 'Item(s) currently out of stock',
            'price_issue' => 'Pricing discrepancy',
            'invalid_items' => 'Invalid or discontinued items',
            'credit_hold' => 'Account on credit hold',
            'other' => 'Other reason - please contact sales',
        ];

        return $reasons[$this->reason] ?? $this->reason;
    }
}
