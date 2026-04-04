<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\Services\OfferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ExpireJobOffersCommand extends Command
{
    protected $signature = 'recruitment:expire-offers';

    protected $description = 'Transition sent job offers past their expiry date to expired status';

    public function __construct(private readonly OfferService $offerService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = JobOffer::expirable()->get();

        $count = 0;
        foreach ($expired as $offer) {
            try {
                $this->offerService->expireOffer($offer);
                Log::info('Expired job offer', ['offer_id' => $offer->id, 'offer_number' => $offer->offer_number]);
                $count++;
            } catch (\Throwable $e) {
                Log::error('Failed to expire offer', [
                    'offer_id' => $offer->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to expire offer {$offer->offer_number}: {$e->getMessage()}");
            }
        }

        $this->info("Expired {$count} job offer(s).");

        return self::SUCCESS;
    }
}
