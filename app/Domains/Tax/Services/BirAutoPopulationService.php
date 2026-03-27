<?php

declare(strict_types=1);

namespace App\Domains\Tax\Services;

use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Models\PayrollRun;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BIR Auto-Population Service — aggregates transaction data to pre-fill BIR form amounts.
 *
 * Sources:
 *  - Payroll: WHT (1601C), SSS/PhilHealth/PagIBIG employer shares
 *  - AP: EWT (0619E), Input VAT (2550M)
 *  - AR: Output VAT (2550M)
 *
 * All amounts returned in centavos to match the Money VO pattern.
 */
final class BirAutoPopulationService implements ServiceContract
{
    /**
     * BIR 1601-C — Monthly Remittance Return of Income Taxes Withheld on Compensation.
     *
     * @return array{
     *     total_employees: int,
     *     total_compensation_centavos: int,
     *     total_tax_withheld_centavos: int,
     *     total_taxable_income_centavos: int,
     *     total_non_taxable_centavos: int,
     * }
     */
    public function form1601c(int $year, int $month): array
    {
        $details = $this->payrollDetailsForMonth($year, $month);

        $totalCompensation = $details->sum('gross_pay_centavos');
        $totalTaxWithheld = $details->sum('withholding_tax_centavos');
        $totalGovEe = $details->sum(fn ($d) => $d->sss_ee_centavos + $d->philhealth_ee_centavos + $d->pagibig_ee_centavos
        );
        $totalTaxable = max(0, $totalCompensation - $totalGovEe);
        $totalNonTaxable = $totalGovEe; // Gov contributions are non-taxable

        return [
            'total_employees' => $details->pluck('employee_id')->unique()->count(),
            'total_compensation_centavos' => (int) $totalCompensation,
            'total_tax_withheld_centavos' => (int) $totalTaxWithheld,
            'total_taxable_income_centavos' => (int) $totalTaxable,
            'total_non_taxable_centavos' => (int) $totalNonTaxable,
        ];
    }

    /**
     * BIR 0619-E — Monthly Remittance Return of Creditable Income Taxes Withheld (Expanded).
     *
     * Source: AP vendor invoices with EWT.
     *
     * @return array{
     *     total_invoices: int,
     *     total_ewt_base_centavos: int,
     *     total_ewt_amount_centavos: int,
     * }
     */
    public function form0619e(int $year, int $month): array
    {
        $result = DB::table('vendor_invoices')
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('status', ['approved', 'paid', 'partially_paid'])
            ->whereNull('deleted_at')
            ->selectRaw('count(*) as total_invoices')
            ->selectRaw('coalesce(sum(ewt_base_amount_centavos), 0) as total_ewt_base')
            ->selectRaw('coalesce(sum(ewt_amount_centavos), 0) as total_ewt_amount')
            ->first();

        return [
            'total_invoices' => (int) ($result->total_invoices ?? 0),
            'total_ewt_base_centavos' => (int) ($result->total_ewt_base ?? 0),
            'total_ewt_amount_centavos' => (int) ($result->total_ewt_amount ?? 0),
        ];
    }

    /**
     * BIR 2550-M — Monthly VAT Declaration.
     *
     * Sources: AR invoices (output VAT) and AP invoices (input VAT).
     *
     * @return array{
     *     output_vat_centavos: int,
     *     input_vat_centavos: int,
     *     vat_payable_centavos: int,
     *     excess_input_vat_centavos: int,
     * }
     */
    public function form2550m(int $year, int $month): array
    {
        // Output VAT from customer invoices
        $outputVat = (int) DB::table('customer_invoices')
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->whereNull('deleted_at')
            ->sum(DB::raw('vat_amount * 100')); // vat_amount is decimal, convert to centavos

        // Input VAT from vendor invoices
        $inputVat = (int) DB::table('vendor_invoices')
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('status', ['approved', 'paid', 'partially_paid'])
            ->whereNull('deleted_at')
            ->sum(DB::raw('vat_amount_centavos'));

        $vatPayable = max(0, $outputVat - $inputVat);
        $excessInput = max(0, $inputVat - $outputVat);

        return [
            'output_vat_centavos' => $outputVat,
            'input_vat_centavos' => $inputVat,
            'vat_payable_centavos' => $vatPayable,
            'excess_input_vat_centavos' => $excessInput,
        ];
    }

    /**
     * SSS employer + employee contribution totals for a month.
     *
     * @return array{total_ee_centavos: int, total_er_centavos: int, total_centavos: int, employee_count: int}
     */
    public function sssMonthly(int $year, int $month): array
    {
        $details = $this->payrollDetailsForMonth($year, $month);

        return [
            'total_ee_centavos' => (int) $details->sum('sss_ee_centavos'),
            'total_er_centavos' => (int) $details->sum('sss_er_centavos'),
            'total_centavos' => (int) $details->sum(fn ($d) => $d->sss_ee_centavos + $d->sss_er_centavos),
            'employee_count' => $details->pluck('employee_id')->unique()->count(),
        ];
    }

    /**
     * PhilHealth premium totals for a month.
     *
     * @return array{total_ee_centavos: int, total_er_centavos: int, total_centavos: int, employee_count: int}
     */
    public function philhealthMonthly(int $year, int $month): array
    {
        $details = $this->payrollDetailsForMonth($year, $month);

        return [
            'total_ee_centavos' => (int) $details->sum('philhealth_ee_centavos'),
            'total_er_centavos' => (int) $details->sum('philhealth_er_centavos'),
            'total_centavos' => (int) $details->sum(fn ($d) => $d->philhealth_ee_centavos + $d->philhealth_er_centavos),
            'employee_count' => $details->pluck('employee_id')->unique()->count(),
        ];
    }

    /**
     * Pag-IBIG contribution totals for a month.
     *
     * @return array{total_ee_centavos: int, total_er_centavos: int, total_centavos: int, employee_count: int}
     */
    public function pagibigMonthly(int $year, int $month): array
    {
        $details = $this->payrollDetailsForMonth($year, $month);

        return [
            'total_ee_centavos' => (int) $details->sum('pagibig_ee_centavos'),
            'total_er_centavos' => (int) $details->sum('pagibig_er_centavos'),
            'total_centavos' => (int) $details->sum(fn ($d) => $d->pagibig_ee_centavos + $d->pagibig_er_centavos),
            'employee_count' => $details->pluck('employee_id')->unique()->count(),
        ];
    }

    // ── Internal ──────────────────────────────────────────────────────────

    /**
     * Get all payroll details for completed/published runs in a given month.
     *
     * @return Collection<int, PayrollDetail>
     */
    private function payrollDetailsForMonth(int $year, int $month): Collection
    {
        $runIds = PayrollRun::query()
            ->whereIn('status', ['DISBURSED', 'PUBLISHED', 'VP_APPROVED', 'ACCTG_APPROVED'])
            ->where(function ($q) use ($year, $month) {
                $q->whereYear('cutoff_start', $year)->whereMonth('cutoff_start', $month);
                $q->orWhere(function ($q2) use ($year, $month) {
                    $q2->whereYear('cutoff_end', $year)->whereMonth('cutoff_end', $month);
                });
            })
            ->pluck('id');

        if ($runIds->isEmpty()) {
            return collect();
        }

        return PayrollDetail::whereIn('payroll_run_id', $runIds)->get();
    }
}
