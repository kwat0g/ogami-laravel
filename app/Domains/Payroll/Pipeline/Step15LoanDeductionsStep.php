<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanAmortizationSchedule;
use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;

/**
 * Step 15 — Loan Deductions (DED-001 strict per-slot minimum-wage protection).
 *
 * Deduction slots #5–#7 in the priority stack (RA 8291 government-first):
 *   #5 Government loans (SSS, Pag-IBIG)
 *   #6 Company / cooperative loans
 *   #7 Any additional loan categories
 *
 * DED-001 floor rule (per DOLE Labor Advisory 01-2022):
 *   Before deducting each loan instalment, verify:
 *     net_remaining − instalment_amount ≥ min_wage × days_worked
 *   If the full instalment would breach the floor: take a partial amount up
 *   to the floor, defer the remainder (LN-007).
 *   If net_remaining is already at or below the floor: skip entirely.
 *
 *   Note: the floor is proportional to actual days_worked, not a fixed half-
 *   month, because short periods (new hire, resignation) have fewer paid days.
 */
final class Step15LoanDeductionsStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $activeLoans = Loan::where('employee_id', $ctx->employee->id)
            ->whereIn('status', ['active'])
            ->with('loanType')
            ->orderByRaw("CASE WHEN loan_types.category = 'government' THEN 0 ELSE 1 END")
            ->join('loan_types', 'loans.loan_type_id', '=', 'loan_types.id')
            ->select('loans.*')
            ->get();

        $ctx->activeLoans = $activeLoans;

        // Net after mandatory statutory deductions (Steps 7–10: SSS/PhilHealth/PagIBIG/WT)
        $netAfterStatutory = $ctx->grossPayCentavos
            - $ctx->sssEeCentavos
            - $ctx->philhealthEeCentavos
            - $ctx->pagibigEeCentavos
            - $ctx->withholdingTaxCentavos;

        // DED-001: floor = daily_minimum_wage × days_worked
        // Helper returns monthly (26 days at daily min-wage × 100).
        $minMonthlyNetCentavos = PayrollComputationHelper::getMinimumMonthlyNetCentavos($ctx->run->cutoff_end);
        $daysWorked = max(1, $ctx->daysWorked); // avoid zero division on new-hire edge
        $floorCentavos = (int) round($minMonthlyNetCentavos * $daysWorked / 26.0, 0, PHP_ROUND_HALF_UP);

        $remainingNet = $netAfterStatutory;

        foreach ($activeLoans as $loan) {
            /** @var LoanAmortizationSchedule|null $nextInstallment */
            $nextInstallment = $loan->nextInstallment();
            if ($nextInstallment === null) {
                continue;
            }

            $dueAmount = $nextInstallment->total_due_centavos;
            $headroom = $remainingNet - $floorCentavos;

            if ($headroom <= 0) {
                // Already at or below floor — full deferral
                $ctx->hasDeferredDeductions = true;

                continue;
            }

            if ($headroom >= $dueAmount) {
                // Full deduction — floor still respected after
                $remainingNet -= $dueAmount;
                $ctx->loanDeductionsCentavos += $dueAmount;
                $ctx->loanDeductionDetail[] = [
                    'loan_id' => $loan->id,
                    'amount_centavos' => $dueAmount,
                    'applied' => true,
                ];
            } else {
                // Partial deduction — defer remainder (LN-007)
                $partial = $headroom;
                $remainingNet -= $partial;
                $ctx->loanDeductionsCentavos += $partial;
                $ctx->loanDeductionDetail[] = [
                    'loan_id' => $loan->id,
                    'amount_centavos' => $partial,
                    'applied' => true,
                    'deferred' => $dueAmount - $partial,
                ];
                $ctx->hasDeferredDeductions = true;
            }
        }

        return $next($ctx);
    }
}
