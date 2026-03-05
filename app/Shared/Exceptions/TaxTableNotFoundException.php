<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * HTTP 500 — No withholding tax bracket found for an employee's annualised
 * income (EDGE-012).
 *
 * When thrown, the affected employee is flagged COMPUTATION_ERROR and the
 * run is blocked (unlike EDGE-011, a missing tax table is a configuration
 * error that prevents correct BIR compliance and must be resolved before
 * the run can complete).
 *
 * Resolution: seed the missing BIR tax table rows.
 *
 * Error code: TAX_TABLE_NOT_FOUND
 */
final class TaxTableNotFoundException extends DomainException
{
    public function __construct(
        int $employeeId,
        int $annualisedIncomeCentavos,
        string $taxYear,
    ) {
        parent::__construct(
            message: "No BIR withholding tax bracket found for employee #{$employeeId} "
                .'with annualised income ₱'.number_format($annualisedIncomeCentavos / 100, 2)
                ." (tax year {$taxYear}). "
                .'Seed the missing BIR TRAIN Law tax table rows before re-running payroll.',
            errorCode: 'TAX_TABLE_NOT_FOUND',
            httpStatus: 500,
            context: [
                'employee_id' => $employeeId,
                'annualised_income_centavos' => $annualisedIncomeCentavos,
                'tax_year' => $taxYear,
            ],
        );
    }
}
