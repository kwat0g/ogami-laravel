<?php

declare(strict_types=1);

namespace App\Listeners\Production;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\ProductionOrderService;
use App\Events\QC\InspectionPassed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Auto-close completed Work Orders when OQC passes.
 *
 * This removes the need for a separate manual "Close Order" action while
 * still preserving the close-time gates (OQC passed + no open/failed OQC)
 * and stock receive behavior enforced by ProductionOrderService::close().
 */
final class AutoCloseProductionOrderOnOqcPass implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly ProductionOrderService $service) {}

    public function handle(InspectionPassed $event): void
    {
        $inspection = $event->inspection;

        if ($inspection->stage !== 'oqc' || $inspection->production_order_id === null) {
            return;
        }

        $order = ProductionOrder::find($inspection->production_order_id);
        if ($order === null) {
            return;
        }

        // Ignore already-closed/cancelled orders and avoid invalid transitions.
        if (! in_array($order->status, ['completed'], true)) {
            return;
        }

        try {
            $this->service->close($order);
            Log::info("PROD-OQC: Auto-closed WO {$order->po_reference} after OQC pass (inspection #{$inspection->id}).");
        } catch (\Throwable $e) {
            // Non-fatal: keep process observable without crashing the queue worker.
            Log::warning("PROD-OQC: Failed to auto-close WO {$order->po_reference} after OQC pass: {$e->getMessage()}");
        }
    }
}
