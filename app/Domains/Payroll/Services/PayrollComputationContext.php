<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Loan\Models\Loan;
use App\Domains\Payroll\Models\PayrollAdjustment;
use App\Domains\Payroll\Models\PayrollRun;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;

/**
 * Mutable computation context — carries all inputs and accumulates results
 * as the 17-step pipeline processes an employee's payroll for one period.
 *
 * Inputs  → set in constructor / by the PayrollComputationService loader.
 * Outputs → set by each pipeline step. Consumed by PayrollComputationService
 *           to create a PayrollDetail record.
 */
final class PayrollComputationContext implements ServiceContract
{
    // ─── Inputs (set before pipeline runs) ────────────────────────────────────

    public readonly Employee $employee;

    public readonly PayrollRun $run;

    /** Working days in this pay period (calculated as actual Mon-Fri days in cutoff). */
    public int $workingDaysInPeriod = 0;

    /** True if this is the 2nd cut-off of the month (SSS collected on 2nd). */
    public bool $isSecondCutoff = false;

    /** True if this is a December 2nd cut-off (13th month + tax reconciliation). */
    public bool $isDecemberSecondCutoff = false;

    /** @var Collection<int, AttendanceLog> */
    public Collection $attendanceLogs;

    /** @var Collection<int, LeaveRequest> Approved paid leave within the period. */
    public Collection $paidLeaveRequests;

    /** @var Collection<int, LeaveRequest> Approved unpaid leave within the period. */
    public Collection $unpaidLeaveRequests;

    /** @var Collection<int, Loan> Active loans with debit instalment due. */
    public Collection $activeLoans;

    /** @var Collection<int, PayrollAdjustment> Adjustments for this employee this run. */
    public Collection $adjustments;

    /** YTD taxable income before this period (from previous PayrollDetail rows). */
    public int $ytdTaxableIncomeCentavos = 0;

    /** YTD withholding tax before this period. */
    public int $ytdTaxWithheldCentavos = 0;

    // ─── Snapshots (captured from employee at pipeline start) ─────────────────

    public int $basicMonthlyCentavos = 0;

    public int $dailyRateCentavos = 0;

    public int $hourlyRateCentavos = 0;

    public string $payBasis = 'monthly';

    public bool $isMinimumWageEarner = false;

    // ─── Attendance summary (Step 1 output) ───────────────────────────────────

    public int $daysWorked = 0;

    public int $daysAbsent = 0;

    public int $daysLateMinutes = 0;   // total tardiness minutes

    public int $undertimeMinutes = 0;

    public int $overtimeRegularMinutes = 0;

    public int $overtimeRestDayMinutes = 0;

    public int $overtimeHolidayMinutes = 0;

    public int $nightDiffMinutes = 0;

    public int $regularHolidayDays = 0;

    public int $specialHolidayDays = 0;

    public int $leaveDaysPaid = 0;

    public int $leaveDaysUnpaid = 0;

    // ─── Earnings (Steps 2–6 output, centavos) ────────────────────────────────

    public int $basicPayCentavos = 0;

    /** Late/undertime deduction applied against basic pay (GAP-2). */
    public int $lateDeductionCentavos = 0;

    public int $undertimeDeductionCentavos = 0;

    public int $overtimePayCentavos = 0;

    public int $holidayPayCentavos = 0;

    public int $nightDiffPayCentavos = 0;

    public int $grossPayCentavos = 0;

    // ─── Government deductions (Steps 7–9, centavos) ─────────────────────────

    public int $sssEeCentavos = 0;

    public int $sssErCentavos = 0;   // employer SSS share

    public int $philhealthEeCentavos = 0;

    public int $philhealthErCentavos = 0;   // employer PhilHealth share

    public int $pagibigEeCentavos = 0;

    public int $pagibigErCentavos = 0;   // employer Pag-IBIG share

    public int $taxableIncomeCentavos = 0;   // gross - gov contributions

    public int $withholdingTaxCentavos = 0;

    // ─── Loan deductions (Step 10, centavos) ──────────────────────────────────

    public int $loanDeductionsCentavos = 0;

    /** @var list<array{loan_id: int, amount_centavos: int}> */
    public array $loanDeductionDetail = [];

    // ─── Other deductions & adjustments (Steps 11–12) ─────────────────────────

    public int $otherDeductionsCentavos = 0;

    public int $totalDeductionsCentavos = 0;

    // ─── Net pay (Steps 13–17) ────────────────────────────────────────────────

    public int $netPayCentavos = 0;

    public bool $isBelowMinWage = false;

    public bool $hasDeferredDeductions = false;

    // ─── 13th Month Pay (Step 18) ─────────────────────────────────────────────

    public int $thirteenthMonthCentavos = 0;

    public int $thirteenthMonthTaxableCentavos = 0;

    // ─── DED-004: per-step deduction audit trace ──────────────────────────────
    /** @var list<array<string, mixed>> Assembled by Step17; written to deduction_stack_trace. */
    public array $deductionTrace = [];

    // ─── EDGE-003/010 & DED-002 flags ────────────────────────────────────────
    /** True when net_pay = 0 due to zero attendance or full LWOP (EDGE-003/010). */
    public bool $isZeroPay = false;

    /** True when a recoverable exception was caught during pipeline execution (DED-002). */
    public bool $hasComputationError = false;

    // ─── Constructor ──────────────────────────────────────────────────────────

    public function __construct(Employee $employee, PayrollRun $run)
    {
        $this->employee = $employee;
        $this->run = $run;
        $this->attendanceLogs = collect();
        $this->paidLeaveRequests = collect();
        $this->unpaidLeaveRequests = collect();
        $this->activeLoans = collect();
        $this->adjustments = collect();
    }
}
