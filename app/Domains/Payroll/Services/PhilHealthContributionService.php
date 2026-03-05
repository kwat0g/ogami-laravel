<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * PhilHealth Contribution Service.
 *
 * Source table: philhealth_premium_tables (PhilHealth Circular 2023-0009)
 *
 * PHL-001: Effective 2024 — 5% of basic monthly salary.
 * PHL-002: Base = basic_salary ONLY (not gross, not allowances).
 * PHL-003: EE share = total premium / 2 | ER share = total premium / 2.
 * PHL-004: Semi-monthly deduction = employee_premium / 2 (deduct each cutoff).
 * PHL-005: Minimum total monthly premium = ₱500 → per cutoff = ₱250.
 * PHL-006: Maximum total monthly premium = ₱5,000 → per cutoff = ₱2,500.
 *
 * All inputs/outputs are integer centavos.
 */
final class PhilHealthContributionService implements ServiceContract
{
    /**
     * Compute the employee PhilHealth contribution per SEMI-MONTHLY period.
     * This amount is deducted on EACH cut-off (1st and 2nd).
     *
     * @param  int  $basicMonthlyCentavos  Basic monthly salary in centavos
     * @return int Per-period employee share in centavos
     */
    public function computeEmployeeSharePerPeriod(int $basicMonthlyCentavos): int
    {
        $row = DB::table('philhealth_premium_tables')
            ->orderByDesc('effective_date')
            ->first();

        if ($row === null) {
            return 0;
        }

        $basicMonthlyPesos = $basicMonthlyCentavos / 100;
        $rate = (float) $row->premium_rate;
        $minPremium = (float) $row->min_monthly_premium;
        $maxPremium = (float) $row->max_monthly_premium;

        $totalMonthlyPremium = $basicMonthlyPesos * $rate;
        $totalMonthlyPremium = max($minPremium, min($maxPremium, $totalMonthlyPremium));

        $employeeMonthlyShare = $totalMonthlyPremium / 2; // EE = 50% of total
        $employeePerPeriod = $employeeMonthlyShare / 2; // semi-monthly

        return (int) round($employeePerPeriod * 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Compute the employer PhilHealth share per SEMI-MONTHLY period.
     * ER = 50% of total monthly premium, then halved for semi-monthly deduction.
     * Equals EE share per period (50/50 split per PHL-003).
     *
     * @param  int  $basicMonthlyCentavos  Basic monthly salary in centavos
     * @return int Per-period employer share in centavos
     */
    public function computeEmployerSharePerPeriod(int $basicMonthlyCentavos): int
    {
        // ER per period = EE per period (symmetric 50/50 — PHL-003)
        return $this->computeEmployeeSharePerPeriod($basicMonthlyCentavos);
    }

    /**
     * Compute the total monthly premium for reporting.
     */
    public function computeTotalMonthlyPremium(int $basicMonthlyCentavos): int
    {
        $row = DB::table('philhealth_premium_tables')
            ->orderByDesc('effective_date')
            ->first();

        if ($row === null) {
            return 0;
        }

        $basicMonthlyPesos = $basicMonthlyCentavos / 100;
        $rate = (float) $row->premium_rate;
        $minPremium = (float) $row->min_monthly_premium;
        $maxPremium = (float) $row->max_monthly_premium;

        $total = max($minPremium, min($maxPremium, $basicMonthlyPesos * $rate));

        return (int) round($total * 100, 0, PHP_ROUND_HALF_UP);
    }
}
