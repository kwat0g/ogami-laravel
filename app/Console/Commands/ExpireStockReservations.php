<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Inventory\Services\StockReservationService;
use Illuminate\Console\Command;

/**
 * Expire stock reservations that have passed their expiration date.
 * Should run daily to clean up expired reservations.
 */
final class ExpireStockReservations extends Command
{
    protected $signature = 'inventory:expire-reservations';

    protected $description = 'Expire stock reservations that have passed their expiration date';

    public function __construct(
        private readonly StockReservationService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->service->expireOldReservations();

        if ($count === 0) {
            $this->info('No expired reservations found.');
        } else {
            $this->info("Expired {$count} reservation(s).");
        }

        return self::SUCCESS;
    }
}
