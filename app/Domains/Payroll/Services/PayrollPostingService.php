<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Payroll\Models\PayrollAdjustment;
use App\Domains\Payroll\Models\PayrollRun;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Posts a locked/completed payroll run to the General Ledger.
 *
 * GL account mapping (account codes from chart_of_accounts):
 *   DEBIT  5001 — Salaries and Wages Expense  (total gross pay)
 *   CREDIT 2100 — SSS Contributions Payable   (employee SSS share)
 *   CREDIT 2101 — PhilHealth Payable          (employee PhilHealth share)
 *   CREDIT 2102 — PagIBIG Payable             (employee PagIBIG share)
 *   CREDIT 2103 — Withholding Tax Payable     (income tax withheld)
 *   CREDIT 2200 — Payroll Payable             (net pay to employees)
 *
 * All amounts stored in pesos (centavos ÷ 100, rounded to 4dp).
 * source_type = 'payroll_run', source_id = $run->id.
 * Idempotent: returns existing JE if the run was already posted.
 */
final class PayrollPostingService implements ServiceContract
{
    /**
     * Allowed statuses for GL posting.
     * H5 FIX: Only approved runs can be posted to GL.
     */
    private const POSTABLE_STATUSES = [
        'ACCTG_APPROVED',
        'VP_APPROVED',
        'DISBURSED',   // idempotent re-post on retry
        'approved',    // legacy status
        'posted',      // legacy status (idempotent)
    ];

    /**
     * Post the given payroll run to the GL.
     * Safe to call multiple times — returns the same JE on repeated calls.
     *
     * C3 FIX: All precondition checks AND GL writes wrapped in a single
     * DB::transaction() to prevent partial writes. Status is validated
     * before any database mutation occurs.
     */
    public function postPayrollRun(PayrollRun $run): JournalEntry
    {
        // ── H5 FIX: Status guard — only approved runs can be posted ───────
        if (! in_array($run->status, self::POSTABLE_STATUSES, true)) {
            throw new DomainException(
                "Cannot post payroll run {$run->reference_no} to GL: status is '{$run->status}'. "
                .'Only ACCTG_APPROVED or VP_APPROVED runs can be posted.',
                'GL_INVALID_RUN_STATUS',
                422,
                ['current_status' => $run->status, 'allowed' => self::POSTABLE_STATUSES],
            );
        }

        // ── Idempotency guard ────────────────────────────────────────────────
        $existing = JournalEntry::where('source_type', 'payroll')
            ->where('source_id', $run->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // ── C3 FIX: Wrap ALL preconditions + writes in a single transaction ──
        return DB::transaction(function () use ($run) {
            return $this->buildAndPostJournalEntry($run);
        });
    }

    /**
     * Internal: build and persist the journal entry inside a transaction.
     */
    private function buildAndPostJournalEntry(PayrollRun $run): JournalEntry
    {
        // ── Aggregate payroll_details totals ─────────────────────────────────
        $totals = DB::table('payroll_details')
            ->where('payroll_run_id', $run->id)
            ->selectRaw(implode(', ', [
                'COALESCE(SUM(gross_pay_centavos), 0)          AS gross',
                'COALESCE(SUM(sss_ee_centavos), 0)             AS sss_ee',
                'COALESCE(SUM(philhealth_ee_centavos), 0)      AS philhealth_ee',
                'COALESCE(SUM(pagibig_ee_centavos), 0)         AS pagibig_ee',
                'COALESCE(SUM(withholding_tax_centavos), 0)    AS wht',
                'COALESCE(SUM(loan_deductions_centavos), 0)    AS loan_ded',
                'COALESCE(SUM(other_deductions_centavos), 0)   AS other_ded',
                'COALESCE(SUM(net_pay_centavos), 0)            AS net',
            ]))
            ->first();

        $gross = (int) $totals->gross;
        $sssEe = (int) $totals->sss_ee;
        $phEe = (int) $totals->philhealth_ee;
        $pagEe = (int) $totals->pagibig_ee;
        $wht = (int) $totals->wht;
        $loanDed = (int) $totals->loan_ded;
        $otherDed = (int) $totals->other_ded;
        $net = (int) $totals->net;

        if ($gross <= 0) {
            // Diagnose why gross is zero — check for missing attendance records.
            $employeeIds = DB::table('payroll_details')
                ->where('payroll_run_id', $run->id)
                ->pluck('employee_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $cutoffStart = date('Y-m-d', strtotime((string) $run->cutoff_start));
            $cutoffEnd = date('Y-m-d', strtotime((string) $run->cutoff_end));

            $attendanceCount = DB::table('attendance_logs')
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('work_date', [$cutoffStart, $cutoffEnd])
                ->count();

            if ($attendanceCount === 0) {
                $employeeCount = count($employeeIds);
                throw new DomainException(
                    "Cannot disburse payroll run {$run->reference_no}: no attendance records were found "
                    ."for {$employeeCount} employee(s) during the cutoff period "
                    ."{$cutoffStart} to {$cutoffEnd}. "
                    .'Please import or record attendance for this period, then recompute the payroll run before disbursing.',
                    'GL_NO_ATTENDANCE_FOR_CUTOFF',
                    422,
                );
            }

            throw new DomainException(
                "Cannot disburse payroll run {$run->reference_no}: gross pay is ₱0.00 despite attendance records existing. "
                .'Please recompute the payroll run to recalculate employee pay before disbursing.',
                'GL_ZERO_GROSS_PAY',
                422,
            );
        }

        // ── Resolve account IDs ───────────────────────────────────────────────
        $acctId = fn (string $code): int => DB::table('chart_of_accounts')
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->value('id')
            ?? throw new DomainException("Chart of account '{$code}' not found.", 'GL_ACCOUNT_NOT_FOUND', 422);

        // ── Find fiscal period for the pay date ───────────────────────────────
        $date = $run->pay_date ?? $run->cutoff_end;
        $payDate = date('Y-m-d', strtotime((string) $date));

        // Find the fiscal period that contains the pay date
        $fiscalPeriod = FiscalPeriod::where('date_from', '<=', $payDate)
            ->where('date_to', '>=', $payDate)
            ->where('status', 'open')
            ->first();

        if (! $fiscalPeriod) {
            throw new DomainException(
                "No open fiscal period found for date: {$payDate}",
                'GL_NO_OPEN_FISCAL_PERIOD',
                422,
            );
        }

        // ── Determine system user for created_by ──────────────────────────────
        $systemUserId = User::where('email', 'system-test@ogami.test')->value('id')
            ?? User::value('id')
            ?? auth()->id()
            ?? 1;

        // ── Build lines (amounts in pesos, numeric(15,4)) ─────────────────────
        $lines = [];

        // Debit: Salaries and Wages Expense
        $lines[] = [
            'account_id' => $acctId(config('accounting.payroll.gl_accounts.salaries_expense', '5001')),
            'debit' => round($gross / 100, 4),
            'credit' => null,
            'description' => 'Salaries and wages expense',
        ];

        // Credit: Statutory Payables
        if ($sssEe > 0) {
            $lines[] = [
                'account_id' => $acctId(config('accounting.payroll.gl_accounts.sss_payable', '2100')),
                'debit' => null,
                'credit' => round($sssEe / 100, 4),
            ];
        }
        if ($phEe > 0) {
            $lines[] = [
                'account_id' => $acctId(config('accounting.payroll.gl_accounts.philhealth_payable', '2101')),
                'debit' => null,
                'credit' => round($phEe / 100, 4),
            ];
        }
        if ($pagEe > 0) {
            $lines[] = [
                'account_id' => $acctId(config('accounting.payroll.gl_accounts.pagibig_payable', '2102')),
                'debit' => null,
                'credit' => round($pagEe / 100, 4),
            ];
        }
        if ($wht > 0) {
            $lines[] = [
                'account_id' => $acctId(config('accounting.payroll.gl_accounts.tax_payable', '2103')),
                'debit' => null,
                'credit' => round($wht / 100, 4),
            ];
        }

        // Loan deductions withheld from employees (repayments via payroll)
        if ($loanDed > 0) {
            $lines[] = [
                'account_id' => $acctId(config('accounting.payroll.gl_accounts.loans_payable', '2104')),
                'debit' => null,
                'credit' => round($loanDed / 100, 4),
                'description' => 'Loan deductions payable',
            ];
        }

        // Voluntary / other deductions (adjustments)
        // If there are other deductions, we try to break them down by GL account ID
        if ($otherDed > 0) {
            $adjustments = PayrollAdjustment::where('payroll_run_id', $run->id)
                ->where('type', 'deduction')
                ->where('status', 'applied')
                ->get();

            // Group by GL account ID (or null)
            $grouped = $adjustments->groupBy(fn ($adj) => $adj->gl_account_id ?? 'default');

            foreach ($grouped as $key => $group) {
                $amountCentavos = $group->sum('amount_centavos');
                if ($amountCentavos <= 0) {
                    continue;
                }

                $accountId = $key === 'default'
                    ? $acctId(config('accounting.payroll.gl_accounts.other_deductions_payable', '2001'))
                    : (int) $key;

                $lines[] = [
                    'account_id' => $accountId,
                    'debit' => null,
                    'credit' => round($amountCentavos / 100, 4),
                    'description' => $key === 'default' ? 'Other payroll deductions payable' : 'Payroll deduction: '.$group->first()->description,
                ];
            }

            // Fallback: If no adjustments were marked as applied but we have a deduction amount (legacy support or migration edge case)
            // We use the aggregated amount minus what we just processed
            $processedCentavos = $adjustments->sum('amount_centavos');
            $remainingCentavos = $otherDed - $processedCentavos;

            if ($remainingCentavos > 0) {
                $lines[] = [
                    'account_id' => $acctId(config('accounting.payroll.gl_accounts.other_deductions_payable', '2001')),
                    'debit' => null,
                    'credit' => round($remainingCentavos / 100, 4),
                    'description' => 'Other payroll deductions payable (unallocated)',
                ];
            }
        }

        $lines[] = [
            'account_id' => $acctId(config('accounting.payroll.gl_accounts.net_pay_payable', '2200')),
            'debit' => null,
            'credit' => round($net / 100, 4),
            'description' => 'Net pay payable',
        ];

        // ── Assert balance before writing ─────────────────────────────────────
        $creditSum = array_sum(array_column(array_filter($lines, fn ($l) => $l['credit'] !== null), 'credit'));
        $debitSum = array_sum(array_column(array_filter($lines, fn ($l) => $l['debit'] !== null), 'debit'));

        if (round($debitSum - $creditSum, 4) !== 0.0) {
            throw new DomainException(
                "Payroll GL post: JE is unbalanced. Debit={$debitSum}, Credit={$creditSum}.",
                'GL_JE_UNBALANCED',
                500,
            );
        }

        // ── Persist (already inside DB::transaction from caller) ─────────────
        $je = JournalEntry::create([
            'date' => $date,
            'description' => "Payroll Run {$run->reference_no} — auto-posted",
            'source_type' => 'payroll',
            'source_id' => $run->id,
            'status' => 'draft',
            'fiscal_period_id' => $fiscalPeriod->id,
            'created_by' => $systemUserId,
            'je_number' => null,
        ]);

        foreach ($lines as $line) {
            $je->lines()->create($line);
        }

        $je->update([
            'status' => 'posted',
            'je_number' => "JE-{$run->reference_no}",
            'posted_by' => null, // auto-post: no SoD enforced, nullable per constraint
            'posted_at' => now(),
        ]);

        return $je->fresh(['lines']);
    }
}
