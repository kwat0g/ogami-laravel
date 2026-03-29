<?php

declare(strict_types=1);

namespace App\Listeners\Payroll;

use App\Domains\HR\Events\EmployeeActivated;
use App\Domains\Payroll\Models\PayPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-010: When a new employee is activated, ensure they are included in
 * the next open payroll period scope.
 *
 * The payroll system uses PayrollScopeService to determine which employees
 * are in a payroll run. This listener ensures the employee record is visible
 * to the scope service by logging the activation and verifying the employee
 * has the required fields for payroll computation (basic_monthly_rate, etc.).
 */
final class EnrollNewHireInPayroll implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(EmployeeActivated $event): void
    {
        $employee = $event->employee;

        // Validate that employee has required payroll fields
        $warnings = [];

        if (empty($employee->basic_monthly_rate) || $employee->basic_monthly_rate <= 0) {
            $warnings[] = 'basic_monthly_rate is missing or zero';
        }

        if (empty($employee->tax_status)) {
            $warnings[] = 'tax_status is not set';
        }

        if (empty($employee->sss_number)) {
            $warnings[] = 'SSS number is missing';
        }

        if (empty($employee->philhealth_number)) {
            $warnings[] = 'PhilHealth number is missing';
        }

        if (empty($employee->pagibig_number)) {
            $warnings[] = 'Pag-IBIG number is missing';
        }

        if (empty($employee->tin)) {
            $warnings[] = 'TIN is missing';
        }

        // Find the next open pay period to verify enrollment
        $nextPeriod = PayPeriod::where('status', 'open')
            ->where('cutoff_end', '>=', now()->toDateString())
            ->orderBy('cutoff_start')
            ->first();

        Log::info('[Payroll] New hire activated — payroll enrollment check', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name ?? $employee->id,
            'hire_date' => (string) $employee->hire_date,
            'basic_monthly_rate' => $employee->basic_monthly_rate ?? 0,
            'next_pay_period' => $nextPeriod?->reference ?? 'none found',
            'warnings' => $warnings,
            'payroll_ready' => empty($warnings),
        ]);

        if (! empty($warnings)) {
            Log::warning('[Payroll] New hire missing required payroll fields — may be excluded from next run', [
                'employee_id' => $employee->id,
                'warnings' => $warnings,
            ]);
        }
    }
}
