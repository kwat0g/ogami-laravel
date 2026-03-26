<?php

use App\Jobs\Accounting\FlagStaleJournalEntriesJob;
use App\Jobs\AP\SendApDailyDigestJob;
use App\Jobs\AP\SendApDueDateAlertJob;
use App\Jobs\Leave\RunLeaveAccrualJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Accounting: JE-010 stale draft detection ─────────────────────────────────
// Runs daily at 02:00 AM. Flags draft JEs idle ≥ stale_draft_days → stale,
// then stale JEs idle ≥ je_cancel_days → cancelled.
Schedule::job(new FlagStaleJournalEntriesJob)->dailyAt('02:00');

// ── AP: Due date alerts ───────────────────────────────────────────────────────
// Runs daily at 08:00 AM. Logs overdue and due-soon AP invoices.
// Alert window (days) is read from system_settings key: ap.due_date_alert_days.
Schedule::job(new SendApDueDateAlertJob)->dailyAt('08:00');

// ── AP: Daily digest (Phase 1D) ───────────────────────────────────────────────
// Runs every weekday at 08:05 AM (after due-date alerts).
// Sends a management-level summary: outstanding balance, pending count, overdue.
Schedule::job(new SendApDailyDigestJob)->weekdays()->at('08:05');

// ── Leave: Monthly accrual (LV-002) ───────────────────────────────────────────
// Runs on the 1st of each month at 01:00 AM.
// Credits leave balances for all active employees.
Schedule::call(function () {
    $now = Carbon::now();
    dispatch(new RunLeaveAccrualJob($now->year, $now->month));
})->monthlyOn(1, '01:00')->name('leave.accrue-monthly')->withoutOverlapping();

// ── Leave: Year-end carry-over (LV-003) ───────────────────────────────────────
// Runs on January 1st at 02:00 AM.
// Processes carry-over and creates new year leave balances.
Schedule::command('leave:renew')->yearlyOn(1, 1, '02:00')
    ->name('leave.renew-yearly')
    ->withoutOverlapping();

// ── Backup ────────────────────────────────────────────────────────────────────
// Daily DB backup at 02:30 AM — runs after the stale JE job.
// Retention policy: config/backup.php (14 daily → 30 daily → 12 weekly → 12 monthly → 5 yearly).
Schedule::command('backup:run --only-db')
    ->dailyAt('02:30')
    ->name('backup.daily-db')
    ->withoutOverlapping()
    ->onFailure(function () {
        // Admin will see a mail notification from Spatie Backup.
        Log::error('[Backup] Daily DB backup FAILED');
    });

// Weekly cleanup — remove backups that no longer satisfy the retention policy.
Schedule::command('backup:clean')->weekly()->at('03:00');

// ── CRM: Expire stale client order negotiations ───────────────────────────────
// Runs daily at 09:00 AM. Flags negotiations that exceeded SLA deadline (default: 7 days).
Schedule::command('crm:expire-stale-negotiations')
    ->dailyAt('09:00')
    ->name('crm.expire-stale-negotiations')
    ->withoutOverlapping();

// ── CRM: SLA breach detection ─────────────────────────────────────────────────
// Runs every 15 minutes. Sets sla_breached_at on tickets that have passed their
// SLA deadline without being resolved or closed.
Schedule::command('crm:mark-sla-breaches')
    ->everyFifteenMinutes()
    ->name('crm.sla-breach-check')
    ->withoutOverlapping();

// ── Backup integrity verification ────────────────────────────────────────────
// Weekly on Sundays at 04:00 AM — after the Sunday backup:clean run.
// Restores latest backup to ogami_erp_restore_test, runs GoldenSuiteTest.
// On failure, sends email to BACKUP_NOTIFY_EMAIL.
Schedule::command('backup:verify --skip-backup')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->name('backup.verify-restore')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('[Backup] Weekly restore verification FAILED');
    });

// ── Horizon metrics snapshot ─────────────────────────────────────────────────
// Required for the Horizon metrics graph to populate.
// Runs every 5 minutes in production; every 15 minutes locally.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// ── Pulse: ingest aggregated metrics ─────────────────────────────────────────
// Pulse records data in a streaming buffer; this schedule flushes it to the
// pulse_aggregates table for dashboard display.
Schedule::command('pulse:check')->everyMinute()->withoutOverlapping();

// ── AR: Overdue invoice notifications ────────────────────────────────────────
// Runs daily at 08:30 AM. Checks for overdue invoices and notifies relevant users.
Schedule::command('ar:check-overdue')
    ->dailyAt('08:30')
    ->name('ar.check-overdue')
    ->withoutOverlapping();

// ── Maintenance: Auto-generate PM work orders ────────────────────────────────
// Runs daily at 06:00 AM. Creates preventive maintenance work orders from due schedules.
Schedule::command('maintenance:generate-pm-work-orders')
    ->dailyAt('06:00')
    ->name('maintenance.auto-pm')
    ->withoutOverlapping();

// ── Mold: Shot count threshold alerts ────────────────────────────────────────
// Runs daily at 06:30 AM. Checks molds approaching max shot count.
Schedule::command('mold:check-shot-counts')
    ->dailyAt('06:30')
    ->name('mold.shot-alerts')
    ->withoutOverlapping();

// ── Inventory: Reorder point alerts ──────────────────────────────────────────
// Runs daily at 07:00 AM. Notifies when stock falls below reorder point.
// Also auto-creates draft PRs for low stock items.
Schedule::command('inventory:check-reorder-points --auto-create-pr')
    ->dailyAt('07:00')
    ->name('inventory.reorder-alerts')
    ->withoutOverlapping();

// ── HR: Leave balance auto-accrual ───────────────────────────────────────────
// Runs on the 1st of each month at 02:00 AM. Accrues leave based on tenure.
Schedule::command('leave:accrue-balances')
    ->monthlyOn(1, '02:00')
    ->name('leave.accrue-balances')
    ->withoutOverlapping();

// ── Inventory: Expire old stock reservations ─────────────────────────────────
// Runs daily at 01:00 AM. Cleans up expired reservations.
Schedule::command('inventory:expire-reservations')
    ->dailyAt('01:00')
    ->name('inventory.expire-reservations')
    ->withoutOverlapping();
