<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Goods Receipt passes three-way match validation.
 *
 * Listeners:
 *   - CreateApInvoiceDraftListener — auto-creates a vendor invoice draft for the
 *     Accounting Officer to review, complete, and submit for approval.
 */
final class ThreeWayMatchPassed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly GoodsReceipt $goodsReceipt,
    ) {}
}
