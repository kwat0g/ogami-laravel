<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Shared\Exceptions\NegativeNetPayException;
use Closure;

/**
 * Step 17 — Net Pay + Minimum-Wage Guard + DED-004 Audit Trace.
 *
 * Aggregates all deduction buckets into total_deductions, then:
 *   1. Computes net_pay = gross_pay − total_deductions
 *   2. DED-002: Throws NegativeNetPayException if net_pay < 0 (statutory > gross).
 *   3. EDGE-001: Flags isBelowMinWage if net pay < proportional min-wage floor.
 *   4. EDGE-003/010: Flags isZeroPay when gross = 0 (zero-attendance / LWOP).
 *   5. DED-004: Assembles the full deduction_stack_trace JSON for audit.
 */
final class Step17NetPayStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        $ctx->totalDeductionsCentavos =
            $ctx->sssEeCentavos
            + $ctx->philhealthEeCentavos
            + $ctx->pagibigEeCentavos
            + $ctx->withholdingTaxCentavos
            + $ctx->loanDeductionsCentavos
            + $ctx->otherDeductionsCentavos;

        $rawNet = $ctx->grossPayCentavos - $ctx->totalDeductionsCentavos;

        // DED-002: guard — statutory deductions must never exceed gross pay
        $statutoryTotal = $ctx->sssEeCentavos
            + $ctx->philhealthEeCentavos
            + $ctx->pagibigEeCentavos
            + $ctx->withholdingTaxCentavos;

        if ($rawNet < 0) {
            // Mark for HR review and throw — ProcessPayrollBatch catches DomainException
            // and sets computation_error = true, continuing the run for other employees.
            throw new NegativeNetPayException(
                $ctx->employee->id,
                $ctx->employee->employee_code,
                $rawNet / 100,
            );
        }

        $ctx->netPayCentavos = $rawNet;

        // ── EDGE-003/010: zero-pay flag ───────────────────────────────────────
        if ($ctx->grossPayCentavos === 0 || $ctx->netPayCentavos === 0) {
            $ctx->isZeroPay = true;
        }

        // ── EDGE-001: below-minimum-wage informational flag ───────────────────
        $minMonthlyNetCentavos = PayrollComputationHelper::getMinimumMonthlyNetCentavos($ctx->run->cutoff_end);
        $daysWorked = max(1, $ctx->daysWorked);
        $floorCentavos = (int) round($minMonthlyNetCentavos * $daysWorked / 26.0, 0, PHP_ROUND_HALF_UP);

        if ($ctx->netPayCentavos < $floorCentavos && $ctx->grossPayCentavos > 0) {
            $ctx->isBelowMinWage = true;
        }

        // ── DED-004: Assemble deduction_stack_trace ───────────────────────────
        $netAfter1 = $ctx->grossPayCentavos - $ctx->sssEeCentavos;
        $netAfter2 = $netAfter1 - $ctx->philhealthEeCentavos;
        $netAfter3 = $netAfter2 - $ctx->pagibigEeCentavos;
        $netAfter4 = $netAfter3 - $ctx->withholdingTaxCentavos;
        $netAfter5 = $netAfter4 - $ctx->loanDeductionsCentavos;
        $netAfter6 = $netAfter5 - $ctx->otherDeductionsCentavos;

        $ctx->deductionTrace = [
            [
                'step' => 1,
                'type' => 'sss_employee',
                'amount_centavos' => $ctx->sssEeCentavos,
                'net_after_centavos' => $netAfter1,
                'applied' => true,
            ],
            [
                'step' => 2,
                'type' => 'philhealth_employee',
                'amount_centavos' => $ctx->philhealthEeCentavos,
                'net_after_centavos' => $netAfter2,
                'applied' => true,
            ],
            [
                'step' => 3,
                'type' => 'pagibig_employee',
                'amount_centavos' => $ctx->pagibigEeCentavos,
                'net_after_centavos' => $netAfter3,
                'applied' => true,
            ],
            [
                'step' => 4,
                'type' => 'withholding_tax',
                'amount_centavos' => $ctx->withholdingTaxCentavos,
                'net_after_centavos' => $netAfter4,
                'applied' => true,
            ],
            [
                'step' => 5,
                'type' => 'min_wage_check',
                'threshold_centavos' => $floorCentavos,
                'net_current_centavos' => $netAfter4,
                'passed' => $netAfter4 >= $floorCentavos,
            ],
            [
                'step' => 6,
                'type' => 'loan_deductions',
                'amount_centavos' => $ctx->loanDeductionsCentavos,
                'net_after_centavos' => $netAfter5,
                'applied' => true,
                'detail' => $ctx->loanDeductionDetail,
            ],
            [
                'step' => 7,
                'type' => 'other_deductions',
                'amount_centavos' => $ctx->otherDeductionsCentavos,
                'net_after_centavos' => $netAfter6,
                'applied' => true,
            ],
        ];

        return $next($ctx);
    }
}
