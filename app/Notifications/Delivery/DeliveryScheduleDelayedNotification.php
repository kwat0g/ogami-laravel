<?php

declare(strict_types=1);

namespace App\Notifications\Delivery;

use App\Domains\Production\Models\CombinedDeliverySchedule;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DeliveryScheduleDelayedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $scheduleId,
        private readonly string $scheduleUlid,
        private readonly string $cdsReference,
        private readonly string $orderReference,
        private readonly string $clientOrderUlid,
        private readonly array $missingItems,
        private readonly ?string $expectedDeliveryDate,
        private readonly ?string $message,
    ) {}

    public static function fromModel(
        CombinedDeliverySchedule $schedule,
        array $missingItems,
        ?string $expectedDeliveryDate,
        ?string $message
    ): self {
        return new self(
            scheduleId: $schedule->id,
            scheduleUlid: $schedule->ulid,
            cdsReference: $schedule->cds_reference,
            orderReference: $schedule->clientOrder->order_reference,
            clientOrderUlid: $schedule->clientOrder->ulid,
            missingItems: $missingItems,
            expectedDeliveryDate: $expectedDeliveryDate,
            message: $message,
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Delivery Update - Some Items Delayed - '.$this->cdsReference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We wanted to update you on your order status.')
            ->line('Order Reference: '.$this->orderReference)
            ->line('');

        // List missing items
        $mail->line('The following items are currently delayed:');
        foreach ($this->missingItems as $item) {
            $mail->line('• '.($item['product_name'] ?? 'Unknown Item').': '.$item['reason']);
        }

        $mail->line('');

        if ($this->expectedDeliveryDate) {
            $mail->line('Expected delivery for these items: '.Carbon::parse($this->expectedDeliveryDate)->format('F j, Y'));
        }

        if ($this->message) {
            $mail->line('Additional Notes: '.$this->message);
        }

        $mail->line('')
            ->line('The available items will be delivered as scheduled.')
            ->action('View Order Details', url('/client-portal/orders/'.$this->clientOrderUlid))
            ->line('We apologize for any inconvenience.');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delivery Delayed',
            'message' => count($this->missingItems).' items in your order are delayed',
            'cds_reference' => $this->cdsReference,
            'delivery_schedule_id' => $this->scheduleId,
            'ulid' => $this->scheduleUlid,
            'missing_items_count' => count($this->missingItems),
            'expected_delivery' => $this->expectedDeliveryDate,
            'type' => 'delivery_delayed',
        ];
    }
}
