<?php

declare(strict_types=1);

namespace App\Notifications\Delivery;

use App\Domains\Production\Models\CombinedDeliverySchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DeliveryDisputeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $scheduleId,
        private readonly string $scheduleUlid,
        private readonly string $cdsReference,
        private readonly string $orderReference,
        private readonly int $disputeItemCount,
    ) {}

    public static function fromModel(CombinedDeliverySchedule $schedule): self
    {
        return new self(
            scheduleId: $schedule->id,
            scheduleUlid: $schedule->ulid,
            cdsReference: $schedule->cds_reference,
            orderReference: $schedule->clientOrder->order_reference,
            disputeItemCount: count($schedule->dispute_summary ?? []),
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Delivery Dispute Raised — '.$this->cdsReference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A client has reported issues with the following delivery:')
            ->line('CDS Reference: '.$this->cdsReference)
            ->line('Order Reference: '.$this->orderReference)
            ->line('Disputed Items: '.$this->disputeItemCount)
            ->action('Review Dispute', url('/production/combined-delivery-schedules/'.$this->scheduleUlid))
            ->line('Please review and process a credit note or replacement as needed.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delivery Dispute Raised',
            'message' => 'Client reported issues with delivery '.$this->cdsReference,
            'cds_reference' => $this->cdsReference,
            'combined_delivery_schedule_id' => $this->scheduleId,
            'ulid' => $this->scheduleUlid,
            'dispute_item_count' => $this->disputeItemCount,
            'type' => 'delivery_dispute',
        ];
    }
}
