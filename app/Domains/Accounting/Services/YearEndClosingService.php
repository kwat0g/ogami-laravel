<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Year-End Closing Service — automates the year-end closing process.
 *
 * Zeros out all income and expense accounts by posting a closing journal entry
 * that transfers the net P&L balance to the Retained Earnings account.
 */
final class YearEndClosingService implements ServiceContract
{
    /**
     * Execute year-end closing for a given fiscal year.
     *
     * 1. Validates all periods in the year are closed
     * 2. Computes net income (revenue - expenses)
     * 3. Creates closing JE: debit revenue accounts, credit expense accounts,
     *    net to retained earnings
     *
     * @return array{journal_entry_id: int, net_income: float, revenue_total: float, expense_total: float}
     */
    public function close(int $fiscalYear, User $actor): array
    {
        return DB::transaction(function () use ($fiscalYear, $actor): array {
            // 1. Verify all fiscal periods for the year are closed
            $openPeriods = FiscalPeriod::whereYear('date_from', $fiscalYear)
                ->where('status', 'open')
                ->count();

            if ($openPeriods > 0) {
                throw new DomainException(
                    "Cannot perform year-end closing: {$openPeriods} fiscal period(s) still open for {$fiscalYear}.",
                    'ACCT_PERIODS_NOT_CLOSED',
                    422,
                    ['open_periods' => $openPeriods, 'fiscal_year' => $fiscalYear]
                );
            }

            // 2. Get the last period of the fiscal year for the closing JE
            $lastPeriod = FiscalPeriod::whereYear('date_from', $fiscalYear)
                ->orderByDesc('date_to')
                ->first();

            if ($lastPeriod === null) {
                throw new DomainException(
                    "No fiscal periods found for year {$fiscalYear}.",
                    'ACCT_NO_PERIODS',
                    422,
                    ['fiscal_year' => $fiscalYear]
                );
            }

            // Re-open the last period temporarily for the closing JE
            $lastPeriod->update(['status' => 'open']);

            // 3. Sum all revenue accounts (type = 'revenue' or account_type = '4')
            $revenueAccounts = ChartOfAccount::where('account_type', 'revenue')->get();
            $expenseAccounts = ChartOfAccount::where('account_type', 'expense')->get();

            // Find retained earnings account
            $retainedEarnings = ChartOfAccount::where('account_code', 'like', '3%')
                ->where('name', 'like', '%retained%earnings%')
                ->first();

            if ($retainedEarnings === null) {
                $retainedEarnings = ChartOfAccount::where('account_type', 'equity')
                    ->where('name', 'like', '%retained%')
                    ->first();
            }

            if ($retainedEarnings === null) {
                throw new DomainException(
                    'No Retained Earnings account found. Create one before year-end closing.',
                    'ACCT_NO_RETAINED_EARNINGS',
                    422
                );
            }

            // 4. Compute balances from posted JE lines for the fiscal year
            $revenueTotal = $this->sumAccountBalances($revenueAccounts->pluck('id')->toArray(), $fiscalYear);
            $expenseTotal = $this->sumAccountBalances($expenseAccounts->pluck('id')->toArray(), $fiscalYear);
            $netIncome = $revenueTotal - $expenseTotal;

            // 5. Create closing journal entry
            $je = JournalEntry::create([
                'fiscal_period_id' => $lastPeriod->id,
                'entry_date' => $lastPeriod->date_to,
                'reference_number' => "YEC-{$fiscalYear}",
                'description' => "Year-end closing entry for fiscal year {$fiscalYear}",
                'source_type' => 'year_end_closing',
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'created_by_id' => $actor->id,
            ]);

            // Debit each revenue account (to zero it out — revenue has credit balance)
            foreach ($revenueAccounts as $account) {
                $balance = $this->accountBalance($account->id, $fiscalYear);
                if ($balance !== 0) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $account->id,
                        'debit' => $balance > 0 ? $balance : null,
                        'credit' => $balance < 0 ? -$balance : null,
                        'description' => "Close revenue: {$account->name}",
                    ]);
                }
            }

            // Credit each expense account (to zero it out — expense has debit balance)
            foreach ($expenseAccounts as $account) {
                $balance = $this->accountBalance($account->id, $fiscalYear);
                if ($balance !== 0) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $account->id,
                        'debit' => $balance < 0 ? -$balance : null,
                        'credit' => $balance > 0 ? $balance : null,
                        'description' => "Close expense: {$account->name}",
                    ]);
                }
            }

            // Post net income to retained earnings
            if ($netIncome !== 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $retainedEarnings->id,
                    'debit' => $netIncome < 0 ? abs($netIncome) : null,
                    'credit' => $netIncome > 0 ? $netIncome : null,
                    'description' => "Net income to retained earnings for FY{$fiscalYear}",
                ]);
            }

            // Close the period again
            $lastPeriod->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $actor->id]);

            return [
                'journal_entry_id' => $je->id,
                'net_income' => $netIncome,
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
            ];
        });
    }

    /**
     * Sum net balance (credits - debits) for a set of accounts in a fiscal year.
     *
     * @param int[] $accountIds
     */
    private function sumAccountBalances(array $accountIds, int $fiscalYear): float
    {
        if (empty($accountIds)) {
            return 0;
        }

        $result = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('fiscal_periods', 'journal_entries.fiscal_period_id', '=', 'fiscal_periods.id')
            ->whereIn('journal_entry_lines.account_id', $accountIds)
            ->where('journal_entries.status', 'posted')
            ->whereYear('fiscal_periods.date_from', $fiscalYear)
            ->whereNull('journal_entries.deleted_at')
            ->where('journal_entries.source_type', '!=', 'year_end_closing')
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as net_balance')
            ->value('net_balance');

        return (float) $result;
    }

    /**
     * Get net balance for a single account in a fiscal year.
     */
    private function accountBalance(int $accountId, int $fiscalYear): float
    {
        return $this->sumAccountBalances([$accountId], $fiscalYear);
    }
}
