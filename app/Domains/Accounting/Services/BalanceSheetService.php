<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Balance Sheet Report (PFRS classified) — GL-003
 *
 * Generates a balance sheet as-of a given date, classified into:
 *   Current Assets / Non-Current Assets /
 *   Current Liabilities / Non-Current Liabilities / Equity
 *
 * Accounts must have `bs_classification` set on chart_of_accounts.
 * Accounts without a classification are grouped under their account_type.
 *
 * Supports an optional comparative-date column.
 */
final class BalanceSheetService implements ServiceContract
{
    /** Ordered sections for display */
    private const SECTIONS = [
        'current_asset' => 'Current Assets',
        'non_current_asset' => 'Non-Current Assets',
        'current_liability' => 'Current Liabilities',
        'non_current_liability' => 'Non-Current Liabilities',
        'equity' => 'Equity',
    ];

    /**
     * @param  Carbon|null  $comparativeDate  If provided, adds a second column
     * @return array{
     *     filters: array{as_of_date: string, comparative_date: string|null},
     *     sections: list<array{
     *         key: string,
     *         label: string,
     *         accounts: list<array{id: int, code: string, name: string, balance: float, comparative: float|null}>,
     *         total: float,
     *         comparative_total: float|null
     *     }>,
     *     totals: array{
     *         total_assets: float,
     *         total_liabilities: float,
     *         total_equity: float,
     *         total_liabilities_and_equity: float,
     *         comparative_total_assets: float|null,
     *         comparative_total_liabilities: float|null,
     *         comparative_total_equity: float|null
     *     },
     *     generated_at: string
     * }
     */
    public function generate(Carbon $asOfDate, ?Carbon $comparativeDate = null): array
    {
        $balances = $this->fetchBalances($asOfDate);
        $comparativeBalances = $comparativeDate ? $this->fetchBalances($comparativeDate) : [];

        $accounts = DB::table('chart_of_accounts')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereIn('account_type', ['ASSET', 'LIABILITY', 'EQUITY'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type', 'normal_balance', 'bs_classification', 'is_current']);

        $sections = [];

        foreach (self::SECTIONS as $key => $label) {
            $isAssetSection = str_contains($key, 'asset');
            $isLiabSection = str_contains($key, 'liability');
            $isEquitySection = $key === 'equity';

            $rows = [];
            foreach ($accounts as $acct) {
                $classif = $acct->bs_classification ?? $this->inferClassification($acct);
                if ($classif !== $key) {
                    continue;
                }

                $balance = $balances[$acct->id] ?? 0.0;
                $comparative = $comparativeDate ? ($comparativeBalances[$acct->id] ?? 0.0) : null;

                $rows[] = [
                    'id' => $acct->id,
                    'code' => $acct->code,
                    'name' => $acct->name,
                    'balance' => round($balance, 4),
                    'comparative' => $comparative !== null ? round($comparative, 4) : null,
                ];
            }

            $total = array_sum(array_column($rows, 'balance'));
            $comparativeTotal = $comparativeDate
                ? array_sum(array_filter(array_column($rows, 'comparative'), fn ($v) => $v !== null))
                : null;

            $sections[] = [
                'key' => $key,
                'label' => $label,
                'accounts' => $rows,
                'total' => round($total, 4),
                'comparative_total' => $comparativeTotal !== null ? round($comparativeTotal, 4) : null,
            ];
        }

        // Aggregate section totals
        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;
        $compAssets = 0.0;
        $compLiabilities = 0.0;
        $compEquity = 0.0;

        foreach ($sections as $section) {
            if (str_contains($section['key'], 'asset')) {
                $totalAssets += $section['total'];
                $compAssets += $section['comparative_total'] ?? 0;
            } elseif (str_contains($section['key'], 'liability')) {
                $totalLiabilities += $section['total'];
                $compLiabilities += $section['comparative_total'] ?? 0;
            } elseif ($section['key'] === 'equity') {
                $totalEquity += $section['total'];
                $compEquity += $section['comparative_total'] ?? 0;
            }
        }

        return [
            'filters' => [
                'as_of_date' => $asOfDate->format('Y-m-d'),
                'comparative_date' => $comparativeDate?->format('Y-m-d'),
            ],
            'sections' => $sections,
            'totals' => [
                'total_assets' => round($totalAssets, 4),
                'total_liabilities' => round($totalLiabilities, 4),
                'total_equity' => round($totalEquity, 4),
                'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 4),
                'comparative_total_assets' => $comparativeDate ? round($compAssets, 4) : null,
                'comparative_total_liabilities' => $comparativeDate ? round($compLiabilities, 4) : null,
                'comparative_total_equity' => $comparativeDate ? round($compEquity, 4) : null,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Fetch account balances (signed, per normal_balance) up to and including
     * the given date.
     *
     * @return array<int, float> keyed by account_id
     */
    private function fetchBalances(Carbon $asOfDate): array
    {
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where(DB::raw('je.date::date'), '<=', $asOfDate->format('Y-m-d'))
            ->whereIn('coa.account_type', ['ASSET', 'LIABILITY', 'EQUITY'])
            ->groupBy('jel.account_id', 'coa.normal_balance')
            ->select([
                'jel.account_id',
                'coa.normal_balance',
                DB::raw('COALESCE(SUM(jel.debit), 0) AS total_debit'),
                DB::raw('COALESCE(SUM(jel.credit), 0) AS total_credit'),
            ])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            $result[$row->account_id] = $row->normal_balance === 'DEBIT'
                ? ($debit - $credit)
                : ($credit - $debit);
        }

        return $result;
    }

    /**
     * Infer a bs_classification from account_type when not explicitly set.
     * This is a best-effort fallback; proper data requires explicit classification.
     */
    private function inferClassification(object $acct): string
    {
        return match ($acct->account_type) {
            'ASSET' => $acct->is_current ? 'current_asset' : 'non_current_asset',
            'LIABILITY' => $acct->is_current ? 'current_liability' : 'non_current_liability',
            'EQUITY', 'REVENUE', 'COGS', 'OPEX', 'TAX' => 'equity',
            default => 'none',
        };
    }
}
