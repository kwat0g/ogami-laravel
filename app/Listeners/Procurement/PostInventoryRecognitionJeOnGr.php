<?php

declare(strict_types=1);

namespace App\Listeners\Procurement;

use App\Domains\Accounting\Models\AccountMapping;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\JournalEntryService;
use App\Events\Procurement\ThreeWayMatchPassed;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-015: Post inventory recognition journal entry on GR confirmation.
 *
 * When a Goods Receipt passes three-way match:
 *   Dr Inventory (raw materials)     — value of goods received
 *   Cr GR/IR Clearing (AP accrual)   — pending vendor invoice matching
 *
 * This ensures inventory is recognized on the balance sheet at the point
 * of receipt, not when the vendor invoice is received.
 *
 * Idempotency: checks source_type='goods_receipt' + source_id to prevent
 * duplicate postings.
 */
class PostInventoryRecognitionJeOnGr
{
    public function __construct(
        private readonly JournalEntryService $jeService,
    ) {}

    public function handle(ThreeWayMatchPassed $event): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->process($event));

            return;
        }

        $this->process($event);
    }

    private function process(ThreeWayMatchPassed $event): void
    {
        $gr = $event->goodsReceipt;
        $gr->loadMissing(['items.poItem', 'purchaseOrder']);

        // Idempotency guard
        $existing = JournalEntry::where('source_type', 'goods_receipt')
            ->where('source_id', $gr->id)
            ->exists();

        if ($existing) {
            return;
        }

        // Calculate total GR value from PO item agreed costs
        $totalValuePesos = 0.0;
        foreach ($gr->items as $grItem) {
            $poItem = $grItem->poItem;
            if ($poItem === null) {
                continue;
            }

            $unitCostPesos = (float) ($poItem->agreed_unit_cost ?? 0);
            // Use QC-accepted quantity for partial acceptance scenarios;
            // falls back to quantity_received when no QC split occurred.
            $qty = $grItem->effectiveAcceptedQuantity();
            $totalValuePesos += $unitCostPesos * $qty;
        }

        if ($totalValuePesos <= 0) {
            Log::info('[GR-JE] Skipping JE for GR with zero value', ['gr_id' => $gr->id]);

            return;
        }

        try {
            // Resolve GL accounts from account_mapping table
            $inventoryAccountId = AccountMapping::resolve('procurement', 'GR_POST', 'debit');
            $grClearingAccountId = AccountMapping::resolve('procurement', 'GR_POST', 'credit');

            $date = (string) $gr->received_date;
            $fiscalPeriod = FiscalPeriod::where('date_from', '<=', $date)
                ->where('date_to', '>=', $date)
                ->where('status', 'open')
                ->first();

            if (! $fiscalPeriod) {
                Log::warning('[GR-JE] No open fiscal period for GR date', [
                    'gr_id' => $gr->id,
                    'date' => $date,
                ]);

                return;
            }

            $je = $this->jeService->create([
                'date' => $date,
                'description' => "Inventory recognition — GR {$gr->gr_reference}",
                'source_type' => 'goods_receipt',
                'source_id' => $gr->id,
                'lines' => [
                    [
                        'account_id' => $inventoryAccountId,
                        'debit' => round($totalValuePesos, 4),
                        'credit' => null,
                        'description' => "Raw materials received — GR {$gr->gr_reference}",
                    ],
                    [
                        'account_id' => $grClearingAccountId,
                        'debit' => null,
                        'credit' => round($totalValuePesos, 4),
                        'description' => "GR/IR clearing — GR {$gr->gr_reference}",
                    ],
                ],
            ]);

            // Auto-post the JE
            $this->jeService->post($je);

            Log::info('[GR-JE] Inventory recognition JE posted', [
                'gr_id' => $gr->id,
                'je_id' => $je->id,
                'amount_pesos' => $totalValuePesos,
            ]);
        } catch (\Throwable $e) {
            // Don't fail the GR flow — log and continue
            Log::error('[GR-JE] Failed to post inventory recognition JE', [
                'gr_id' => $gr->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
