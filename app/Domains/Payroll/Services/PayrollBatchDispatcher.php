<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Models\PayrollRunExclusion;
use App\Jobs\Payroll\ProcessPayrollBatch;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dispatches per-employee payroll batch jobs via Laravel Horizon.
 *
 * Called after a PayrollRun is locked. Transitions the run to 'processing'
 * once all jobs are queued. The batch completion callback transitions it
 * to 'completed' (handled externally via PayrollRunService::approve()).
 */
final class PayrollBatchDispatcher implements ServiceContract
{
    /**
     * Dispatch one ProcessPayrollBatch job per active employee.
     *
     * @return array{batch_id: string, total_jobs: int}
     */
    public function dispatch(PayrollRun $run): array
    {
        // Collect manually excluded employee IDs for this run
        $excludedIds = PayrollRunExclusion::where('payroll_run_id', $run->id)
            ->pluck('employee_id')
            ->all();

        $query = Employee::where('is_active', true)
            ->where('employment_status', 'active')
            ->where('date_hired', '<=', $run->cutoff_end)
            ->select('id');

        if (! empty($excludedIds)) {
            $query->whereNotIn('id', $excludedIds);
        }

        // Apply department scope if set
        if (! empty($run->scope_departments)) {
            $query->whereIn('department_id', $run->scope_departments);
        }

        // Apply employment type scope if set
        if (! empty($run->scope_employment_types)) {
            $query->whereIn('employment_type', $run->scope_employment_types);
        }

        $employees = $query->get();

        $totalEmployees = $employees->count();

        $jobs = $employees->map(
            fn (Employee $e) => new ProcessPayrollBatch($e->id, $run->id),
        )->all();

        // Stamp totals and start time before dispatching so the progress
        // endpoint has accurate data from the first poll.
        DB::table('payroll_runs')
            ->where('id', $run->id)
            ->update([
                'total_employees' => $totalEmployees,
                'computation_started_at' => now(),
                'status' => 'PROCESSING',
                'progress_json' => json_encode([
                    'total_employees' => $totalEmployees,
                    'employees_processed' => 0,
                    'percent_complete' => 0,
                ]),
            ]);

        $runId = $run->id;

        $batch = Bus::batch($jobs)
            ->name("payroll-run-{$run->id}")
            ->allowFailures()
            ->onQueue('payroll')
            ->then(function (Batch $batch) use ($runId, $totalEmployees): void {
                // All jobs done — aggregate totals and validate success rate.
                $processed = (int) DB::table('payroll_details')->where('payroll_run_id', $runId)->count();
                $gross = (int) DB::table('payroll_details')->where('payroll_run_id', $runId)->sum('gross_pay_centavos');
                $net = (int) DB::table('payroll_details')->where('payroll_run_id', $runId)->sum('net_pay_centavos');
                $deductions = (int) DB::table('payroll_details')->where('payroll_run_id', $runId)->sum('total_deductions_centavos');

                // H4 FIX: Validate that a minimum percentage of employees were actually
                // computed before transitioning to COMPUTED. If <50% succeeded, mark as
                // FAILED to prevent an empty/near-empty payroll from being approved.
                $minSuccessRate = 0.50; // 50% threshold
                $successRate = $totalEmployees > 0 ? ($processed / $totalEmployees) : 0;

                if ($processed === 0 || $successRate < $minSuccessRate) {
                    $pct = round($successRate * 100, 1);
                    DB::table('payroll_runs')->where('id', $runId)->update([
                        'status' => 'FAILED',
                        'failure_reason' => "Only {$processed} of {$totalEmployees} employees computed ({$pct}%). "
                            ."Minimum success rate is ".($minSuccessRate * 100).'%. '
                            .'Check attendance data and employee records, then retry.',
                        'computation_completed_at' => now(),
                        'progress_json' => json_encode([
                            'total_employees' => $totalEmployees,
                            'employees_processed' => $processed,
                            'percent_complete' => $pct,
                            'failed' => true,
                        ]),
                    ]);

                    return;
                }

                DB::table('payroll_runs')->where('id', $runId)->update([
                    'status' => 'COMPUTED',
                    'computation_completed_at' => now(),
                    'total_employees' => $processed,
                    'gross_pay_total_centavos' => $gross,
                    'net_pay_total_centavos' => $net,
                    'total_deductions_centavos' => $deductions,
                    'progress_json' => json_encode([
                        'total_employees' => $processed,
                        'employees_processed' => $processed,
                        'percent_complete' => 100,
                    ]),
                ]);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($runId): void {
                DB::table('payroll_runs')->where('id', $runId)->update([
                    'status' => 'FAILED',
                    'failure_reason' => $e->getMessage(),
                    'progress_json' => json_encode(['error' => $e->getMessage()]),
                ]);
            })
            ->dispatch();

        return [
            'batch_id' => $batch->id,
            'total_jobs' => $totalEmployees,
        ];
    }
}
