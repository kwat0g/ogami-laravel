<?php

declare(strict_types=1);

namespace App\Listeners\QC;

use App\Domains\QC\Models\NonConformanceReport;
use App\Events\QC\InspectionFailed;
use Illuminate\Support\Facades\Log;

/**
 * Auto-create a Non-Conformance Report when a QC inspection fails.
 *
 * Prevents NCRs from being forgotten when QC staff only record the
 * inspection results but forget to manually create the NCR.
 *
 * Auto-discovered by Laravel (in app/Listeners/).
 */
class AutoCreateNcrOnInspectionFailure
{
    public function handle(InspectionFailed $event): void
    {
        $inspection = $event->inspection;

        // Guard: don't create duplicate NCRs for the same inspection
        $exists = NonConformanceReport::where('inspection_id', $inspection->id)->exists();
        if ($exists) {
            return;
        }

        try {
            NonConformanceReport::create([
                'inspection_id' => $inspection->id,
                'production_order_id' => $inspection->production_order_id,
                'item_id' => $inspection->item_master_id ?? $inspection->item_id ?? null,
                'ncr_type' => $inspection->stage === 'iqc' ? 'supplier' : 'internal',
                'severity' => 'major',
                'description' => "Auto-generated NCR from failed {$inspection->stage} inspection #{$inspection->id}. "
                    . "Qty inspected: {$inspection->qty_inspected}, Qty failed: {$inspection->qty_failed}. "
                    . ($inspection->remarks ? "Inspector remarks: {$inspection->remarks}" : ''),
                'status' => 'open',
                'reported_by_id' => $inspection->inspector_id ?? $inspection->created_by_id,
                'reported_at' => now(),
            ]);

            Log::info('[QC] Auto-created NCR for failed inspection', [
                'inspection_id' => $inspection->id,
                'stage' => $inspection->stage,
            ]);
        } catch (\Throwable $e) {
            // Non-fatal: NCR auto-creation failure should not break the inspection flow
            Log::error('[QC] Auto NCR creation failed', [
                'inspection_id' => $inspection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
