<?php

declare(strict_types=1);

namespace App\Domains\Leave\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monthly leave accrual engine.
 *
 * Rules:
 *  LV-002: Accruals are credited on the 1st of each month for all active employees.
 *  LV-003: Carry-over at year-end is capped by leave_type.max_carry_over_days.
 *  LV-007: SIL monetization is a separate step (SilMonetizationService).
 *
 * Intended to be called by a scheduled command on the 1st of each month.
 */
final class LeaveAccrualService implements ServiceContract
{
    /**
     * Accrue leave for ALL active employees for the given month/year.
     * Skips leave types with null monthly_accrual_days (lump-sum types handled at year open).
     *
     * @return array{processed: int, skipped: int}
     */
    public function accrueMonthlyForAll(int $year, int $month): array
    {
        $processedCount = 0;
        $skippedCount = 0;

        $accrualTypes = LeaveType::where('is_active', true)
            ->whereNotNull('monthly_accrual_days')
            ->where('monthly_accrual_days', '>', 0)
            ->get();

        if ($accrualTypes->isEmpty()) {
            return ['processed' => 0, 'skipped' => 0];
        }

        Employee::where('is_active', true)
            ->where('employment_status', 'active')
            ->chunk(200, function ($employees) use ($accrualTypes, $year, &$processedCount, &$skippedCount): void {
                foreach ($employees as $employee) {
                    foreach ($accrualTypes as $leaveType) {
                        try {
                            $this->accrueForEmployee($employee, $leaveType, $year);
                            $processedCount++;
                        } catch (\Throwable $e) {
                            Log::warning('Leave accrual failed', [
                                'employee_id' => $employee->id,
                                'leave_type_id' => $leaveType->id,
                                'error' => $e->getMessage(),
                            ]);
                            $skippedCount++;
                        }
                    }
                }
            });

        return ['processed' => $processedCount, 'skipped' => $skippedCount];
    }

    /**
     * Accrue leave for a single employee + leave type for a given year.
     *
     * M5 FIX: Prorates the first accrual for mid-year hires. An employee
     * hired on June 15 should not receive the full annual allocation --
     * they should only accrue from their hire month onward. Without this,
     * the company overpays leave liability for new hires.
     */
    public function accrueForEmployee(Employee $employee, LeaveType $leaveType, int $year): void
    {
        if ($leaveType->monthly_accrual_days === null || $leaveType->monthly_accrual_days <= 0) {
            return;
        }

        DB::transaction(function () use ($employee, $leaveType, $year): void {
            $balance = LeaveBalance::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
                ['opening_balance' => 0.0, 'accrued' => 0.0, 'adjusted' => 0.0, 'used' => 0.0, 'monetized' => 0.0],
            );

            // M5 FIX: Prorate accrual for mid-year hires.
            // If the employee was hired in the accrual year and this is their
            // first accrual, only credit from hire month onward.
            $accrualAmount = (float) $leaveType->monthly_accrual_days;

            if ($employee->date_hired && $balance->accrued == 0.0) {
                $hireDate = \Carbon\Carbon::parse((string) $employee->date_hired);
                $hireYear = (int) $hireDate->year;

                if ($hireYear === $year) {
                    // Employee hired mid-year: calculate remaining months in the year
                    $hireMonth = (int) $hireDate->month;
                    $currentMonth = (int) now()->month;

                    // Only accrue if hire month is in the current year
                    // Skip accrual for months before hire
                    if ($currentMonth < $hireMonth) {
                        return; // Don't accrue before hire date
                    }

                    // For the first accrual after hire, prorate based on remaining months
                    $remainingMonths = 12 - $hireMonth + 1; // months from hire through December
                    $proratedTotal = $accrualAmount * $remainingMonths;

                    // Only credit the prorated portion on first accrual
                    $balance->accrued = $proratedTotal;
                    $balance->save();

                    Log::info('M5-PRORATE: Leave accrual prorated for mid-year hire', [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'hire_month' => $hireMonth,
                        'remaining_months' => $remainingMonths,
                        'prorated_total' => $proratedTotal,
                    ]);

                    return;
                }
            }

            $balance->accrued += $accrualAmount;
            $balance->save();
        });
    }

    /**
     * Year-end carry-over processing.
     * Caps accrued balance to max_carry_over_days and opens new-year balances.
     */
    public function processYearEndCarryOver(int $closingYear): void
    {
        $openingYear = $closingYear + 1;

        $accrualTypes = LeaveType::where('is_active', true)->get();

        Employee::where('is_active', true)
            ->chunk(200, function ($employees) use ($accrualTypes, $closingYear, $openingYear): void {
                foreach ($employees as $employee) {
                    foreach ($accrualTypes as $leaveType) {
                        $closingBalance = LeaveBalance::where([
                            'employee_id' => $employee->id,
                            'leave_type_id' => $leaveType->id,
                            'year' => $closingYear,
                        ])->first();

                        if ($closingBalance === null) {
                            continue;
                        }

                        $carryOver = min(
                            max(0.0, $closingBalance->balance),
                            (float) $leaveType->max_carry_over_days,
                        );

                        DB::transaction(function () use ($employee, $leaveType, $openingYear, $carryOver): void {
                            LeaveBalance::firstOrCreate(
                                ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $openingYear],
                                [
                                    'opening_balance' => $carryOver,
                                    'accrued' => 0.0,
                                    'adjusted' => 0.0,
                                    'used' => 0.0,
                                    'monetized' => 0.0,
                                ],
                            );
                        });
                    }
                }
            });
    }
}
