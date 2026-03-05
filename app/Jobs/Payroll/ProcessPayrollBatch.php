<?php

declare(strict_types=1);

namespace App\Jobs\Payroll;

use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Services\PayrollComputationService;
use App\Domains\Payroll\Services\ThirteenthMonthComputationService;
use App\Shared\Exceptions\DomainException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process a single employee's payroll computation within a batch.
 *
 * Dispatched by PayrollBatchDispatcher when a PayrollRun is locked.
 * Each job runs the 17-step pipeline for one employee and persists
 * a PayrollDetail row.
 *
 * Queue: payroll
 * Retries: 3 (network/DB glitches only — DomainExceptions do not retry)
 * Batch: Batchable — parent batch ID is on the PayrollRun record
 */
final class ProcessPayrollBatch implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $employeeId,
        public readonly int $payrollRunId,
    ) {
        $this->onQueue('payroll');
    }

    public function handle(
        PayrollComputationService $service,
        ThirteenthMonthComputationService $thirteenthService,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $employee = Employee::find($this->employeeId);
        $run = PayrollRun::find($this->payrollRunId);

        if ($employee === null || $run === null) {
            Log::warning('ProcessPayrollBatch: missing employee or run', [
                'employee_id' => $this->employeeId,
                'payroll_run_id' => $this->payrollRunId,
            ]);

            return;
        }

        try {
            if ($run->isThirteenthMonth()) {
                $thirteenthService->computeForEmployee($employee, $run);
            } else {
                $service->computeForEmployee($employee, $run);
            }
        } catch (DomainException $e) {
            // Domain errors are logged but do not fail the batch so other
            // employees can still be processed.
            Log::error('ProcessPayrollBatch: domain error for employee', [
                'employee_id' => $this->employeeId,
                'payroll_run_id' => $this->payrollRunId,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
