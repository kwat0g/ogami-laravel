<?php

declare(strict_types=1);

namespace App\Events\Production;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Production\Models\ProductionOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Production Order is auto-created from a Client Order approval.
 * Consumed by NotifyProductionTeamOnAutoOrder listener to alert the production team.
 */
final class ProductionOrderAutoCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ProductionOrder $productionOrder,
        public readonly ClientOrder $clientOrder,
    ) {}
}
