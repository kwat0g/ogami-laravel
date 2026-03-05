<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Models\ThirteenthMonthAccrual;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;

/**
 * Records monthly 13th month accruals for each employee after a regular
 * payroll run is approved.
 *
 * 13TH-001: accrual_amount = basic_salary_earned_centavos for the cutoff month.
 * Called once per approved regular payroll run.
 *
 * For semi-monthly payrolls, we aggregate the two cutoffs in the same month
 * by upserting: if an accrual row for the same employee/year/month already
 * exists, we accumulate the new basic pay on top.
 */
final class ThirteenthMonthAccrualService implements ServiceContract
{
    /**
     * Upsert accrual records for all employees in the given regular run.
     */
    public function recordForRun(PayrollRun $run): void
    {
        if ($run->run_type !== 'regular') {
            return; // Nothing to accrue for 13th month runs themselves
        }

        $month = (int) Carbon::parse($run->pay_date)->format('m');
        $year = (int) Carbon::parse($run->pay_date)->format('Y');

        $details = PayrollDetail::where('payroll_run_id', $run->id)
            ->select('employee_id', 'basic_pay_centavos')
            ->cursor();

        foreach ($details as $detail) {
            // Upsert: accumulate within the same month (two semi-monthly cutoffs)
            $existing = ThirteenthMonthAccrual::where('employee_id', $detail->employee_id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existing) {
                $existing->basic_salary_earned_centavos += $detail->basic_pay_centavos;
                $existing->accrual_amount_centavos += $detail->basic_pay_centavos;
                $existing->save();
            } else {
                ThirteenthMonthAccrual::create([
                    'employee_id' => $detail->employee_id,
                    'year' => $year,
                    'month' => $month,
                    'basic_salary_earned_centavos' => $detail->basic_pay_centavos,
                    'accrual_amount_centavos' => $detail->basic_pay_centavos,
                    'payroll_run_id' => $run->id,
                ]);
            }
        }
    }
}
