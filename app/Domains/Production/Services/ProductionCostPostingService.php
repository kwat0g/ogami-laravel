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

            // Find accounts (use generic names - real implementation would use config)
            $wipAccount = ChartOfAccount::where('name', 'like', '%Work in Process%')
                ->orWhere('name', 'like', '%WIP%')
                ->first();
            $varianceAccount = ChartOfAccount::where('name', 'like', '%Cost Variance%')
                ->orWhere('name', 'like', '%Manufacturing Variance%')
                ->first();
            $inventoryAccount = ChartOfAccount::where('name', 'like', '%Raw Material%')
                ->orWhere('account_type', 'asset')
                ->where('name', 'like', '%Inventor%')
                ->first();

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

            $totalActualCost = $actual['total_cost_centavos'];

            // Debit Finished Goods / WIP for actual cost
            if ($wipAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $wipAccount->id,
                    'debit_centavos' => $totalActualCost,
                    'credit_centavos' => 0,
                    'description' => "Production output: Order #{$order->id}",
                ]);
            }

            // Credit Raw Material Inventory for material consumed
            if ($inventoryAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $inventoryAccount->id,
                    'debit_centavos' => 0,
                    'credit_centavos' => $actual['material_cost_centavos'],
                    'description' => "Material consumed: Order #{$order->id}",
                ]);
            }

            // Post labor cost
            $laborCost = $actual['labor_cost_centavos'];
            if ($laborCost > 0 && $wipAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $wipAccount->id,
                    'debit_centavos' => 0,
                    'credit_centavos' => $laborCost,
                    'description' => "Labor cost applied: Order #{$order->id}",
                ]);
            }

            // Post variance if any
            $varianceAmount = $variance['variance_centavos'];
            if ($varianceAmount !== 0 && $varianceAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $varianceAccount->id,
                    'debit_centavos' => $varianceAmount > 0 ? 0 : abs($varianceAmount),
                    'credit_centavos' => $varianceAmount > 0 ? $varianceAmount : 0,
                    'description' => ($variance['favorable'] ? 'Favorable' : 'Unfavorable') . " variance: Order #{$order->id}",
                ]);
            }

            return [
                'journal_entry_id' => $je->id,
                'variance' => $variance,
                'actual_cost' => $actual,
            ];
        });
    }
}
