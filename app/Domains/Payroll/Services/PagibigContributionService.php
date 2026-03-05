<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * Pag-IBIG (HDMF) Contribution Service.
 *
 * Source table: pagibig_contribution_tables (HDMF Circular 274, RA 9679)
 *
 * PAGIBIG-001: Applies to all employees.
 * PAGIBIG-002: EE rate = 1% if basic ≤ ₱1,500; 2% if above.
 * PAGIBIG-003: EE cap = ₱100/month → ₱50 per semi-monthly period.
 * PAGIBIG-004: ER rate = always 2%, no cap.
 * Semi-monthly: deduct ₱50 (or computed share if lower) each cutoff.
 *
 * All inputs/outputs are integer centavos.
 */
final class PagibigContributionService implements ServiceContract
{
    /**
     * Compute the employee Pag-IBIG contribution per SEMI-MONTHLY period.
     *
     * @param  int  $basicMonthlyCentavos  Basic monthly salary in centavos
     * @return int Per-period employee share in centavos
     */
    public function computeEmployeeSharePerPeriod(int $basicMonthlyCentavos): int
    {
        $row = DB::table('pagibig_contribution_tables')
            ->orderByDesc('effective_date')
            ->first();

        if ($row === null) {
            // Fallback: ₱100/month → ₱50/period
            return 5000;
        }

        $basicMonthlyPesos = $basicMonthlyCentavos / 100;
        $threshold = (float) $row->salary_threshold;
        $rateBelow = (float) $row->employee_rate_below;
        $rateAbove = (float) $row->employee_rate_above;
        $monthlyCapPesos = (float) $row->employee_cap_monthly;

        $rate = $basicMonthlyPesos <= $threshold ? $rateBelow : $rateAbove;
        $monthlyContribution = $basicMonthlyPesos * $rate;
        $monthlyContribution = min($monthlyCapPesos, $monthlyContribution);

        $perPeriod = $monthlyContribution / 2; // semi-monthly

        return (int) round($perPeriod * 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Compute employer Pag-IBIG share per SEMI-MONTHLY period.
     * ER rate = always 2%, no cap (PAGIBIG-004). Halved for semi-monthly.
     *
     * @param  int  $basicMonthlyCentavos  Basic monthly salary in centavos
     * @return int Per-period employer share in centavos
     */
    public function computeEmployerSharePerPeriod(int $basicMonthlyCentavos): int
    {
        $monthly = $this->computeEmployerMonthlyShare($basicMonthlyCentavos);

        return (int) round($monthly / 2, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Compute employer Pag-IBIG share per month (for reporting).
     */
    public function computeEmployerMonthlyShare(int $basicMonthlyCentavos): int
    {
        $row = DB::table('pagibig_contribution_tables')
            ->orderByDesc('effective_date')
            ->first();

        if ($row === null) {
            return (int) round($basicMonthlyCentavos * 0.02, 0, PHP_ROUND_HALF_UP);
        }

        $employerRate = (float) $row->employer_rate;
        $basicMonthlyPesos = $basicMonthlyCentavos / 100;

        return (int) round($basicMonthlyPesos * $employerRate * 100, 0, PHP_ROUND_HALF_UP);
    }
}
