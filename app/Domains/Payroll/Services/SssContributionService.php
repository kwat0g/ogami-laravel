<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * SSS Contribution Service.
 *
 * Looks up the SSS contribution table and returns the employee (EE) share.
 * Source table: sss_contribution_tables (seeded from SSS Circular 2023-001)
 *
 * SSS-001: Base = monthly basic salary — NOT gross pay.
 * SSS-002: Semi-monthly deduction = full monthly EE contribution (NOT halved —
 *          SSS collects full contribution from the payroll period where it falls).
 *          Convention in this system: deduct on 2nd cut-off of the month only.
 * SSS-003: Once per month. If run = 1st cutoff → 0; if run = 2nd cutoff → full.
 * SSS-004: Below minimum MSC (₱4,000) → use lowest bracket.
 * EDGE-014: Above maximum MSC (₱30,000) → use highest bracket.
 *
 * All inputs/outputs are integer centavos.
 */
final class SssContributionService implements ServiceContract
{
    /**
     * Compute the employee SSS contribution for a given basic monthly salary.
     * Returns centavos. Pass $isSemiMonthlySecondCutoff = false to skip (1st cutoff).
     *
     * @param  int  $basicMonthlyCentavos  Employee basic monthly salary in centavos
     * @param  bool  $isSecondCutoff  True = 2nd cut-off (deduct now); False = 1st (skip)
     * @return int Employee share in centavos
     */
    public function computeEmployeeShare(int $basicMonthlyCentavos, bool $isSecondCutoff = true): int
    {
        if (! $isSecondCutoff) {
            return 0;
        }

        $monthlySalary = $basicMonthlyCentavos / 100; // convert to pesos for lookup

        $row = DB::table('sss_contribution_tables')
            ->where('salary_range_from', '<=', $monthlySalary)
            ->where(function ($q) use ($monthlySalary) {
                $q->where('salary_range_to', '>=', $monthlySalary)
                    ->orWhereNull('salary_range_to'); // above-max bracket
            })
            ->orderByDesc('salary_range_from')
            ->first();

        if ($row === null) {
            // Below minimum bracket — use lowest bracket
            $row = DB::table('sss_contribution_tables')
                ->orderBy('salary_range_from')
                ->first();
        }

        if ($row === null) {
            return 0;
        }

        // employee_contribution is stored in PHP float (pesos); convert to centavos
        return (int) round((float) $row->employee_contribution * 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Compute the employer SSS contribution for reporting purposes.
     */
    public function computeEmployerShare(int $basicMonthlyCentavos, bool $isSecondCutoff = true): int
    {
        if (! $isSecondCutoff) {
            return 0;
        }

        $monthlySalary = $basicMonthlyCentavos / 100;

        $row = DB::table('sss_contribution_tables')
            ->where('salary_range_from', '<=', $monthlySalary)
            ->where(function ($q) use ($monthlySalary) {
                $q->where('salary_range_to', '>=', $monthlySalary)
                    ->orWhereNull('salary_range_to');
            })
            ->orderByDesc('salary_range_from')
            ->first();

        if ($row === null) {
            $row = DB::table('sss_contribution_tables')
                ->orderBy('salary_range_from')
                ->first();
        }

        if ($row === null) {
            return 0;
        }

        return (int) round((float) $row->employer_contribution * 100, 0, PHP_ROUND_HALF_UP);
    }
}
