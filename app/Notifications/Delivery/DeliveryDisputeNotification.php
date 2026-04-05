<?php

declare(strict_types=1);

namespace App\Notifications\Delivery;

use App\Domains\Production\Models\CombinedDeliverySchedule;
use App\Domains\Production\Models\DeliverySchedule;
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
        private readonly string $scheduleReference,
        private readonly string $scheduleType,
        private readonly string $orderReference,
        private readonly int $disputeItemCount,
    ) {}

    public static function fromModel(CombinedDeliverySchedule|DeliverySchedule $schedule): self
    {
        $isCombined = $schedule instanceof CombinedDeliverySchedule;

        $reference = $isCombined
            ? $schedule->cds_reference
            : $schedule->ds_reference;

        $orderReference = $schedule->clientOrder?->order_reference ?? 'N/A';

        return new self(
            scheduleId: $schedule->id,
            scheduleUlid: $schedule->ulid,
            scheduleReference: $reference,
            scheduleType: $isCombined ? 'combined' : 'single',
            orderReference: $orderReference,
            disputeItemCount: count($schedule->dispute_summary ?? []),
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reviewUrl = $this->scheduleType === 'combined'
            ? url('/production/combined-delivery-schedules/'.$this->scheduleUlid)
            : url('/production/delivery-schedules/'.$this->scheduleUlid);

        $referenceLabel = $this->scheduleType === 'combined' ? 'CDS Reference' : 'DS Reference';

        return (new MailMessage)
            ->subject('Delivery Dispute Raised — '.$this->scheduleReference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A client has reported issues with the following delivery:')
            ->line($referenceLabel.': '.$this->scheduleReference)
            ->line('Order Reference: '.$this->orderReference)
            ->line('Disputed Items: '.$this->disputeItemCount)
            ->action('Review Dispute', $reviewUrl)
            ->line('Please review and process a credit note or replacement as needed.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delivery Dispute Raised',
            'message' => 'Client reported issues with delivery '.$this->scheduleReference,
            'delivery_reference' => $this->scheduleReference,
            'cds_reference' => $this->scheduleType === 'combined' ? $this->scheduleReference : null,
            'schedule_type' => $this->scheduleType,
            'combined_delivery_schedule_id' => $this->scheduleType === 'combined' ? $this->scheduleId : null,
            'delivery_schedule_id' => $this->scheduleType === 'single' ? $this->scheduleId : null,
            'ulid' => $this->scheduleUlid,
            'dispute_item_count' => $this->disputeItemCount,
            'type' => 'delivery_dispute',
        ];
    }
}
