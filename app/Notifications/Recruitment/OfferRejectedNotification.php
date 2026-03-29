<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\JobOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class OfferRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $offerId,
        private readonly string $offerNumber,
        private readonly string $candidateName,
        private readonly string $positionTitle,
        private readonly ?string $reason,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(JobOffer $offer): self
    {
        return new self(
            offerId: $offer->id,
            offerNumber: $offer->offer_number,
            candidateName: $offer->application?->candidate?->full_name ?? 'A candidate',
            positionTitle: $offer->offeredPosition?->title ?? 'a position',
            reason: $offer->rejection_reason,
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $msg = sprintf('%s has rejected the offer (%s) for %s.', $this->candidateName, $this->offerNumber, $this->positionTitle);
        if ($this->reason) {
            $msg .= " Reason: {$this->reason}";
        }

        return [
            'type' => 'recruitment.offer.rejected',
            'title' => 'Offer Rejected',
            'message' => $msg,
            'offer_id' => $this->offerId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
