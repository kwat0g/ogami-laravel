<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cash Flow Statement — Indirect Method (PFRS) — GL-005
 *
 * Structure:
 *   Operating Activities
 *     Net Income                        (from IncomeStatementService)
 *     ± Adjustments for non-cash items  (cf_classification = 'operating')
 *     ± Changes in working capital      (ASSET/LIABILITY accounts, cf_classification = 'operating')
 *   ---
 *   Investing Activities                 (cf_classification = 'investing')
 *   Financing Activities                 (cf_classification = 'financing')
 *   ---
 *   Net Increase / (Decrease) in Cash
 *   Opening Cash Balance
 *   Closing Cash Balance
 *
 * Accounts must have `cf_classification` set on chart_of_accounts for proper
 * classification. Accounts tagged 'cash_equivalent' are used to compute
 * opening and closing cash balances.
 */
final class CashFlowService implements ServiceContract
{
    public function __construct(
        private readonly IncomeStatementService $incomeStatementService,
    ) {}

    /**
     * @return array{
     *     filters: array{date_from: string, date_to: string},
     *     operating: array{
     *         net_income: float,
     *         adjustments: list<array{id: int, code: string, name: string, amount: float}>,
     *         total_operating: float
     *     },
     *     investing: array{
     *         lines: list<array{id: int, code: string, name: string, amount: float}>,
     *         total_investing: float
     *     },
     *     financing: array{
     *         lines: list<array{id: int, code: string, name: string, amount: float}>,
     *         total_financing: float
     *     },
     *     net_change_in_cash: float,
     *     opening_cash_balance: float,
     *     closing_cash_balance: float,
     *     generated_at: string
     * }
     */
    public function generate(Carbon $dateFrom, Carbon $dateTo): array
    {
        // ── Net Income (from Income Statement) ────────────────────────────────
        $is = $this->incomeStatementService->generate($dateFrom, $dateTo);
        $netIncome = $is['net_income'];

        // ── Period movements per account for cf_classified accounts ──────────
        $periodMovements = $this->fetchPeriodMovements($dateFrom, $dateTo);

        $operatingAdjustments = [];
        $investingLines = [];
        $financingLines = [];

        foreach ($periodMovements as $row) {
            $entry = [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'amount' => round((float) $row->net_movement, 4),
            ];

            switch ($row->cf_classification) {
                case 'operating':
                    $operatingAdjustments[] = $entry;
                    break;
                case 'investing':
                    $investingLines[] = $entry;
                    break;
                case 'financing':
                    $financingLines[] = $entry;
                    break;
            }
        }

        // ── Totals ────────────────────────────────────────────────────────────
        $totalAdjustments = array_sum(array_column($operatingAdjustments, 'amount'));
        $totalOperating = round($netIncome + $totalAdjustments, 4);
        $totalInvesting = round(array_sum(array_column($investingLines, 'amount')), 4);
        $totalFinancing = round(array_sum(array_column($financingLines, 'amount')), 4);

        $netChange = round($totalOperating + $totalInvesting + $totalFinancing, 4);

        // ── Opening / Closing cash balances ──────────────────────────────────
        $openingCash = $this->fetchCashBalance($dateFrom->copy()->subDay());
        $closingCash = round($openingCash + $netChange, 4);

        return [
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
            ],
            'operating' => [
                'net_income' => $netIncome,
                'adjustments' => $operatingAdjustments,
                'total_operating' => $totalOperating,
            ],
            'investing' => [
                'lines' => $investingLines,
                'total_investing' => $totalInvesting,
            ],
            'financing' => [
                'lines' => $financingLines,
                'total_financing' => $totalFinancing,
            ],
            'net_change_in_cash' => $netChange,
            'opening_cash_balance' => round($openingCash, 4),
            'closing_cash_balance' => round($closingCash, 4),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Get period net movements for accounts with cf_classification in
     * (operating, investing, financing).
     *
     * For ASSET accounts (debit-normal): positive = increase in asset = use of cash → negative adjustment
     * For LIABILITY accounts (credit-normal): positive = increase in liability = source of cash → positive adjustment
     *
     * The sign convention is applied here:
     *   - OPEX/depreciation: debit-normal, period movement is positive (debit > credit) → adds back to net income
     *   - Working capital: ASSET increase = subtract; LIABILITY increase = add
     */
    private function fetchPeriodMovements(Carbon $dateFrom, Carbon $dateTo): Collection
    {
        return DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->whereBetween(DB::raw('je.date::date'), [
                $dateFrom->format('Y-m-d'),
                $dateTo->format('Y-m-d'),
            ])
            ->whereIn('coa.cf_classification', ['operating', 'investing', 'financing'])
            ->whereNull('coa.deleted_at')
            ->groupBy('jel.account_id', 'coa.id', 'coa.code', 'coa.name', 'coa.normal_balance', 'coa.cf_classification')
            ->select([
                DB::raw('coa.id'),
                'coa.code',
                'coa.name',
                'coa.normal_balance',
                'coa.cf_classification',
                // Net movement: debit-normal → (debit - credit); credit-normal → (credit - debit)
                DB::raw("
                    CASE WHEN coa.normal_balance = 'DEBIT'
                         THEN COALESCE(SUM(jel.debit), 0) - COALESCE(SUM(jel.credit), 0)
                         ELSE COALESCE(SUM(jel.credit), 0) - COALESCE(SUM(jel.debit), 0)
                    END AS net_movement
                "),
            ])
            ->orderBy('coa.code')
            ->get();
    }

    /**
     * Cash balance = sum of all posted JE lines on accounts tagged 'cash_equivalent'
     * up to and including the given date.
     */
    private function fetchCashBalance(Carbon $asOfDate): float
    {
        $row = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where(DB::raw('je.date::date'), '<=', $asOfDate->format('Y-m-d'))
            ->where('coa.cf_classification', 'cash_equivalent')
            ->whereNull('coa.deleted_at')
            ->selectRaw(
                'COALESCE(SUM(jel.debit), 0) - COALESCE(SUM(jel.credit), 0) AS cash_balance'
            )
            ->first();

        return (float) ($row->cash_balance ?? 0);
    }
}
