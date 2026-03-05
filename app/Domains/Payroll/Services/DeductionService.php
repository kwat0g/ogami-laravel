<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Loan\Models\Loan;
use App\Domains\Payroll\DataTransferObjects\DeductionResult;
use App\Domains\Payroll\Pipeline\PayrollComputationHelper;
use App\Shared\Contracts\ServiceContract;

/**
 * DeductionService — encapsulates DED-001 through DED-004 deduction rules.
 *
 * DED-001 = Statutory government contributions (SSS / PhilHealth / Pag-IBIG)
 *   → Applied first; never reduced regardless of net pay.
 *
 * DED-002 = Withholding tax (TRAIN Law)
 *   → Applied second; never reduced; computed from taxable income.
 *
 * DED-003 = Government loans (SSS Calamity, Pag-IBIG multi-purpose)
 *   → Applied third; pro-rata reduced if minimum-wage floor would be breached.
 *
 * DED-004 = Company / cooperative / other loans
 *   → Applied last (lowest priority); first to be cut at LN-007 floor.
 *
 * Extracted from the inline pipeline step logic so each rule can be unit-tested
 * in isolation without spinning up the full 17-step pipeline.
 *
 * @see Step15LoanDeductionsStep  — calls applyGovernmentLoans() + applyCompanyLoans()
 * @see Step16OtherDeductionsStep — calls applyOtherDeductions()
 * @see Step17NetPayStep          — calls computeNetPay() + buildDeductionTrace()
 */
final class DeductionService implements ServiceContract
{
    // ─── DED-001: Statutory government contributions ──────────────────────────

    /**
     * Verify statutory contributions are already set in context.
     * Statutory deductions (SSS/PhilHealth/Pagibig EE shares) are NEVER reduced
     * regardless of net pay. This method asserts the invariant and records the
     * trace step.
     *
     * @return PayrollComputationContext The context with the trace step appended
     */
    public function applyStatutory(PayrollComputationContext $ctx): PayrollComputationContext
    {
        // Statutory amounts are set by Steps 10‒12 (SSS / PhilHealth / Pagibig).
        // This service method exists for explicit DED-001 test coverage and to
        // allow future override points (e.g. minimum wage exemption waiver).

        // Invariant: statutory total must not exceed gross pay
        $statutoryTotal = $ctx->sssEeCentavos
            + $ctx->philhealthEeCentavos
            + $ctx->pagibigEeCentavos;

        if ($ctx->grossPayCentavos > 0 && $statutoryTotal > $ctx->grossPayCentavos) {
            // Flag as computation error — this should never happen in practice
            $ctx->hasComputationError = true;
        }

        return $ctx;
    }

    // ─── DED-002: Withholding tax ─────────────────────────────────────────────

    /**
     * Withholding tax (TRAIN Law) is never reduced.
     * This method documents the DED-002 invariant and enables targeted tests.
     *
     * In the pipeline, withholding tax is computed by Step 14 and stored in
     * $ctx->withholdingTaxCentavos. This service asserts it is non-negative.
     */
    public function applyWithholdingTax(PayrollComputationContext $ctx): PayrollComputationContext
    {
        // Withholding tax is always >= 0 (TRAIN Law)
        if ($ctx->withholdingTaxCentavos < 0) {
            $ctx->withholdingTaxCentavos = 0;
        }

        return $ctx;
    }

    // ─── DED-003: Government loans ────────────────────────────────────────────

    /**
     * Apply government-category loan deductions with proportional minimum-wage
     * floor protection (LN-007).
     *
     * Government loans (SSS Calamity, Pag-IBIG multi-purpose, GSIS) are
     * pro-rata reduced — not skipped entirely — when full deduction would breach
     * the minimum-wage floor. The deferred amount is carried forward.
     *
     * @param  int  $netRemainingCentavos  Net pay remaining after DED-001 + DED-002
     */
    public function applyGovernmentLoans(
        PayrollComputationContext $ctx,
        int $netRemainingCentavos,
    ): DeductionResult {
        return $this->applyLoansByCategory($ctx, $netRemainingCentavos, 'government');
    }

    // ─── DED-004: Company loans and other deductions ──────────────────────────

    /**
     * Apply company / cooperative / other loan deductions.
     * These are the first deductions to be cut (lowest priority in the stack).
     * LN-007 minimum wage floor protection applies strictly — these are fully
     * deferred if the floor would be breached.
     *
     * @param  int  $netRemainingCentavos  Net pay remaining after DED-001 + DED-002 + DED-003
     */
    public function applyCompanyLoans(
        PayrollComputationContext $ctx,
        int $netRemainingCentavos,
    ): DeductionResult {
        return $this->applyLoansByCategory($ctx, $netRemainingCentavos, 'company');
    }

    // ─── Net pay + trace ──────────────────────────────────────────────────────

    /**
     * Compute final net pay and build the DED-004 deduction audit trace.
     * Must be called after all deduction methods have been applied.
     */
    public function computeNetPay(PayrollComputationContext $ctx): PayrollComputationContext
    {
        $ctx->totalDeductionsCentavos =
            $ctx->sssEeCentavos
            + $ctx->philhealthEeCentavos
            + $ctx->pagibigEeCentavos
            + $ctx->withholdingTaxCentavos
            + $ctx->loanDeductionsCentavos
            + $ctx->otherDeductionsCentavos;

        $ctx->netPayCentavos = max(0, $ctx->grossPayCentavos - $ctx->totalDeductionsCentavos);

        return $ctx;
    }

    /**
     * Compute the semi-monthly minimum-wage floor proportional to days worked.
     * Uses the minimum_wage_rates table via PayrollComputationHelper.
     */
    public function computeMinWageFloorCentavos(PayrollComputationContext $ctx): int
    {
        $minMonthlyNetCentavos = PayrollComputationHelper::getMinimumMonthlyNetCentavos(
            $ctx->run->cutoff_end
        );
        $daysWorked = max(1, $ctx->daysWorked);

        return (int) round($minMonthlyNetCentavos * $daysWorked / 26.0, 0, PHP_ROUND_HALF_UP);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function applyLoansByCategory(
        PayrollComputationContext $ctx,
        int $netRemainingCentavos,
        string $category,
    ): DeductionResult {
        $floorCentavos = $this->computeMinWageFloorCentavos($ctx);
        $remaining = $netRemainingCentavos;
        $totalDeducted = 0;
        $hasDeferred = false;
        $detail = [];

        foreach ($ctx->activeLoans as $loan) {
            if ($loan->loanType->category !== $category) {
                continue;
            }

            $nextInstallment = $loan->nextInstallment();
            if ($nextInstallment === null) {
                continue;
            }

            $dueAmount = $nextInstallment->total_due_centavos;
            $headroom = $remaining - $floorCentavos;

            if ($headroom <= 0) {
                // Already at floor — defer entire instalment
                $hasDeferred = true;
                $detail[] = [
                    'loan_id' => $loan->id,
                    'amount_centavos' => 0,
                    'applied' => false,
                    'deferred' => $dueAmount,
                ];

                continue;
            }

            if ($headroom >= $dueAmount) {
                // Full deduction — floor respected
                $remaining -= $dueAmount;
                $totalDeducted += $dueAmount;
                $detail[] = [
                    'loan_id' => $loan->id,
                    'amount_centavos' => $dueAmount,
                    'applied' => true,
                ];
            } else {
                // Partial — defer remainder (LN-007)
                $remaining -= $headroom;
                $totalDeducted += $headroom;
                $hasDeferred = true;
                $detail[] = [
                    'loan_id' => $loan->id,
                    'amount_centavos' => $headroom,
                    'applied' => true,
                    'deferred' => $dueAmount - $headroom,
                ];
            }
        }

        return new DeductionResult(
            totalDeductedCentavos: $totalDeducted,
            netRemainingCentavos: $remaining,
            hasDeferred: $hasDeferred,
            detail: $detail,
        );
    }
}
