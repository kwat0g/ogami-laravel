<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Production Cost Auto-Posting Service.
 *
 * On production order completion, automatically posts a journal entry
 * for the cost variance between standard and actual cost.
 */
final class ProductionCostPostingService implements ServiceContract
{
    public function __construct(private readonly CostingService $costingService) {}

    /**
     * Post production cost variance to GL.
     *
     * Debits WIP/COGS account, credits raw material inventory account,
     * and posts variance to a cost variance account.
     *
     * @return array{journal_entry_id: int, variance: array}
     */
    public function postCostVariance(ProductionOrder $order, User $actor): array
    {
        if ($order->status !== 'completed') {
            throw new DomainException(
                'Production order must be completed to post cost variance.',
                'PROD_ORDER_NOT_COMPLETED',
                422
            );
        }

        $variance = $this->costingService->costVariance($order);
        $actual = $this->costingService->actualCost($order);

        if ($actual['total_cost_centavos'] === 0) {
            throw new DomainException(
                'No actual cost computed for this production order.',
                'PROD_NO_ACTUAL_COST',
                422
            );
        }

        return DB::transaction(function () use ($order, $actor, $variance, $actual): array {
            // Find current open fiscal period
            $period = FiscalPeriod::where('status', 'open')
                ->orderByDesc('end_date')
                ->first();

            if ($period === null) {
                throw new DomainException(
                    'No open fiscal period found for posting.',
                    'ACCT_NO_OPEN_PERIOD',
                    422
                );
            }

            // Find accounts by code (reliable) with fallback to name-based search
            $wipAccount = ChartOfAccount::where('code', '1400')->first()
                ?? ChartOfAccount::where('name', 'like', '%Work in Process%')->orWhere('name', 'like', '%WIP%')->first();
            $varianceAccount = ChartOfAccount::where('code', '5900')->first()
                ?? ChartOfAccount::where('name', 'like', '%Cost Variance%')->orWhere('name', 'like', '%Manufacturing Variance%')->first();
            $inventoryAccount = ChartOfAccount::where('code', '1300')->first()
                ?? ChartOfAccount::where('name', 'like', '%Raw Material%')->where('name', 'like', '%Inventor%')->first();

            if (! $wipAccount || ! $inventoryAccount) {
                throw new DomainException(
                    'Missing GL accounts for production cost posting. Ensure WIP (1400) and Raw Material Inventory (1300) accounts exist.',
                    'PROD_MISSING_GL_ACCOUNTS',
                    422,
                );
            }

            // Create the journal entry
            $je = JournalEntry::create([
                'fiscal_period_id' => $period->id,
                'entry_date' => now()->toDateString(),
                'reference_number' => "PROD-{$order->id}",
                'description' => "Production cost posting for order #{$order->id}",
                'source_type' => 'production_order',
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'created_by_id' => $actor->id,
            ]);

            // ── Balanced JE Structure ──────────────────────────────────────
            // Debit: Finished Goods / WIP   = material + labor + overhead (total actual)
            // Credit: Raw Material Inventory = material consumed
            // Credit: Accrued Labor          = labor cost (or WIP if no labor account)
            // Credit: Manufacturing Overhead = overhead cost (or WIP if no overhead account)
            // Variance line: debit or credit to balance standard vs actual
            //
            // The key invariant: total debits MUST equal total credits.

            $materialCost = $actual['material_cost_centavos'];
            $laborCost = $actual['labor_cost_centavos'] ?? 0;
            $overheadCost = ($actual['total_cost_centavos'] ?? 0) - $materialCost - $laborCost;
            $totalActualCost = $materialCost + $laborCost + max(0, $overheadCost);

            $totalDebits = 0;
            $totalCredits = 0;

            // Debit Finished Goods / WIP for total actual cost
            if ($totalActualCost > 0 && $wipAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $wipAccount->id,
                    'debit' => $totalActualCost / 100,
                    'credit' => null,
                    'description' => "Production output: Order #{$order->id}",
                ]);
                $totalDebits += $totalActualCost;
            }

            // Credit Raw Material Inventory for material consumed
            if ($materialCost > 0 && $inventoryAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $inventoryAccount->id,
                    'debit' => null,
                    'credit' => $materialCost / 100,
                    'description' => "Material consumed: Order #{$order->id}",
                ]);
                $totalCredits += $materialCost;
            }

            // Credit labor and overhead to the inventory account (simplified:
            // real implementations would use separate labor/overhead accounts)
            $conversionCost = $laborCost + max(0, $overheadCost);
            if ($conversionCost > 0 && $inventoryAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $inventoryAccount->id,
                    'debit' => null,
                    'credit' => $conversionCost / 100,
                    'description' => "Conversion cost (labor + overhead): Order #{$order->id}",
                ]);
                $totalCredits += $conversionCost;
            }

            // Post variance if any
            $varianceAmount = $variance['variance_centavos'];
            if ($varianceAmount !== 0 && $varianceAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $varianceAccount->id,
                    'debit' => $varianceAmount < 0 ? abs($varianceAmount) / 100 : null,
                    'credit' => $varianceAmount > 0 ? $varianceAmount / 100 : null,
                    'description' => ($variance['favorable'] ? 'Favorable' : 'Unfavorable') . " variance: Order #{$order->id}",
                ]);

                if ($varianceAmount < 0) {
                    $totalDebits += abs($varianceAmount);
                } else {
                    $totalCredits += $varianceAmount;
                }
            }

            // ── Balance validation ───────────────────────────────────────
            $imbalance = abs($totalDebits - $totalCredits);
            if ($imbalance > 1) { // Allow 1 centavo rounding tolerance
                throw new \App\Shared\Exceptions\UnbalancedJournalEntryException(
                    $totalDebits / 100,
                    $totalCredits / 100,
                );
            }

            return [
                'journal_entry_id' => $je->id,
                'variance' => $variance,
                'actual_cost' => $actual,
                'je_totals' => [
                    'total_debits_centavos' => $totalDebits,
                    'total_credits_centavos' => $totalCredits,
                ],
            ];
        });
    }
}
