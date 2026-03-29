<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\StateMachines\PayrollRunStateMachine;
use App\Models\User;
use App\Notifications\PayrollProcessingStuckNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * REC-06: Watchdog for stale payroll runs stuck at PROCESSING.
 *
 * When a payroll batch job crashes (Redis failure, worker timeout, etc.),
 * the PayrollRun stays at PROCESSING indefinitely with no mechanism to
 * recover. This command detects stale runs and auto-transitions them to
 * FAILED so the officer can retry or create a new run.
 *
 * Schedule: every 5 minutes via Kernel.
 */
final class PayrollProcessingWatchdogCommand extends Command
{
    protected $signature = 'payroll:watchdog
        {--timeout=30 : Minutes after which a PROCESSING run is considered stale}';

    protected $description = 'Detect and auto-fail payroll runs stuck at PROCESSING state';

    public function handle(PayrollRunStateMachine $stateMachine): int
    {
        $timeoutMinutes = (int) $this->option('timeout');
        $threshold = now()->subMinutes($timeoutMinutes);

        $staleRuns = PayrollRun::where('status', 'PROCESSING')
            ->where('updated_at', '<', $threshold)
            ->get();

        if ($staleRuns->isEmpty()) {
            $this->info('No stale PROCESSING payroll runs found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$staleRuns->count()} stale PROCESSING run(s).");

        foreach ($staleRuns as $run) {
            $minutesStuck = (int) now()->diffInMinutes($run->updated_at);

            try {
                $stateMachine->transition($run, 'FAILED');
                $run->save();

                Log::critical('Payroll processing watchdog auto-failed stale run', [
                    'payroll_run_id' => $run->id,
                    'reference_no' => $run->reference_no,
                    'minutes_stuck' => $minutesStuck,
                ]);

                $this->line("  Run #{$run->reference_no} (ID {$run->id}): PROCESSING -> FAILED after {$minutesStuck} minutes.");

                // Notify payroll officers
                $this->notifyPayrollTeam($run, $minutesStuck);
            } catch (\Throwable $e) {
                Log::error('Payroll watchdog failed to transition run', [
                    'payroll_run_id' => $run->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  Failed to transition Run #{$run->reference_no}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function notifyPayrollTeam(PayrollRun $run, int $minutesStuck): void
    {
        try {
            $payrollUsers = User::role(['officer', 'manager'])
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('payroll.create'));

            if ($payrollUsers->isEmpty()) {
                return;
            }

            foreach ($payrollUsers as $user) {
                $user->notify(new PayrollProcessingStuckNotification($run, $minutesStuck));
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }
}
