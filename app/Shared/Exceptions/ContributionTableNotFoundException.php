<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * HTTP 500 — A government contribution rate table could not be found for the
 * required effective date (EDGE-011).
 *
 * This is treated as a system-level configuration error (not a user error).
 * It triggers an alert, blocks the affected employee's computation, and
 * the run continues for other employees. The error is logged with full context
 * and surfaced in Laravel Pulse.
 *
 * Resolution: an Admin must seed the missing rate table row before the run
 * can complete cleanly.
 */
class ContributionTableNotFoundException extends DomainException
{
    public function __construct(
        string $tableType,
        string $effectiveDate,
        int $employeeId,
    ) {
        parent::__construct(
            message: "No {$tableType} contribution table found effective on {$effectiveDate}. "
                ."Seed the missing rate before re-running payroll for employee #{$employeeId}.",
            errorCode: 'CONTRIBUTION_TABLE_NOT_FOUND',
            httpStatus: 500,
            context: [
                'table_type' => $tableType,
                'effective_date' => $effectiveDate,
                'employee_id' => $employeeId,
            ],
        );
    }
}
