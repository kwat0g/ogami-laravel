<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Inventory\Services\StockService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-Issue MRQ for Single-Location Warehouses — Automation A5.
 *
 * When an MRQ is VP-approved and all items are in a single warehouse location,
 * auto-fulfill the requisition by issuing stock directly.
 *
 * For multi-location warehouses, generates a pick list instead.
 *
 * Configurable via system_setting 'automation.mrq_approved.auto_issue_single_location'.
 * Designed to run periodically or triggered after approval.
 */
final class AutoIssueMrqCommand extends Command
{
    protected $signature = 'inventory:auto-issue-mrq';

    protected $description = 'Auto-issue approved MRQs for single-location warehouses';

    public function handle(StockService $stockService): int
    {
        $enabled = (bool) (DB::table('system_settings')
            ->where('key', 'automation.mrq_approved.auto_issue_single_location')
            ->value('value') ?? false);

        if (! $enabled) {
            $this->info('Auto-issue MRQ is disabled via system_settings.');

            return self::SUCCESS;
        }

        // Find MRQs that are approved but not yet fulfilled
        $mrqs = MaterialRequisition::where('status', 'approved')
            ->with('items.itemMaster')
            ->get();

        if ($mrqs->isEmpty()) {
            $this->info('No approved MRQs pending fulfillment.');

            return self::SUCCESS;
        }

        // Check if warehouse is single-location
        $locationCount = WarehouseLocation::count();
        $isSingleLocation = $locationCount <= 1;

        if (! $isSingleLocation) {
            $this->info("Multi-location warehouse ({$locationCount} locations). Auto-issue skipped — use pick lists.");

            return self::SUCCESS;
        }

        $defaultLocation = WarehouseLocation::first();
        if ($defaultLocation === null) {
            $this->warn('No warehouse locations configured.');

            return self::FAILURE;
        }

        $systemUser = User::first();
        $fulfilled = 0;
        $skipped = 0;

        foreach ($mrqs as $mrq) {
            // Check if all items have sufficient stock
            $canFulfill = true;

            foreach ($mrq->items as $item) {
                $qtyNeeded = (float) ($item->quantity_requested ?? 0);
                $available = (float) StockBalance::where('item_id', $item->item_master_id)
                    ->where('location_id', $defaultLocation->id)
                    ->sum('quantity_on_hand');

                if ($available < $qtyNeeded) {
                    $canFulfill = false;

                    break;
                }
            }

            if (! $canFulfill) {
                $skipped++;

                continue;
            }

            // Issue all items
            try {
                DB::transaction(function () use ($mrq, $stockService, $defaultLocation, $systemUser): void {
                    foreach ($mrq->items as $item) {
                        $qty = (float) ($item->quantity_requested ?? 0);

                        $stockService->issue(
                            itemId: $item->item_master_id,
                            locationId: $defaultLocation->id,
                            quantity: $qty,
                            referenceType: 'material_requisitions',
                            referenceId: $mrq->id,
                            actor: $systemUser,
                            remarks: "Auto-issued from MRQ #{$mrq->mr_reference}",
                        );

                        $item->update(['quantity_issued' => $qty]);
                    }

                    $mrq->update([
                        'status' => 'fulfilled',
                        'fulfilled_at' => now(),
                    ]);
                });

                $fulfilled++;
            } catch (\Throwable $e) {
                Log::warning('[AutoIssueMRQ] Failed to auto-issue', [
                    'mrq_id' => $mrq->id,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $this->info("Auto-issue complete: {$fulfilled} MRQs fulfilled, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
