<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger Report — GL-001
 *
 * Returns line-by-line journal entry movements for a specific account within
 * a date range, with an opening balance and a running balance per line.
 *
 * @return array{
 *     account: array{id: int, code: string, name: string, normal_balance: string},
 *     filters: array{date_from: string, date_to: string, cost_center_id: int|null},
 *     opening_balance: float,
 *     lines: list<array{
 *         date: string,
 *         je_number: string|null,
 *         description: string,
 *         source_type: string,
 *         debit: float|null,
 *         credit: float|null,
 *         running_balance: float
 *     }>,
 *     closing_balance: float,
 *     generated_at: string
 * }
 */
final class GeneralLedgerService implements ServiceContract
{
    /**
     * Generate the GL report for a specific account.
     *
     * @throws DomainException if the account does not exist
     */
    public function generate(
        int $accountId,
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $costCenterId = null,
    ): array {
        /** @var ChartOfAccount $account */
        $account = ChartOfAccount::find($accountId);

        if ($account === null) {
            throw new DomainException(
                message: "Chart of account #{$accountId} not found.",
                errorCode: 'GL_ACCOUNT_NOT_FOUND',
                httpStatus: 404,
            );
        }

        $isDebitNormal = $account->normal_balance === 'DEBIT';

        // ── Opening balance: all posted lines strictly BEFORE dateFrom ────────
        $openingBalance = $this->computeAccountBalance(
            accountId: $accountId,
            beforeDate: $dateFrom,
            costCenterId: $costCenterId,
            isDebitNormal: $isDebitNormal,
        );

        // ── Period lines: posted lines in [dateFrom, dateTo] ─────────────────
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.account_id', $accountId)
            ->where('je.status', 'posted')
            ->whereBetween(DB::raw('je.date::date'), [
                $dateFrom->format('Y-m-d'),
                $dateTo->format('Y-m-d'),
            ])
            ->orderBy('je.date')
            ->orderBy('je.id')
            ->orderBy('jel.id');

        if ($costCenterId !== null) {
            $query->where('jel.cost_center_id', $costCenterId);
        }

        $rawLines = $query->select([
            'je.date',
            'je.je_number',
            DB::raw('COALESCE(jel.description, je.description) AS description'),
            'je.source_type',
            'jel.debit',
            'jel.credit',
        ])->get();

        // ── Build lines with running balance ──────────────────────────────────
        $runningBalance = $openingBalance;
        $lines = [];

        foreach ($rawLines as $row) {
            $debit = $row->debit !== null ? (float) $row->debit : null;
            $credit = $row->credit !== null ? (float) $row->credit : null;

            if ($isDebitNormal) {
                $runningBalance += ($debit ?? 0) - ($credit ?? 0);
            } else {
                $runningBalance += ($credit ?? 0) - ($debit ?? 0);
            }

            $lines[] = [
                'date' => $row->date,
                'je_number' => $row->je_number,
                'description' => $row->description,
                'source_type' => $row->source_type,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => round($runningBalance, 4),
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'normal_balance' => $account->normal_balance,
            ],
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'cost_center_id' => $costCenterId,
            ],
            'opening_balance' => round($openingBalance, 4),
            'lines' => $lines,
            'closing_balance' => round($runningBalance, 4),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function computeAccountBalance(
        int $accountId,
        Carbon $beforeDate,
        ?int $costCenterId,
        bool $isDebitNormal,
    ): float {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.account_id', $accountId)
            ->where('je.status', 'posted')
            ->where(DB::raw('je.date::date'), '<', $beforeDate->format('Y-m-d'));

        if ($costCenterId !== null) {
            $query->where('jel.cost_center_id', $costCenterId);
        }

        $totals = $query->selectRaw(
            'COALESCE(SUM(jel.debit), 0) AS total_debit, COALESCE(SUM(jel.credit), 0) AS total_credit'
        )->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return $isDebitNormal ? ($debit - $credit) : ($credit - $debit);
    }
}
