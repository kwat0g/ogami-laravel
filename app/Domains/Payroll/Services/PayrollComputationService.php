<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Pipeline\Step01SnapshotsStep;
use App\Domains\Payroll\Pipeline\Step02PeriodMetaStep;
use App\Domains\Payroll\Pipeline\Step03AttendanceSummaryStep;
use App\Domains\Payroll\Pipeline\Step04LoadYtdStep;
use App\Domains\Payroll\Pipeline\Step05BasicPayStep;
use App\Domains\Payroll\Pipeline\Step06OvertimePayStep;
use App\Domains\Payroll\Pipeline\Step07HolidayPayStep;
use App\Domains\Payroll\Pipeline\Step08NightDiffStep;
use App\Domains\Payroll\Pipeline\Step09GrossPayStep;
use App\Domains\Payroll\Pipeline\Step10SssStep;
use App\Domains\Payroll\Pipeline\Step11PhilHealthStep;
use App\Domains\Payroll\Pipeline\Step12PagibigStep;
use App\Domains\Payroll\Pipeline\Step13TaxableIncomeStep;
use App\Domains\Payroll\Pipeline\Step14WithholdingTaxStep;
use App\Domains\Payroll\Pipeline\Step15LoanDeductionsStep;
use App\Domains\Payroll\Pipeline\Step16OtherDeductionsStep;
use App\Domains\Payroll\Pipeline\Step17NetPayStep;
use App\Domains\Payroll\Pipeline\Step18ThirteenthMonthStep;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pipeline\Pipeline;

/**
 * Payroll Computation Service — 17-step pipeline per employee per period.
 *
 * Dispatches the computation through a Laravel Pipeline comprising 17
 * discrete step classes in App\Domains\Payroll\Pipeline\. Each step
 * reads from and writes to a shared PayrollComputationContext object.
 *
 * The entry point is `computeForEmployee()`, which returns a persisted
 * PayrollDetail record.
 *
 * Monetary values: all centavos (integer ×100). Never float inside pipeline.
 * Rounding: ROUND_HALF_UP at every division boundary.
 */
final class PayrollComputationService implements ServiceContract
{
    /**
     * Run the full 17-step payroll pipeline for one employee.
     * Creates (or updates if re-run) a PayrollDetail row.
     *
     * @throws DomainException If the run is not in a computable status.
     */
    public function computeForEmployee(Employee $employee, PayrollRun $run): PayrollDetail
    {
        if (! in_array(strtolower((string) $run->status), ['locked', 'processing'], true)) {
            throw new DomainException(
                'Payroll can only be computed when the run is in locked or processing status.',
                'PR_NOT_COMPUTABLE',
                422,
                ['run_id' => $run->id, 'status' => $run->status],
            );
        }

        $ctx = new PayrollComputationContext($employee, $run);

        /** @var PayrollComputationContext $ctx */
        $ctx = app(Pipeline::class)
            ->send($ctx)
            ->through([
                Step01SnapshotsStep::class,
                Step02PeriodMetaStep::class,
                Step03AttendanceSummaryStep::class,
                Step04LoadYtdStep::class,
                Step05BasicPayStep::class,
                Step06OvertimePayStep::class,
                Step07HolidayPayStep::class,
                Step08NightDiffStep::class,
                Step09GrossPayStep::class,
                Step10SssStep::class,
                Step11PhilHealthStep::class,
                Step12PagibigStep::class,
                Step13TaxableIncomeStep::class,
                Step14WithholdingTaxStep::class,
                Step15LoanDeductionsStep::class,
                Step16OtherDeductionsStep::class,
                Step17NetPayStep::class,
                Step18ThirteenthMonthStep::class,
            ])
            ->thenReturn();

        return $this->persist($ctx);
    }

    // ─── Persistence ──────────────────────────────────────────────────────────

    private function persist(PayrollComputationContext $ctx): PayrollDetail
    {
        return PayrollDetail::updateOrCreate(
            [
                'payroll_run_id' => $ctx->run->id,
                'employee_id' => $ctx->employee->id,
            ],
            [
                'basic_monthly_rate_centavos' => $ctx->basicMonthlyCentavos,
                'daily_rate_centavos' => $ctx->dailyRateCentavos,
                'hourly_rate_centavos' => $ctx->hourlyRateCentavos,
                'working_days_in_period' => $ctx->workingDaysInPeriod,
                'pay_basis' => $ctx->payBasis,
                'days_worked' => $ctx->daysWorked,
                'days_absent' => $ctx->daysAbsent,
                'days_late_minutes' => $ctx->daysLateMinutes,
                'undertime_minutes' => $ctx->undertimeMinutes,
                'overtime_regular_minutes' => $ctx->overtimeRegularMinutes,
                'overtime_rest_day_minutes' => $ctx->overtimeRestDayMinutes,
                'overtime_holiday_minutes' => $ctx->overtimeHolidayMinutes,
                'night_diff_minutes' => $ctx->nightDiffMinutes,
                'regular_holiday_days' => $ctx->regularHolidayDays,
                'special_holiday_days' => $ctx->specialHolidayDays,
                'leave_days_paid' => $ctx->leaveDaysPaid,
                'leave_days_unpaid' => $ctx->leaveDaysUnpaid,
                'basic_pay_centavos' => $ctx->basicPayCentavos,
                'overtime_pay_centavos' => $ctx->overtimePayCentavos,
                'holiday_pay_centavos' => $ctx->holidayPayCentavos,
                'night_diff_pay_centavos' => $ctx->nightDiffPayCentavos,
                'gross_pay_centavos' => $ctx->grossPayCentavos,
                'sss_ee_centavos' => $ctx->sssEeCentavos,
                'sss_er_centavos' => $ctx->sssErCentavos,
                'philhealth_ee_centavos' => $ctx->philhealthEeCentavos,
                'philhealth_er_centavos' => $ctx->philhealthErCentavos,
                'pagibig_ee_centavos' => $ctx->pagibigEeCentavos,
                'pagibig_er_centavos' => $ctx->pagibigErCentavos,
                'withholding_tax_centavos' => $ctx->withholdingTaxCentavos,
                'loan_deductions_centavos' => $ctx->loanDeductionsCentavos,
                'loan_deduction_detail' => $ctx->loanDeductionDetail,
                'other_deductions_centavos' => $ctx->otherDeductionsCentavos,
                'total_deductions_centavos' => $ctx->totalDeductionsCentavos,
                'net_pay_centavos' => $ctx->netPayCentavos,
                'thirteenth_month_centavos' => $ctx->thirteenthMonthCentavos,
                'thirteenth_month_taxable_centavos' => $ctx->thirteenthMonthTaxableCentavos,
                'is_below_min_wage' => $ctx->isBelowMinWage,
                'has_deferred_deductions' => $ctx->hasDeferredDeductions,
                'deduction_stack_trace' => empty($ctx->deductionTrace) ? null : $ctx->deductionTrace,
                'zero_pay' => $ctx->isZeroPay,
                'computation_error' => $ctx->hasComputationError,
                'ytd_taxable_income_centavos' => $ctx->ytdTaxableIncomeCentavos + $ctx->taxableIncomeCentavos,
                'ytd_tax_withheld_centavos' => $ctx->ytdTaxWithheldCentavos + $ctx->withholdingTaxCentavos,
                'status' => 'computed',
            ],
        );
    }
}
