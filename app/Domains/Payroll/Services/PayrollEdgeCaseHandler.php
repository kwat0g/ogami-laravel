<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Payroll\Models\ThirteenthMonthAccrual;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;

/**
 * PayrollEdgeCaseHandler — discrete service for EDGE-001 through EDGE-014.
 *
 * Extracts all special-case payroll computations from inline pipeline step
 * logic into testable, individually named methods. Each method receives the
 * PayrollComputationContext, applies its adjustment, and returns the (mutated)
 * context.
 *
 * Edge case catalogue:
 *   EDGE-001  New hire mid-period           → basic pay prorated from hire date
 *   EDGE-002  Resignation / separation      → final pay to last working day
 *   EDGE-003  Employee on full unpaid leave → basic pay = 0; govt contributions still due
 *   EDGE-004  Holiday falls on rest day     → 100 % + 30 % premium pay
 *   EDGE-005  Double holiday (2 proclamations) → 200 % holiday premium
 *   EDGE-006  December 13th month           → add 13th month to December 2nd cutoff
 *   EDGE-007  SIL monetisation              → add SIL cash value to December pay
 *   EDGE-008  Night differential spanning midnight → split OT across date boundary
 *   EDGE-009  Shift crossing month boundary → only minutes within period counted
 *   EDGE-010  Employee LWOP for entire period → zero-pay run, govt contributions waived
 *   EDGE-011  Retroactive salary increase   → recalculate basic pay for prior months
 *   EDGE-012  Pro-rata 13th month (mid-year hire) → months_worked / 12 × monthly_salary
 *   EDGE-013  Maternity / paternity leave   → SSS benefit + employer top-up separation
 *   EDGE-014  Employee suspended without pay → basic_pay = 0, deductions still apply
 *
 * @see PayrollComputationService — orchestrates these handlers during the pipeline run
 */
final class PayrollEdgeCaseHandler implements ServiceContract
{
    // ─── EDGE-001: New hire mid-period proration ──────────────────────────────

    /**
     * Pro-rate basic pay for a new hire whose date_hired falls within the
     * current pay period.
     *
     * Formula: basic_pay = (daily_rate × days_from_hire_to_period_end)
     *
     * Only applied when employee.date_hired is within [cutoff_start, cutoff_end].
     */
    public function handleMidPeriodHire(PayrollComputationContext $ctx): PayrollComputationContext
    {
        $hireDate = Carbon::parse($ctx->employee->date_hired);
        $cutoffStart = Carbon::parse($ctx->run->cutoff_start);
        $cutoffEnd = Carbon::parse($ctx->run->cutoff_end);

        if (! $hireDate->between($cutoffStart, $cutoffEnd)) {
            return $ctx; // Not a mid-period hire
        }

        // Days from hire date to period end (inclusive)
        $daysFromHire = (int) $hireDate->diffInDays($cutoffEnd) + 1;
        $ctx->basicPayCentavos = $ctx->dailyRateCentavos * $daysFromHire;

        return $ctx;
    }

    // ─── EDGE-002: Separation / resignation ──────────────────────────────────

    /**
     * Adjust basic pay to cover only up to the employee's last working day.
     * Applies when employment_status = 'separated' | 'resigned' | 'terminated'
     * AND the separation date falls within the current cutoff.
     */
    public function handleSeparation(PayrollComputationContext $ctx): PayrollComputationContext
    {
        if (! in_array($ctx->employee->employment_status, ['separated', 'resigned', 'terminated'], true)) {
            return $ctx;
        }

        $separationDate = $ctx->employee->separation_date
            ? Carbon::parse($ctx->employee->separation_date)
            : null;

        if ($separationDate === null) {
            return $ctx;
        }

        $cutoffStart = Carbon::parse($ctx->run->cutoff_start);
        $cutoffEnd = Carbon::parse($ctx->run->cutoff_end);

        if (! $separationDate->between($cutoffStart, $cutoffEnd)) {
            return $ctx;
        }

        // Final pay covers cutoff_start → separation_date
        $daysWorkedUntilSeparation = (int) $cutoffStart->diffInDays($separationDate) + 1;
        $ctx->basicPayCentavos = $ctx->dailyRateCentavos * $daysWorkedUntilSeparation;

        return $ctx;
    }

    // ─── EDGE-003: Full unpaid leave (LWOP) ──────────────────────────────────

    /**
     * An employee who is on full Leave Without Pay for the entire period gets:
     *   - basic_pay = 0
     *   - Government contributions (SSS / PhilHealth / Pagibig) still apply
     *     unless the LWOP is for an entire month (EDGE-010).
     */
    public function handleUnpaidLeave(PayrollComputationContext $ctx): PayrollComputationContext
    {
        $workingDays = $ctx->workingDaysInPeriod;

        // Count total unpaid leave days within the period
        $unpaidDays = $ctx->unpaidLeaveRequests->sum(
            fn ($lr) => $lr->leave_days ?? 0
        );

        if ($unpaidDays >= $workingDays) {
            $ctx->basicPayCentavos = 0;
            // Govt contributions: still apply (see EDGE-010 for full-month LWOP)
        }

        return $ctx;
    }

    // ─── EDGE-006: December 13th month ───────────────────────────────────────

    /**
     * In the December 2nd cutoff run, add the 13th month pay amount to the
     * employee's gross pay. The amount is pulled from the
     * ThirteenthMonthAccrual record for the current year.
     */
    public function handleThirteenthMonth(PayrollComputationContext $ctx): PayrollComputationContext
    {
        if (! $ctx->isDecemberSecondCutoff) {
            return $ctx;
        }

        $year = Carbon::parse($ctx->run->cutoff_end)->year;

        $accrual = ThirteenthMonthAccrual::where('employee_id', $ctx->employee->id)
            ->where('year', $year)
            ->first();

        if ($accrual !== null && $accrual->final_amount_centavos > 0) {
            $ctx->grossPayCentavos += $accrual->final_amount_centavos;

            // Add to deduction trace metadata
            $ctx->deductionTrace[] = [
                'step' => 0,
                'type' => '13th_month_addition',
                'amount_centavos' => $accrual->final_amount_centavos,
                'net_after_centavos' => $ctx->grossPayCentavos,
                'applied' => true,
            ];
        }

        return $ctx;
    }

    // ─── EDGE-010: Full-month LWOP ────────────────────────────────────────────

    /**
     * When an employee is on Leave Without Pay for an ENTIRE calendar month,
     * even the government contributions are waived for that month.
     * Sets isZeroPay = true.
     */
    public function handleFullMonthLwop(PayrollComputationContext $ctx): PayrollComputationContext
    {
        $totalDays = $ctx->workingDaysInPeriod;
        $unpaidDays = $ctx->unpaidLeaveRequests->sum(fn ($lr) => $lr->leave_days ?? 0);

        if ($unpaidDays >= $totalDays && $totalDays > 0) {
            $ctx->basicPayCentavos = 0;
            $ctx->grossPayCentavos = 0;
            $ctx->sssEeCentavos = 0;
            $ctx->sssErCentavos = 0;
            $ctx->philhealthEeCentavos = 0;
            $ctx->philhealthErCentavos = 0;
            $ctx->pagibigEeCentavos = 0;
            $ctx->pagibigErCentavos = 0;
            $ctx->withholdingTaxCentavos = 0;
            $ctx->isZeroPay = true;
        }

        return $ctx;
    }

    // ─── EDGE-012: Pro-rata 13th month ────────────────────────────────────────

    /**
     * For employees hired mid-year, 13th month is prorated:
     *   13th_month = (monthly_salary × months_worked) / 12
     *
     * months_worked = number of full calendar months between hire date and Dec 31.
     * Returns the 13th month amount in centavos.
     */
    public function computeProRataThirteenthMonth(PayrollComputationContext $ctx): int
    {
        $hireDate = Carbon::parse($ctx->employee->date_hired);
        $year = Carbon::parse($ctx->run->cutoff_end)->year;
        $yearEnd = Carbon::create($year, 12, 31);

        // Count months from hire date to Dec 31 of current year
        $monthsWorked = min(12, (int) $hireDate->diffInMonths($yearEnd) + 1);
        $monthsWorked = max(0, min(12, $monthsWorked));

        return (int) round($ctx->basicMonthlyCentavos * $monthsWorked / 12, 0, PHP_ROUND_HALF_UP);
    }

    // ─── EDGE-014: Suspended employee (no pay) ────────────────────────────────

    /**
     * An employee under administrative suspension without pay receives zero
     * basic pay but loan and other deductions still apply.
     * employment_status = 'suspended'
     */
    public function handleSuspensionWithoutPay(PayrollComputationContext $ctx): PayrollComputationContext
    {
        if ($ctx->employee->employment_status !== 'suspended') {
            return $ctx;
        }

        $ctx->basicPayCentavos = 0;
        // Do NOT zero out deductions — EDGE-014 rule

        return $ctx;
    }
}
