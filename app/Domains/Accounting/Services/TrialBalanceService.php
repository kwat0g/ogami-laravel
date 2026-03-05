<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Trial Balance Report — GL-002
 *
 * Produces a columnar report showing for every active account:
 *   - Opening debit / credit (all posted lines before dateFrom)
 *   - Period debit / credit (posted lines in the date range)
 *   - Closing debit / credit (opening + period)
 *
 * Accounts with zero activity in all columns are included.
 */
final class TrialBalanceService implements ServiceContract
{
    /**
     * @return array{
     *     filters: array{date_from: string, date_to: string},
     *     accounts: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         account_type: string,
     *         normal_balance: string,
     *         opening_debit: float,
     *         opening_credit: float,
     *         period_debit: float,
     *         period_credit: float,
     *         closing_debit: float,
     *         closing_credit: float
     *     }>,
     *     totals: array{
     *         opening_debit: float,
     *         opening_credit: float,
     *         period_debit: float,
     *         period_credit: float,
     *         closing_debit: float,
     *         closing_credit: float
     *     },
     *     generated_at: string
     * }
     */
    public function generate(Carbon $dateFrom, Carbon $dateTo): array
    {
        $dateFromStr = $dateFrom->format('Y-m-d');
        $dateToStr = $dateTo->format('Y-m-d');

        // All leaf accounts (active, not archived)
        $allAccounts = DB::table('chart_of_accounts')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type', 'normal_balance']);

        if ($allAccounts->isEmpty()) {
            return [
                'filters' => ['date_from' => $dateFromStr, 'date_to' => $dateToStr],
                'accounts' => [],
                'totals' => array_fill_keys(['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'], 0.0),
                'generated_at' => now()->toIso8601String(),
            ];
        }

        $accountIds = $allAccounts->pluck('id')->toArray();

        // Opening aggregates (before dateFrom)
        $openingTotals = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.status', 'posted')
            ->where(DB::raw('je.date::date'), '<', $dateFromStr)
            ->whereIn('jel.account_id', $accountIds)
            ->groupBy('jel.account_id')
            ->select([
                'jel.account_id',
                DB::raw('COALESCE(SUM(jel.debit), 0) AS total_debit'),
                DB::raw('COALESCE(SUM(jel.credit), 0) AS total_credit'),
            ])
            ->get()
            ->keyBy('account_id');

        // Period aggregates (within date range)
        $periodTotals = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.status', 'posted')
            ->whereBetween(DB::raw('je.date::date'), [$dateFromStr, $dateToStr])
            ->whereIn('jel.account_id', $accountIds)
            ->groupBy('jel.account_id')
            ->select([
                'jel.account_id',
                DB::raw('COALESCE(SUM(jel.debit), 0) AS total_debit'),
                DB::raw('COALESCE(SUM(jel.credit), 0) AS total_credit'),
            ])
            ->get()
            ->keyBy('account_id');

        // Build per-account rows
        $accounts = [];
        $totals = ['opening_debit' => 0.0, 'opening_credit' => 0.0, 'period_debit' => 0.0, 'period_credit' => 0.0, 'closing_debit' => 0.0, 'closing_credit' => 0.0];

        foreach ($allAccounts as $acct) {
            $openingDebit = (float) ($openingTotals[$acct->id]->total_debit ?? 0);
            $openingCredit = (float) ($openingTotals[$acct->id]->total_credit ?? 0);
            $periodDebit = (float) ($periodTotals[$acct->id]->total_debit ?? 0);
            $periodCredit = (float) ($periodTotals[$acct->id]->total_credit ?? 0);

            $closingDebit = $openingDebit + $periodDebit;
            $closingCredit = $openingCredit + $periodCredit;

            $accounts[] = [
                'id' => $acct->id,
                'code' => $acct->code,
                'name' => $acct->name,
                'account_type' => $acct->account_type,
                'normal_balance' => $acct->normal_balance,
                'opening_debit' => round($openingDebit, 4),
                'opening_credit' => round($openingCredit, 4),
                'period_debit' => round($periodDebit, 4),
                'period_credit' => round($periodCredit, 4),
                'closing_debit' => round($closingDebit, 4),
                'closing_credit' => round($closingCredit, 4),
            ];

            $totals['opening_debit'] += $openingDebit;
            $totals['opening_credit'] += $openingCredit;
            $totals['period_debit'] += $periodDebit;
            $totals['period_credit'] += $periodCredit;
            $totals['closing_debit'] += $closingDebit;
            $totals['closing_credit'] += $closingCredit;
        }

        foreach ($totals as $key => $val) {
            $totals[$key] = round($val, 4);
        }

        return [
            'filters' => ['date_from' => $dateFromStr, 'date_to' => $dateToStr],
            'accounts' => $accounts,
            'totals' => $totals,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
