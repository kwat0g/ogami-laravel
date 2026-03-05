<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Income Statement Report (PFRS) — GL-004
 *
 * Uses account_type tiers to build the P&L waterfall:
 *
 *   REVENUE accounts  → Net Revenue
 *   COGS accounts     → Cost of Goods Sold
 *                     → Gross Profit  (Revenue - COGS)
 *   OPEX accounts     → Operating Expenses
 *                     → Operating Income  (Gross Profit - OPEX)
 *   TAX accounts      → Income Tax Expense
 *                     → Net Income  (Operating Income - Tax)
 */
final class IncomeStatementService implements ServiceContract
{
    /**
     * @return array{
     *     filters: array{date_from: string, date_to: string},
     *     revenue: array{accounts: list<array{id: int, code: string, name: string, balance: float}>, total: float},
     *     cogs: array{accounts: list<array{id: int, code: string, name: string, balance: float}>, total: float},
     *     gross_profit: float,
     *     operating_expenses: array{accounts: list<array{id: int, code: string, name: string, balance: float}>, total: float},
     *     operating_income: float,
     *     income_tax: array{accounts: list<array{id: int, code: string, name: string, balance: float}>, total: float},
     *     net_income: float,
     *     generated_at: string
     * }
     */
    public function generate(Carbon $dateFrom, Carbon $dateTo): array
    {
        $balances = $this->fetchPeriodBalances($dateFrom, $dateTo);

        $revenueAccounts = $this->buildSection($balances, 'REVENUE');
        $cogsAccounts = $this->buildSection($balances, 'COGS');
        $opexAccounts = $this->buildSection($balances, 'OPEX');
        $taxAccounts = $this->buildSection($balances, 'TAX');

        $totalRevenue = array_sum(array_column($revenueAccounts, 'balance'));
        $totalCogs = array_sum(array_column($cogsAccounts, 'balance'));
        $totalOpex = array_sum(array_column($opexAccounts, 'balance'));
        $totalTax = array_sum(array_column($taxAccounts, 'balance'));

        $grossProfit = $totalRevenue - $totalCogs;
        $operatingIncome = $grossProfit - $totalOpex;
        $netIncome = $operatingIncome - $totalTax;

        return [
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
            ],
            'revenue' => [
                'accounts' => $revenueAccounts,
                'total' => round($totalRevenue, 4),
            ],
            'cogs' => [
                'accounts' => $cogsAccounts,
                'total' => round($totalCogs, 4),
            ],
            'gross_profit' => round($grossProfit, 4),
            'operating_expenses' => [
                'accounts' => $opexAccounts,
                'total' => round($totalOpex, 4),
            ],
            'operating_income' => round($operatingIncome, 4),
            'income_tax' => [
                'accounts' => $taxAccounts,
                'total' => round($totalTax, 4),
            ],
            'net_income' => round($netIncome, 4),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Fetch the net period movement per account for income-statement account types.
     * Revenue/TAX accounts are credit-normal, so balance = credit − debit.
     * COGS/OPEX accounts are debit-normal, so balance = debit − credit.
     *
     * @return array<int, array{id: int, code: string, name: string, account_type: string, normal_balance: string, balance: float}>
     */
    private function fetchPeriodBalances(Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->whereBetween(DB::raw('je.date::date'), [
                $dateFrom->format('Y-m-d'),
                $dateTo->format('Y-m-d'),
            ])
            ->whereIn('coa.account_type', ['REVENUE', 'COGS', 'OPEX', 'TAX'])
            ->whereNull('coa.deleted_at')
            ->groupBy('jel.account_id', 'coa.id', 'coa.code', 'coa.name', 'coa.account_type', 'coa.normal_balance')
            ->select([
                DB::raw('coa.id'),
                'coa.code',
                'coa.name',
                'coa.account_type',
                'coa.normal_balance',
                DB::raw('COALESCE(SUM(jel.debit), 0) AS total_debit'),
                DB::raw('COALESCE(SUM(jel.credit), 0) AS total_credit'),
            ])
            ->orderBy('coa.code')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            $balance = $row->normal_balance === 'DEBIT'
                ? ($debit - $credit)
                : ($credit - $debit);

            $result[$row->id] = [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'account_type' => $row->account_type,
                'balance' => round($balance, 4),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{id: int, code: string, name: string, account_type: string, balance: float}>  $balances
     * @return list<array{id: int, code: string, name: string, balance: float}>
     */
    private function buildSection(array $balances, string $accountType): array
    {
        return array_values(
            array_filter(
                $balances,
                fn (array $acct) => $acct['account_type'] === $accountType,
            )
        );
    }
}
