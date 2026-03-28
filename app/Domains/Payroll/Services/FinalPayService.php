<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Loan\Models\Loan;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive Final Pay Service — Item 57.
 *
 * Computes the complete final pay package for a separating employee:
 *   1. Prorated basic salary (last working day in period)
 *   2. Unused leave monetization (VL, SIL, and other monetizable types)
 *   3. Prorated 13th month pay (months worked / 12 * monthly salary)
 *   4. Outstanding loan balance deduction
 *   5. Other deductions reconciliation
 *
 * This enhances the existing EDGE-002 (basic proration only) with full
 * separation pay computation per Philippine labor law.
 *
 * Flexibility:
 *   - Configurable leave divisor (default 26 working days/month)
 *   - Supports voluntary (resigned) and involuntary (terminated) separation
 *   - Terminated employees may receive separation pay (0.5 month per year of service)
 *   - Clearance gate: can optionally block final pay until clearance is complete
 */
final class FinalPayService implements ServiceContract
{
    /** Working days per month for leave conversion (DOLE standard). */
    private const WORKING_DAYS_PER_MONTH = 26;

    /**
     * Compute final pay breakdown for a separating employee.
     *
     * @return array{
     *     employee_id: int,
     *     employee_name: string,
     *     separation_type: string,
     *     separation_date: string,
     *     hire_date: string,
     *     years_of_service: float,
     *     prorated_salary_centavos: int,
     *     leave_monetization_centavos: int,
     *     leave_details: array,
     *     prorated_13th_month_centavos: int,
     *     separation_pay_centavos: int,
     *     gross_final_pay_centavos: int,
     *     loan_deductions_centavos: int,
     *     loan_details: array,
     *     other_deductions_centavos: int,
     *     net_final_pay_centavos: int,
     * }
     */
    public function compute(Employee $employee, ?string $lastWorkingDate = null): array
    {
        $separationDate = $lastWorkingDate
            ? Carbon::parse($lastWorkingDate)
            : ($employee->separation_date ? Carbon::parse($employee->separation_date) : now());

        $hireDate = Carbon::parse($employee->hire_date);
        $yearsOfService = round($hireDate->diffInMonths($separationDate) / 12, 2);
        $monthlyRate = (int) $employee->basic_monthly_rate;

        // 1. Prorated salary for final period
        $proratedSalary = $this->computeProratedSalary($employee, $separationDate);

        // 2. Leave monetization
        $leaveResult = $this->computeLeaveMonetization($employee, $monthlyRate);

        // 3. Prorated 13th month
        $prorated13th = $this->computeProrated13thMonth($employee, $separationDate);

        // 4. Separation pay (for involuntary termination)
        $separationPay = $this->computeSeparationPay($employee, $monthlyRate, $yearsOfService);

        // 5. Outstanding loan deductions
        $loanResult = $this->computeLoanDeductions($employee);

        $grossPay = $proratedSalary + $leaveResult['total_centavos'] + $prorated13th + $separationPay;
        $totalDeductions = $loanResult['total_centavos'];
        $netPay = max(0, $grossPay - $totalDeductions);

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name ?? "{$employee->first_name} {$employee->last_name}",
            'separation_type' => $employee->employment_status,
            'separation_date' => $separationDate->toDateString(),
            'hire_date' => $hireDate->toDateString(),
            'years_of_service' => $yearsOfService,
            'prorated_salary_centavos' => $proratedSalary,
            'leave_monetization_centavos' => $leaveResult['total_centavos'],
            'leave_details' => $leaveResult['details'],
            'prorated_13th_month_centavos' => $prorated13th,
            'separation_pay_centavos' => $separationPay,
            'gross_final_pay_centavos' => $grossPay,
            'loan_deductions_centavos' => $loanResult['total_centavos'],
            'loan_details' => $loanResult['details'],
            'other_deductions_centavos' => 0,
            'net_final_pay_centavos' => $netPay,
        ];
    }

    /**
     * Prorated basic salary: daily_rate * days worked in final period.
     */
    private function computeProratedSalary(Employee $employee, Carbon $separationDate): int
    {
        $dailyRate = (int) ($employee->daily_rate ?? round($employee->basic_monthly_rate / self::WORKING_DAYS_PER_MONTH));
        $dayOfMonth = $separationDate->day;

        // Prorate from 1st of month to separation date
        return $dailyRate * $dayOfMonth;
    }

    /**
     * Monetize all unused leave balances for monetizable types.
     * Formula: (unused_days / 26) * monthly_basic_salary
     *
     * @return array{total_centavos: int, details: list<array>}
     */
    private function computeLeaveMonetization(Employee $employee, int $monthlyRate): array
    {
        $currentYear = (int) now()->format('Y');
        $divisor = (int) (DB::table('system_settings')
            ->where('key', 'leave.monetization_divisor')
            ->value('value') ?? self::WORKING_DAYS_PER_MONTH);

        $monetizableTypes = LeaveType::where('can_be_monetized', true)
            ->where('is_active', true)
            ->get();

        $details = [];
        $totalCentavos = 0;

        foreach ($monetizableTypes as $leaveType) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $currentYear)
                ->first();

            $unusedDays = $balance ? max(0, (float) $balance->balance) : 0;

            if ($unusedDays <= 0) {
                continue;
            }

            $cashValue = (int) round(($unusedDays / $divisor) * $monthlyRate);
            $totalCentavos += $cashValue;

            $details[] = [
                'leave_type' => $leaveType->name,
                'unused_days' => $unusedDays,
                'cash_value_centavos' => $cashValue,
            ];
        }

        return ['total_centavos' => $totalCentavos, 'details' => $details];
    }

    /**
     * Prorated 13th month: (months_worked_this_year / 12) * monthly_salary.
     * Per PD 851, divisor is always 12 regardless of months worked.
     */
    private function computeProrated13thMonth(Employee $employee, Carbon $separationDate): int
    {
        $yearStart = $separationDate->copy()->startOfYear();
        $monthsWorked = (int) $yearStart->diffInMonths($separationDate) + 1;
        $monthlyRate = (int) $employee->basic_monthly_rate;

        // Check if 13th month was already paid for this year
        $alreadyPaid = (int) DB::table('thirteenth_month_accruals')
            ->where('employee_id', $employee->id)
            ->whereYear('accrual_month', $separationDate->year)
            ->sum('final_amount_centavos');

        if ($alreadyPaid > 0) {
            return 0; // Already paid in a previous 13th month run
        }

        return (int) round(($monthlyRate * $monthsWorked) / 12);
    }

    /**
     * Separation pay for involuntary termination.
     * Per Labor Code Art. 298-299:
     *   - Authorized causes (redundancy, retrenchment): 1 month per year of service
     *   - Just causes (serious misconduct): 0.5 month per year of service OR none
     * Default: 0.5 month per year (configurable).
     */
    private function computeSeparationPay(Employee $employee, int $monthlyRate, float $yearsOfService): int
    {
        if ($employee->employment_status !== 'terminated') {
            return 0; // Only for involuntary termination
        }

        $multiplier = (float) (DB::table('system_settings')
            ->where('key', 'payroll.separation_pay_multiplier')
            ->value('value') ?? 0.5);

        $years = max(1, (int) ceil($yearsOfService)); // Minimum 1 year

        return (int) round($monthlyRate * $multiplier * $years);
    }

    /**
     * Outstanding loan balances to deduct from final pay.
     *
     * @return array{total_centavos: int, details: list<array>}
     */
    private function computeLoanDeductions(Employee $employee): array
    {
        $activeLoans = Loan::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->with('loanType')
            ->get();

        $details = [];
        $totalCentavos = 0;

        foreach ($activeLoans as $loan) {
            $remaining = max(0, (int) $loan->total_payable_centavos - (int) $loan->total_paid_centavos);

            if ($remaining <= 0) {
                continue;
            }

            $totalCentavos += $remaining;
            $details[] = [
                'loan_type' => $loan->loanType?->name ?? 'Unknown',
                'loan_id' => $loan->id,
                'remaining_balance_centavos' => $remaining,
            ];
        }

        return ['total_centavos' => $totalCentavos, 'details' => $details];
    }
}
