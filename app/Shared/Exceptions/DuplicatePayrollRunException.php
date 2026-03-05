<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** HTTP 409 — A payroll run already exists for this period + group (PR-001, PR-003). */
class DuplicatePayrollRunException extends DomainException
{
    public function __construct(string $periodLabel, string $payrollGroupName, int $existingRunId)
    {
        parent::__construct(
            message: "A payroll run for '{$payrollGroupName}' during '{$periodLabel}' already exists (Run #{$existingRunId}). "
                .'Cancel or archive the existing run before creating a new one.',
            errorCode: 'DUPLICATE_PAYROLL_RUN',
            httpStatus: 409,
            context: [
                'period' => $periodLabel,
                'payroll_group' => $payrollGroupName,
                'existing_run_id' => $existingRunId,
            ],
        );
    }
}
