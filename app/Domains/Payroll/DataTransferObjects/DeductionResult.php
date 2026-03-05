<?php

declare(strict_types=1);

namespace App\Domains\Payroll\DataTransferObjects;

/**
 * Value object returned by DeductionService loan application methods.
 * Immutable — constructed by the service; consumed by pipeline steps.
 */
final readonly class DeductionResult
{
    /**
     * @param  int  $totalDeductedCentavos  Total amount deducted from net pay this call
     * @param  int  $netRemainingCentavos  Net pay remaining after these deductions
     * @param  bool  $hasDeferred  True if any instalment was fully or partially deferred
     * @param  list<array<string, mixed>>  $detail  Per-loan deduction breakdown
     */
    public function __construct(
        public readonly int $totalDeductedCentavos,
        public readonly int $netRemainingCentavos,
        public readonly bool $hasDeferred,
        public readonly array $detail,
    ) {}
}
