<?php

declare(strict_types=1);

namespace App\Listeners\Production;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\ProductionOrderService;
use App\Events\QC\InspectionPassed;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Resumes a Production Work Order that was placed on hold after a QC failure,
 * once a subsequent inspection passes and clears the hold.
 *
 * QC-002: Passed in-process inspection unblocks further production.
 */
final class ResumeProductionOrderOnInspectionPass implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly ProductionOrderService $service) {}

    public function handle(InspectionPassed $event): void
    {
        $inspection = $event->inspection;

        if ($inspection->production_order_id === null) {
            return;
        }

        $order = ProductionOrder::find($inspection->production_order_id);

        if ($order === null) {
            return;
        }

        // Only resume orders that are currently on hold
        if ($order->status !== 'on_hold') {
            return;
        }

        $this->service->resume($order);
    }
}
