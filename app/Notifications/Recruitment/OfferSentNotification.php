<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\JobOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class OfferSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $offerId,
        private readonly string $offerNumber,
        private readonly string $candidateName,
        private readonly string $positionTitle,
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
        return [
            'type' => 'recruitment.offer.sent',
            'title' => 'Job Offer Sent',
            'message' => sprintf(
                'Offer %s sent to %s for %s.',
                $this->offerNumber,
                $this->candidateName,
                $this->positionTitle,
            ),
            'offer_id' => $this->offerId,
            'offer_number' => $this->offerNumber,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
