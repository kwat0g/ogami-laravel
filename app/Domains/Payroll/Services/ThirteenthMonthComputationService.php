<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Models\ThirteenthMonthAccrual;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Handles 13th month pay computation for a single employee.
 *
 * 13TH-002: thirteenth_month_pay = sum(accrual_amount_centavos for year) / 12
 *           Divisor is always 12, regardless of months worked (PD 851).
 *
 * 13TH-004: First ₱90,000 is tax-exempt. Excess over cap is subject
 *           to withholding tax in the 13th month run.
 *           Cap is read from system_settings key 'tax.thirteenth_month_exempt_cap'.
 *
 * 13TH-005: 13th month run posts to a separate GL account; does NOT include
 *           SSS/PhilHealth/Pag-IBIG deductions (government contributions
 *           are only for regular compensation).
 */
final class ThirteenthMonthComputationService implements ServiceContract
{
    public function __construct(private readonly TaxWithholdingService $tax) {}

    /**
     * Compute and persist the 13th month PayrollDetail for one employee.
     *
     * @throws DomainException
     */
    public function computeForEmployee(Employee $employee, PayrollRun $run): PayrollDetail
    {
        if ($run->run_type !== 'thirteenth_month') {
            throw new DomainException(
                'ThirteenthMonthComputationService can only process thirteenth_month runs.',
                'PR_WRONG_RUN_TYPE',
                422,
                ['run_type' => $run->run_type],
            );
        }

        $year = (int) date('Y', strtotime($run->cutoff_end));

        // Sum all accruals for this employee in the given year
        $totalAccrualCentavos = ThirteenthMonthAccrual::where('employee_id', $employee->id)
            ->where('year', $year)
            ->sum('accrual_amount_centavos');

        // 13TH-002: divide by 12 (always)
        $thirteenthMonthCentavos = (int) round($totalAccrualCentavos / 12);

        // 13TH-004: Tax-exempt cap from system_settings
        $exemptCapCentavos = (int) (
            DB::table('system_settings')
                ->where('key', 'tax.thirteenth_month_exempt_cap')
                ->value('value')
                ?? json_encode(9000000) // default ₱90,000 in centavos
        );

        $decoded = json_decode(
            (string) DB::table('system_settings')
                ->where('key', 'tax.thirteenth_month_exempt_cap')
                ->value('value') ?? '9000000',
            true
        );

        if (is_numeric($decoded)) {
            $exemptCapCentavos = (int) $decoded;
        }

        // Taxable portion = amount above the cap (can be 0)
        $taxablePortionCentavos = max(0, $thirteenthMonthCentavos - $exemptCapCentavos);

        // Withholding tax on the taxable excess only
        // Use the annualised method: supply (gross_pay = taxable excess, ytd = 0)
        $withholdingTaxCentavos = $taxablePortionCentavos > 0
            ? $this->tax->compute(
                grossPayCentavos: $taxablePortionCentavos,
                ytdTaxableIncomeCentavos: 0,
                ytdTaxWithheldCentavos: 0,
                isSecondCutoff: true,
            )
            : 0;

        $netPayCentavos = $thirteenthMonthCentavos - $withholdingTaxCentavos;

        // Load YTD from previous details in the same year
        $ytdTaxable = (int) PayrollDetail::where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->whereYear('pay_date', $year)
                ->where('status', 'completed')
                ->where('run_type', 'regular')
            )
            ->sum('ytd_taxable_income_centavos');

        $ytdTaxWithheld = (int) PayrollDetail::where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->whereYear('pay_date', $year)
                ->where('status', 'completed')
                ->where('run_type', 'regular')
            )
            ->sum('ytd_tax_withheld_centavos');

        return PayrollDetail::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                // Snapshot (rate at time of month 12 or last accrual)
                'basic_monthly_rate_centavos' => $employee->basic_monthly_rate,
                'daily_rate_centavos' => $employee->daily_rate,
                'hourly_rate_centavos' => $employee->hourly_rate,
                'working_days_in_period' => 0,
                'pay_basis' => $employee->pay_basis,
                // Attendance (not applicable for 13th month)
                'days_worked' => 0,
                'days_absent' => 0,
                'days_late_minutes' => 0,
                'undertime_minutes' => 0,
                'overtime_regular_minutes' => 0,
                'overtime_rest_day_minutes' => 0,
                'overtime_holiday_minutes' => 0,
                'night_diff_minutes' => 0,
                'regular_holiday_days' => 0,
                'special_holiday_days' => 0,
                'leave_days_paid' => 0,
                'leave_days_unpaid' => 0,
                // Earnings
                'basic_pay_centavos' => $thirteenthMonthCentavos,
                'overtime_pay_centavos' => 0,
                'holiday_pay_centavos' => 0,
                'night_diff_pay_centavos' => 0,
                'gross_pay_centavos' => $thirteenthMonthCentavos,
                // Gov deductions — N/A for 13TH (13TH-005)
                'sss_ee_centavos' => 0,
                'philhealth_ee_centavos' => 0,
                'pagibig_ee_centavos' => 0,
                'withholding_tax_centavos' => $withholdingTaxCentavos,
                // Loan + other deductions — N/A for 13th month
                'loan_deductions_centavos' => 0,
                'loan_deduction_detail' => null,
                'other_deductions_centavos' => 0,
                'total_deductions_centavos' => $withholdingTaxCentavos,
                'net_pay_centavos' => $netPayCentavos,
                'is_below_min_wage' => false,
                'has_deferred_deductions' => false,
                // YTD (cumulative including this run)
                'ytd_taxable_income_centavos' => $ytdTaxable + $taxablePortionCentavos,
                'ytd_tax_withheld_centavos' => $ytdTaxWithheld + $withholdingTaxCentavos,
                'status' => 'computed',
                'notes' => "13th month: accruals={$totalAccrualCentavos}¢ ÷12={$thirteenthMonthCentavos}¢ exempt={$exemptCapCentavos}¢",
            ]
        );
    }
}
