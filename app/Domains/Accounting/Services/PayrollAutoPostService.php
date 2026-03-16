<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Payroll\Models\PayrollRun;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payroll → General Ledger Auto-Post Service.
 *
 * Called when a PayrollRun transitions to 'approved'. Builds a balanced
 * double-entry journal entry from the run's aggregated payroll_details.
 *
 * Journal entry structure (per accounting convention):
 *
 *  DEBIT  Salaries Expense             gross_pay total
 *  DEBIT  SSS Contribution Expense-ER  sss_er total
 *  DEBIT  PhilHealth Exp-ER            philhealth_er total
 *  DEBIT  Pag-IBIG Exp-ER              pagibig_er total
 *  CREDIT Cash in Bank                 net_pay total
 *  CREDIT SSS Payable                  sss_ee + sss_er total
 *  CREDIT PhilHealth Payable           philhealth_ee + philhealth_er total
 *  CREDIT Pag-IBIG Payable             pagibig_ee + pagibig_er total
 *  CREDIT Withholding Tax Payable      withholding_tax total
 *  CREDIT Loans Payable                loan_deductions total (if > 0)
 *  CREDIT Other Deductions Payable     other_deductions total (if > 0)
 *
 * All account codes are loaded from system_settings (accounting group) —
 * zero hardcoding (S3). If a required setting is missing, the service
 * throws with a clear message indicating which key to configure.
 *
 * JE-008: source_type = 'payroll', source_id = payroll_run_id.
 * Idempotent: skips if a posted JE already exists for this run (S10).
 */
final class PayrollAutoPostService implements ServiceContract
{
    public function __construct(
        private readonly JournalEntryService $jeService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {}

    /**
     * Auto-post a completed & approved payroll run to the GL.
     * Idempotent — calling twice produces only one posted JE.
     */
    public function post(PayrollRun $run): JournalEntry
    {
        // Idempotency guard (S10)
        $existing = JournalEntry::where('source_type', 'payroll')
            ->where('source_id', $run->id)
            ->where('status', 'posted')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $totals = $this->aggregateTotals($run);
        $codes = $this->loadAccountCodes();

        // Build the balanced lines array (centavos → pesos)
        $lines = $this->buildLines($totals, $codes);

        $fiscalPeriod = $this->fiscalPeriodService->resolveForDateOrFail(
            Carbon::parse($run->pay_date)
        );

        return DB::transaction(function () use ($run, $lines, $fiscalPeriod) {
            // Use create() but bypass the SoD check (source_type = 'payroll')
            $je = JournalEntry::create([
                'date' => $run->pay_date,
                'description' => "Payroll: {$run->pay_period_label} — auto-posted on approval",
                'source_type' => 'payroll',
                'source_id' => $run->id,
                'status' => 'draft', // will immediately post below
                'fiscal_period_id' => $fiscalPeriod->id,
                'created_by' => auth()->id() ?? $run->approved_by,
                'je_number' => null,
            ]);

            foreach ($lines as $line) {
                $je->lines()->create($line);
            }

            // Auto-post immediately — no SoD check for system-generated entries
            $jeNumber = "JE-{$run->pay_date}-PR{$run->id}";
            $je->update([
                'status' => 'posted',
                'je_number' => $jeNumber,
                'posted_by' => auth()->id() ?? $run->approved_by,
                'posted_at' => now(),
            ]);

            return $je->fresh(['lines.account']);
        });
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    /** Aggregate payroll_details sums for the run (single query — no N+1). */
    private function aggregateTotals(PayrollRun $run): object
    {
        return DB::table('payroll_details')
            ->where('payroll_run_id', $run->id)
            ->selectRaw('
                COALESCE(SUM(gross_pay_centavos), 0)          AS gross_pay,
                COALESCE(SUM(net_pay_centavos), 0)             AS net_pay,
                COALESCE(SUM(sss_ee_centavos), 0)              AS sss_ee,
                COALESCE(SUM(sss_er_centavos), 0)              AS sss_er,
                COALESCE(SUM(philhealth_ee_centavos), 0)       AS philhealth_ee,
                COALESCE(SUM(philhealth_er_centavos), 0)       AS philhealth_er,
                COALESCE(SUM(pagibig_ee_centavos), 0)          AS pagibig_ee,
                COALESCE(SUM(pagibig_er_centavos), 0)          AS pagibig_er,
                COALESCE(SUM(withholding_tax_centavos), 0)     AS wht,
                COALESCE(SUM(loan_deductions_centavos), 0)     AS loans,
                COALESCE(SUM(other_deductions_centavos), 0)    AS other_deductions
            ')
            ->first();
    }

    /**
     * Load all required account codes from system_settings.
     * Throws a clear DomainException if any key is missing.
     */
    private function loadAccountCodes(): array
    {
        $keys = [
            'accounting.salaries_expense_code',
            'accounting.sss_er_expense_code',
            'accounting.philhealth_er_expense_code',
            'accounting.pagibig_er_expense_code',
            'accounting.cash_in_bank_code',
            'accounting.sss_payable_code',
            'accounting.philhealth_payable_code',
            'accounting.pagibig_payable_code',
            'accounting.withholding_tax_payable_code',
            'accounting.loans_payable_code',
            'accounting.other_deductions_payable_code',
        ];

        $rows = DB::table('system_settings')
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        $codes = [];
        foreach ($keys as $key) {
            if (! isset($rows[$key])) {
                throw new DomainException(
                    message: "System setting '{$key}' is not configured. Set the account code in Accounting Settings before approving payroll runs.",
                    errorCode: 'ACCOUNTING_SETTING_MISSING',
                    httpStatus: 422,
                    context: ['missing_key' => $key],
                );
            }
            // Values are stored as JSON strings (e.g. "\"5001\"") — decode them
            $codes[$key] = json_decode($rows[$key], true);
        }

        return $codes;
    }

    /**
     * Resolve a COA id from code. Throws if the account doesn't exist.
     */
    private function resolveAccountId(string $code): int
    {
        $id = DB::table('chart_of_accounts')
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->value('id');

        if ($id === null) {
            throw new DomainException(
                message: "Chart of accounts entry with code '{$code}' not found. Create the account before posting payroll.",
                errorCode: 'ACCOUNT_NOT_FOUND',
                httpStatus: 422,
                context: ['account_code' => $code],
            );
        }

        return $id;
    }

    /** Build the balanced journal entry lines array (amounts in pesos). */
    private function buildLines(object $t, array $c): array
    {
        $lines = [];

        // Helper to add a line only when amount > 0
        $dr = function (string $settingKey, float $amountCentavos) use (&$lines, $c): void {
            if ($amountCentavos <= 0) {
                return;
            }
            $lines[] = [
                'account_id' => $this->resolveAccountId($c[$settingKey]),
                'debit' => round($amountCentavos / 100, 4),
                'credit' => null,
            ];
        };

        $cr = function (string $settingKey, float $amountCentavos) use (&$lines, $c): void {
            if ($amountCentavos <= 0) {
                return;
            }
            $lines[] = [
                'account_id' => $this->resolveAccountId($c[$settingKey]),
                'debit' => null,
                'credit' => round($amountCentavos / 100, 4),
            ];
        };

        // ── Debit lines ──────────────────────────────────────────────────────
        $dr('accounting.salaries_expense_code', (float) $t->gross_pay);
        $dr('accounting.sss_er_expense_code', (float) $t->sss_er);
        $dr('accounting.philhealth_er_expense_code', (float) $t->philhealth_er);
        $dr('accounting.pagibig_er_expense_code', (float) $t->pagibig_er);

        // ── Credit lines ─────────────────────────────────────────────────────
        $cr('accounting.cash_in_bank_code', (float) $t->net_pay);
        $cr('accounting.sss_payable_code', (float) ($t->sss_ee + $t->sss_er));
        $cr('accounting.philhealth_payable_code', (float) ($t->philhealth_ee + $t->philhealth_er));
        $cr('accounting.pagibig_payable_code', (float) ($t->pagibig_ee + $t->pagibig_er));
        $cr('accounting.withholding_tax_payable_code', (float) $t->wht);
        $cr('accounting.loans_payable_code', (float) $t->loans);
        $cr('accounting.other_deductions_payable_code', (float) $t->other_deductions);

        return $lines;
    }
}
