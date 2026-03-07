<?php

declare(strict_types=1);

namespace App\Listeners\Production;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\ProductionOrderService;
use App\Events\QC\InspectionFailed;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Places a Production Work Order on hold when a linked QC inspection fails.
 * The WO can be resumed once the associated NCR / CAPA is resolved.
 *
 * QC-001: Failed in-process inspection blocks further production.
 */
final class HoldProductionOrderOnInspectionFail implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly ProductionOrderService $service) {}

    public function handle(InspectionFailed $event): void
    {
        $inspection = $event->inspection;

        if ($inspection->production_order_id === null) {
            return;
        }

        $order = ProductionOrder::find($inspection->production_order_id);

        if ($order === null) {
            return;
        }

        // Only hold orders that are actively in progress or released
        if (! in_array($order->status, ['released', 'in_progress'], true)) {
            return;
        }

        $this->service->hold($order, "QC inspection #{$inspection->id} failed — {$inspection->qty_failed} unit(s) non-conforming.");
    }
}
