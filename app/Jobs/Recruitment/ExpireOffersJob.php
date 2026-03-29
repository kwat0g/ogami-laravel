<?php

declare(strict_types=1);

namespace App\Jobs\Recruitment;

use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\Services\OfferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ExpireOffersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OfferService $offerService): void
    {
        $expiredOffers = JobOffer::expirable()->get();

        foreach ($expiredOffers as $offer) {
            try {
                $offerService->expireOffer($offer);
                Log::info('Expired job offer', ['offer_id' => $offer->id, 'offer_number' => $offer->offer_number]);
            } catch (\Throwable $e) {
                Log::error('Failed to expire offer', [
                    'offer_id' => $offer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
