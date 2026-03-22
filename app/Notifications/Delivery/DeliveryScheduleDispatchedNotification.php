<?php

declare(strict_types=1);

namespace App\Notifications\Delivery;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Production\Models\CombinedDeliverySchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DeliveryScheduleDispatchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $scheduleId,
        private readonly string $scheduleUlid,
        private readonly string $cdsReference,
        private readonly string $orderReference,
        private readonly int $readyItems,
        private readonly int $totalItems,
        private readonly ?string $targetDeliveryDate,
    ) {}

    public static function fromModel(
        CombinedDeliverySchedule $schedule,
        DeliveryReceipt $_deliveryReceipt
    ): self {
        $readyItems = collect($schedule->item_status_summary ?? [])
            ->filter(fn ($item) => $item['is_ready'] ?? false)
            ->count();

        return new self(
            scheduleId: $schedule->id,
            scheduleUlid: $schedule->ulid,
            cdsReference: $schedule->cds_reference,
            orderReference: $schedule->clientOrder->order_reference,
            readyItems: $readyItems,
            totalItems: $schedule->total_items,
            targetDeliveryDate: $schedule->target_delivery_date?->format('F j, Y'),
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Order Has Been Dispatched - '.$this->cdsReference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your order has been dispatched and is on its way to you!')
            ->line('Reference: '.$this->cdsReference)
            ->line('Order Reference: '.$this->orderReference)
            ->line('Items: '.$this->readyItems.' of '.$this->totalItems.' ready for delivery')
            ->line('Expected Delivery: '.($this->targetDeliveryDate ?? 'To be confirmed'))
            ->action('Track Delivery', url('/client-portal/deliveries/'.$this->scheduleUlid))
            ->line('Thank you for your business!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order Dispatched',
            'message' => 'Your order '.$this->cdsReference.' has been dispatched',
            'cds_reference' => $this->cdsReference,
            'delivery_schedule_id' => $this->scheduleId,
            'ulid' => $this->scheduleUlid,
            'type' => 'delivery_dispatched',
        ];
    }
}
