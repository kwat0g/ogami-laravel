<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domains\Accounting\Services\PayrollAutoPostService;
use App\Domains\Payroll\Models\PayrollRun;
use App\Events\Payroll\PayrollStatusChanged;
use App\Models\User;
use App\Notifications\PayrollApprovedNotification;
use App\Notifications\PayrollSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PayrollRun Observer — reacts to status transitions.
 *
 * draft → locked    : notify accounting officers that a run is awaiting approval
 * any   → completed : GL auto-post, notify HR manager who created it,
 *                     and notify each employee (payslip ready)
 */
final class PayrollRunObserver
{
    public function __construct(
        private readonly PayrollAutoPostService $autoPostService,
    ) {}

    /**
     * Called after any payroll_run UPDATE.
     */
    public function updated(PayrollRun $run): void
    {
        $oldStatus = $run->getOriginal('status');
        $newStatus = $run->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        match (true) {
            $newStatus === 'locked' => DB::afterCommit(fn () => $this->onLocked($run)),
            $newStatus === 'completed' => DB::afterCommit(fn () => $this->onCompleted($run)),
            default => null,
        };
    }

    // ── Transition handlers ───────────────────────────────────────────────────

    /**
     * Run locked (submitted for approval) → notify accounting officers.
     */
    private function onLocked(PayrollRun $run): void
    {
        try {
            $accountingOfficers = User::role(['manager', 'officer'])->get();
        } catch (\Throwable $e) {
            // Role may not exist in minimal test environments — skip gracefully.
            Log::warning("[PayrollRunObserver] Could not query manager roles: {$e->getMessage()}");

            return;
        }

        foreach ($accountingOfficers as $officer) {
            try {
                $officer->notify(new PayrollSubmittedNotification($run));
                PayrollStatusChanged::dispatch($run, $officer->id, 'locked');
            } catch (\Throwable $e) {
                Log::warning("[PayrollRunObserver] Failed to notify officer #{$officer->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Run completed (approved) → GL auto-post + notify HR manager + notify employees.
     */
    private function onCompleted(PayrollRun $run): void
    {
        // 1. GL Auto-post — isolated in its own transaction so a failure
        //    rolls back to a clean savepoint without aborting the session.
        try {
            DB::transaction(function () use ($run) {
                $je = $this->autoPostService->post($run);
                Log::info("[PayrollRunObserver] Auto-posted GL JE for PayrollRun #{$run->id} ({$run->pay_period_label}): {$je->je_number}");
            });
        } catch (\Throwable $e) {
            Log::error("[PayrollRunObserver] Failed to auto-post GL JE for PayrollRun #{$run->id}: {$e->getMessage()}", [
                'payroll_run_id' => $run->id,
                'exception' => $e,
            ]);
        }

        // 2. Notify HR manager who created the run
        try {
            if ($run->created_by) {
                $hrManager = User::find($run->created_by);
                if ($hrManager) {
                    $hrManager->notify(new PayrollApprovedNotification($run, 'approval'));
                    PayrollStatusChanged::dispatch($run, $hrManager->id, 'completed');
                }
            }

            // 3. Notify each employee: payslip ready
            $run->loadMissing('details.employee');
            foreach ($run->details as $detail) {
                $employeeUserId = $detail->employee?->getAttribute('user_id');
                if ($employeeUserId) {
                    $employeeUser = User::find($employeeUserId);
                    if ($employeeUser) {
                        $employeeUser->notify(new PayrollApprovedNotification($run, 'payslip'));
                        PayrollStatusChanged::dispatch($run, $employeeUserId, 'completed');
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[PayrollRunObserver] Notification dispatch failed for PayrollRun #{$run->id}: {$e->getMessage()}");
        }
    }
}
