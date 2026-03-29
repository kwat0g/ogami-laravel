<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Validators;

use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayPeriod;
use App\Domains\Payroll\Models\PayrollRun;
use App\Shared\Exceptions\DomainException;

/**
 * PayrollRunValidator — asserts business constraints before run creation.
 */
final class PayrollRunValidator
{
    /**
     * PR-001: Ensure no other active run of the same type overlaps the proposed cutoff range.
     *
     * Regular runs may not overlap other regular runs.
     * 13th month runs may not overlap other 13th month runs.
     * They do not conflict with each other (per excl_payroll_run_dates_per_type constraint).
     *
     * @throws DomainException
     */
    public function assertNonOverlapping(string $cutoffStart, string $cutoffEnd, string $runType = 'regular'): void
    {
        // REC-07: Exclude all terminal/transient states — not just 'cancelled'.
        // Previously only 'cancelled' was excluded, so a REJECTED or RETURNED run
        // still blocked creation of a replacement run for the same period.
        $terminalStatuses = ['cancelled', 'REJECTED', 'RETURNED', 'FAILED', 'PUBLISHED'];

        $overlapping = PayrollRun::whereNotIn('status', $terminalStatuses)
            ->where('run_type', $runType)
            ->where('cutoff_start', '<=', $cutoffEnd)
            ->where('cutoff_end', '>=', $cutoffStart)
            ->exists();

        if ($overlapping) {
            throw new DomainException(
                'A payroll run already exists that overlaps this cutoff period.',
                'PR_OVERLAP',
                422,
                ['cutoff_start' => $cutoffStart, 'cutoff_end' => $cutoffEnd, 'run_type' => $runType],
            );
        }
    }

    /**
     * Assert that cutoff_start <= cutoff_end <= pay_date.
     *
     * @throws DomainException
     */
    public function assertCutoffOrder(string $cutoffStart, string $cutoffEnd, string $payDate): void
    {
        if ($cutoffStart > $cutoffEnd) {
            throw new DomainException(
                'cutoff_start must not be after cutoff_end.',
                'PR_INVALID_CUTOFF',
                422,
                ['cutoff_start' => $cutoffStart, 'cutoff_end' => $cutoffEnd],
            );
        }

        if ($payDate < $cutoffEnd) {
            throw new DomainException(
                'pay_date must be on or after cutoff_end.',
                'PR_PAYDATE_BEFORE_CUTOFF',
                422,
                ['cutoff_end' => $cutoffEnd, 'pay_date' => $payDate],
            );
        }
    }

    /**
     * PR-004: At least one active employee must exist before a payroll run can be initiated.
     *
     * An empty payroll run (no eligible employees) would produce a zero-line compute
     * batch and waste processing resources.  This guard prevents it.
     *
     * @throws DomainException
     */
    public function assertActiveEmployeesExist(): void
    {
        $hasActive = Employee::where('employment_status', 'active')->exists();

        if (! $hasActive) {
            throw new DomainException(
                'Cannot initiate a payroll run: no active employees found.',
                'PR_NO_ACTIVE_EMPLOYEES',
                422,
            );
        }
    }

    /**
     * PR-005: An open pay period must fully contain the proposed cutoff range.
     *
     * Each payroll run must be anchored to a pay period that is still open.
     * Closed periods are locked and cannot accept new runs.
     *
     * @throws DomainException
     */
    public function assertOpenPayPeriodExists(string $cutoffStart, string $cutoffEnd): void
    {
        $exists = PayPeriod::where('status', 'open')
            ->where('cutoff_start', '<=', $cutoffStart)
            ->where('cutoff_end', '>=', $cutoffEnd)
            ->exists();

        if (! $exists) {
            throw new DomainException(
                'No open pay period covers the requested cutoff range.',
                'PR_NO_OPEN_PERIOD',
                422,
                ['cutoff_start' => $cutoffStart, 'cutoff_end' => $cutoffEnd],
            );
        }
    }
}
