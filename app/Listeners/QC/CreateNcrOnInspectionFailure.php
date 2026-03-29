<?php

declare(strict_types=1);

namespace App\Listeners\QC;

use App\Domains\QC\Models\NonConformanceReport;
use App\Events\QC\InspectionFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * GAP-QC-3: Auto-create a Non-Conformance Report when an inspection fails.
 *
 * Previously, QC staff had to manually create NCRs after a failed inspection,
 * which could be forgotten. This listener ensures every failed inspection
 * has a corresponding NCR for traceability and corrective action tracking.
 */
final class CreateNcrOnInspectionFailure implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(InspectionFailed $event): void
    {
        $inspection = $event->inspection;

        // Don't create duplicate NCRs if one already exists for this inspection
        $existingNcr = NonConformanceReport::where('inspection_id', $inspection->id)->exists();
        if ($existingNcr) {
            return;
        }

        $itemName = $inspection->itemMaster?->name ?? "Item #{$inspection->item_master_id}";
        $stage = $inspection->stage ?? 'unknown';

        try {
            NonConformanceReport::create([
                'inspection_id' => $inspection->id,
                'title' => "Auto-NCR: {$stage} inspection failed for {$itemName}",
                'description' => "Automatically created from failed inspection #{$inspection->id}. "
                    . "Qty inspected: {$inspection->qty_inspected}, "
                    . "Qty failed: {$inspection->qty_failed}. "
                    . ($inspection->remarks ? "Inspector remarks: {$inspection->remarks}" : 'No remarks.'),
                'severity' => $inspection->qty_failed > ($inspection->qty_inspected * 0.5) ? 'major' : 'minor',
                'status' => 'open',
                'raised_by_id' => $inspection->inspector_id ?? $inspection->created_by_id,
            ]);

            Log::info("QC-NCR: Auto-created NCR for failed inspection #{$inspection->id}");
        } catch (\Throwable $e) {
            // Non-fatal: NCR auto-creation failure should not break the inspection flow
            Log::warning("QC-NCR: Failed to auto-create NCR for inspection #{$inspection->id}: {$e->getMessage()}");
        }
    }
}
