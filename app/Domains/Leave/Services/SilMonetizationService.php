<?php

declare(strict_types=1);

namespace App\Domains\Leave\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Payroll\Models\PayrollAdjustment;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * SIL (Service Incentive Leave) monetization service.
 *
 * Business rules:
 *  LV-009: SIL cash conversion at year-end:
 *          cash_value = (unused_sil_days / 26) × monthly_basic_salary
 *          The divisor 26 is configurable; default = 26.
 *          Conversion is irreversible once processed.
 *  LV-011: On separation, any remaining VL or SIL balance is also converted
 *          using the same formula (entry point: monetizeForEmployee).
 *
 * NOTE: Only leave types marked `can_be_monetized = true` are processed.
 *       Currently SIL. The formula follows DOLE standard: 26 working days = 1 month.
 */
final class SilMonetizationService implements ServiceContract
{
    /**
     * Monetize all SIL (and other monetizable) balances for all active employees
     * at year-end.  Idempotent: already-monetized balances are skipped.
     *
     * @param  int|null  $payrollRunId  When provided, a PayrollAdjustment earmarks the payout per employee.
     * @param  int|null  $createdBy     User ID recording the adjustments (required when $payrollRunId is set).
     * @return array<string, mixed> Summary: { processed: int, total_cash_centavos: int, employees: list<int> }
     */
    public function monetizeAllForYear(int $year, ?int $payrollRunId = null, ?int $createdBy = null): array
    {
        $monetizableTypes = LeaveType::where('can_be_monetized', true)
            ->where('is_active', true)
            ->get();

        if ($monetizableTypes->isEmpty()) {
            return ['processed' => 0, 'total_cash_centavos' => 0, 'employees' => []];
        }

        $results = [];

        foreach ($monetizableTypes as $leaveType) {
            $balances = LeaveBalance::with('employee')
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->where('balance', '>', 0)   // skip zero balances
                ->get();

            foreach ($balances as $balance) {
                /** @var LeaveBalance $balance */
                if ($balance->balance <= 0) {
                    continue;
                }

                $cashCentavos = $this->computeCashValue(
                    $balance->balance,
                    $balance->employee->basic_monthly_rate,
                );

                $results[] = [
                    'employee_id' => $balance->employee_id,
                    'leave_type_id' => $leaveType->id,
                    'days_monetized' => $balance->balance,
                    'cash_centavos' => $cashCentavos,
                ];
            }
        }

        // Apply monetizations in a single transaction
        DB::transaction(function () use ($results, $year, $payrollRunId, $createdBy): void {
            foreach ($results as $row) {
                LeaveBalance::where('employee_id', $row['employee_id'])
                    ->where('leave_type_id', $row['leave_type_id'])
                    ->where('year', $year)
                    ->increment('monetized', $row['days_monetized']);

                // LV-B2: Create a PayrollAdjustment so the cash payout flows through GL.
                if ($payrollRunId !== null && $createdBy !== null) {
                    PayrollAdjustment::create([
                        'payroll_run_id'   => $payrollRunId,
                        'employee_id'      => $row['employee_id'],
                        'type'             => 'earning',
                        'nature'           => 'non_taxable',
                        'description'      => 'SIL monetization — year-end cash conversion',
                        'amount_centavos'  => $row['cash_centavos'],
                        'created_by'       => $createdBy,
                    ]);
                }
            }
        });

        $totalCash = array_sum(array_column($results, 'cash_centavos'));
        $employeeIds = array_unique(array_column($results, 'employee_id'));

        return [
            'processed' => count($results),
            'total_cash_centavos' => (int) $totalCash,
            'employees' => array_values($employeeIds),
            'detail' => $results,
        ];
    }

    /**
     * Monetize remaining monetizable leave balances for a single employee.
     * Used for year-end processing and upon separation (LV-011).
     *
     * @param  int  $year  The leave year to monetize (defaults to current year)
     * @param  int|null  $payrollRunId  When provided, a PayrollAdjustment is created for each balance converted.
     * @param  int|null  $createdBy     User ID recording the adjustment (required when $payrollRunId is set).
     * @return list<array<string, mixed>> { leave_type_id, days_monetized, cash_centavos }[]
     */
    public function monetizeForEmployee(Employee $employee, int $year = 0, ?int $payrollRunId = null, ?int $createdBy = null): array
    {
        if ($year === 0) {
            $year = (int) date('Y');
        }

        $monetizableTypes = LeaveType::where('can_be_monetized', true)
            ->where('is_active', true)
            ->get();

        if ($monetizableTypes->isEmpty()) {
            return [];
        }

        /** @var list<array<string, mixed>> $processed */
        $processed = [];

        DB::transaction(function () use ($employee, $year, $monetizableTypes, $payrollRunId, $createdBy, &$processed): void {
            foreach ($monetizableTypes as $leaveType) {
                $balance = LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if ($balance === null || $balance->balance <= 0) {
                    continue;
                }

                $daysToConvert = $balance->balance;
                $cashCentavos = $this->computeCashValue(
                    $daysToConvert,
                    $employee->basic_monthly_rate,
                );

                // LV-009: Monetization is irreversible
                $balance->monetized += $daysToConvert;
                $balance->save();

                // LV-B2: Create a PayrollAdjustment so the cash payout flows through GL.
                if ($payrollRunId !== null && $createdBy !== null) {
                    PayrollAdjustment::create([
                        'payroll_run_id'   => $payrollRunId,
                        'employee_id'      => $employee->id,
                        'type'             => 'earning',
                        'nature'           => 'non_taxable',
                        'description'      => "SIL monetization — {$leaveType->code} cash conversion",
                        'amount_centavos'  => $cashCentavos,
                        'created_by'       => $createdBy,
                    ]);
                }

                $processed[] = [
                    'leave_type_id'   => $leaveType->id,
                    'leave_type_code' => $leaveType->code,
                    'days_monetized'  => $daysToConvert,
                    'cash_centavos'   => $cashCentavos,
                ];
            }
        });

        return $processed;
    }

    /**
     * Compute the cash value of leave days.
     *
     * LV-009 formula: cash = (days / 26) × monthly_basic_salary
     * The divisor 26 represents 26 working days per month (DOLE standard).
     * This is configurable; pass a custom divisor if the system setting differs.
     *
     * @param  float  $days  Number of leave days to convert
     * @param  int  $monthlyBasicRateCentavos  Employee's basic_monthly_rate in centavos
     * @param  int  $divisor  Working days per month (default: 26 per DOLE)
     * @return int Cash value in centavos
     */
    public function computeCashValue(float $days, int $monthlyBasicRateCentavos, int $divisor = 26): int
    {
        if ($days <= 0 || $divisor <= 0) {
            return 0;
        }

        // Formula: (days / divisor) * monthly_rate
        // Keep precision — round to centavos at the end using ROUND_HALF_UP (DED-003)
        $cashCentavos = ($days / $divisor) * $monthlyBasicRateCentavos;

        return (int) round($cashCentavos, 0, PHP_ROUND_HALF_UP);
    }
}
