<?php

declare(strict_types=1);

namespace App\Notifications\CRM;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClientOrderNegotiatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $orderId,
        private readonly string $orderReference,
        private readonly string $customerName,
        private readonly string $reason,
        private readonly array $proposedChanges,
        private readonly ?string $notes,
    ) {}

    public static function fromModel(
        ClientOrder $order,
        string $reason,
        array $proposedChanges,
        ?string $notes
    ): self {
        return new self(
            orderId: $order->id,
            orderReference: $order->order_reference,
            customerName: $order->customer->name,
            reason: $reason,
            proposedChanges: $proposedChanges,
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
            ->subject("Order Update - Action Required: {$this->orderReference}")
            ->greeting("Hello {$this->customerName},")
            ->line("We need to discuss your order {$this->orderReference}.")
            ->line("Reason: {$this->getReasonText()}")
            ->line('');

        // Show proposed changes
        if (!empty($this->proposedChanges['delivery_date'])) {
            $date = $this->proposedChanges['delivery_date'];
            $mail->line("📅 Proposed Delivery Date: {$date}");
        }

        if (!empty($this->proposedChanges['items'])) {
            $mail->line('📦 Item Changes:');
            foreach ($this->proposedChanges['items'] as $item) {
                $line = "- Item {$item['item_id']}: ";
                if (isset($item['quantity'])) {
                    $line .= "Qty {$item['quantity']} ";
                }
                if (isset($item['price'])) {
                    $line .= "@ ₱{$item['price']}";
                }
                $mail->line($line);
            }
        }

        if ($this->notes) {
            $mail->line('')
                ->line('Additional Information:')
                ->line($this->notes);
        }

        $mail->line('')
            ->action('Respond to Proposal', url("/client-portal/orders/{$this->orderId}"))
            ->line('Please accept, counter-propose, or cancel the order.');

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'client_order_negotiated',
            'order_id' => $this->orderId,
            'order_reference' => $this->orderReference,
            'reason' => $this->reason,
            'message' => "Your order {$this->orderReference} needs review: {$this->getReasonText()}",
            'url' => "/client-portal/orders/{$this->orderId}",
        ];
    }

    private function getReasonText(): string
    {
        $reasons = ClientOrder::getNegotiationReasons();
        return $reasons[$this->reason] ?? $this->reason;
    }
}
