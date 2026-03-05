<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow Design v1.0 — Expand payroll run to support 8-step wizard.
 *
 * 1. Drop old status CHECK and replace with full 14-state enum.
 * 2. Drop old run_type CHECK, add adjustment + year_end_reconciliation.
 * 3. Add workflow audit columns: scope_confirmed_at, pre_run_acknowledged_at, etc.
 * 4. Add pay_period_id FK (nullable — links to pay_periods for OPEN period gate).
 * 5. Add hr_approved_by_id / acctg_approved_by_id for granular SoD constraints.
 * 6. Add DB-level SoD CHECK constraints for HR and Accounting approvals.
 * 7. Add scope JSON columns (departments, positions, employment_types filters).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1 ─ Status constraint ────────────────────────────────────────────────
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_status');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_status
            CHECK (status IN (
                'DRAFT','SCOPE_SET','PRE_RUN_CHECKED',
                'PROCESSING','COMPUTED','REVIEW',
                'SUBMITTED','HR_APPROVED','ACCTG_APPROVED',
                'DISBURSED','PUBLISHED',
                'FAILED','RETURNED','REJECTED',
                -- legacy lowercase values kept for migration safety
                'draft','locked','processing','completed',
                'submitted','approved','posted','failed','cancelled'
            ))
        ");

        // 2 ─ Run type constraint ──────────────────────────────────────────────
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_run_type');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_run_type
            CHECK (run_type IN (
                'regular','thirteenth_month','adjustment',
                'year_end_reconciliation','final_pay'
            ))
        ");

        Schema::table('payroll_runs', function (Blueprint $table) {
            // 3 ─ Workflow audit timestamps (skip if column already exists)
            if (! Schema::hasColumn('payroll_runs', 'scope_confirmed_at')) {
                $table->timestamp('scope_confirmed_at')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('payroll_runs', 'pre_run_checked_at')) {
                $table->timestamp('pre_run_checked_at')->nullable()->after('scope_confirmed_at');
            }
            if (! Schema::hasColumn('payroll_runs', 'pre_run_acknowledged_at')) {
                $table->timestamp('pre_run_acknowledged_at')->nullable()->after('pre_run_checked_at');
            }
            if (! Schema::hasColumn('payroll_runs', 'pre_run_acknowledged_by_id')) {
                $table->foreignId('pre_run_acknowledged_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('pre_run_acknowledged_at');
            }
            if (! Schema::hasColumn('payroll_runs', 'computation_started_at')) {
                $table->timestamp('computation_started_at')->nullable()->after('pre_run_acknowledged_by_id');
            }
            if (! Schema::hasColumn('payroll_runs', 'computation_completed_at')) {
                $table->timestamp('computation_completed_at')->nullable()->after('computation_started_at');
            }
            if (! Schema::hasColumn('payroll_runs', 'progress_json')) {
                $table->jsonb('progress_json')->nullable()->after('computation_completed_at');
            }
            if (! Schema::hasColumn('payroll_runs', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('progress_json');
            }
            if (! Schema::hasColumn('payroll_runs', 'publish_scheduled_at')) {
                $table->timestamp('publish_scheduled_at')->nullable()->after('published_at');
            }

            // 4 ─ Pay period FK (already exists in prior migration, skip) ──────
            // pay_period_id was added in a prior migration — skipped here.

            // 5 ─ Granular approver columns ────────────────────────────────────
            if (! Schema::hasColumn('payroll_runs', 'hr_approved_by_id')) {
                $table->foreignId('hr_approved_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('approved_by');
            }
            if (! Schema::hasColumn('payroll_runs', 'hr_approved_at')) {
                $table->timestamp('hr_approved_at')->nullable()->after('hr_approved_by_id');
            }
            if (! Schema::hasColumn('payroll_runs', 'acctg_approved_by_id')) {
                $table->foreignId('acctg_approved_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('hr_approved_at');
            }
            if (! Schema::hasColumn('payroll_runs', 'acctg_approved_at')) {
                $table->timestamp('acctg_approved_at')->nullable()->after('acctg_approved_by_id');
            }

            // 7 ─ Scope JSON filters ───────────────────────────────────────────
            if (! Schema::hasColumn('payroll_runs', 'scope_departments')) {
                $table->jsonb('scope_departments')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('payroll_runs', 'scope_positions')) {
                $table->jsonb('scope_positions')->nullable()->after('scope_departments');
            }
            if (! Schema::hasColumn('payroll_runs', 'scope_employment_types')) {
                $table->jsonb('scope_employment_types')->nullable()->after('scope_positions');
            }
            if (! Schema::hasColumn('payroll_runs', 'scope_include_unpaid_leave')) {
                $table->boolean('scope_include_unpaid_leave')->default(false)->after('scope_employment_types');
            }
            if (! Schema::hasColumn('payroll_runs', 'scope_include_probation_end')) {
                $table->boolean('scope_include_probation_end')->default(false)->after('scope_include_unpaid_leave');
            }
        });

        // 6 ─ DB-level SoD CHECK constraints ──────────────────────────────────
        DB::statement('
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_sod_payroll_hr
            CHECK (hr_approved_by_id IS NULL OR hr_approved_by_id != initiated_by_id)
        ');
        DB::statement('
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_sod_payroll_acctg
            CHECK (acctg_approved_by_id IS NULL OR acctg_approved_by_id != initiated_by_id)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll_hr');
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll_acctg');

        $toDrop = [
            'pre_run_acknowledged_by_id', 'hr_approved_by_id', 'acctg_approved_by_id',
            'scope_confirmed_at', 'pre_run_checked_at', 'pre_run_acknowledged_at',
            'computation_started_at', 'computation_completed_at',
            'progress_json', 'published_at', 'publish_scheduled_at',
            'hr_approved_at', 'acctg_approved_at',
            'scope_departments', 'scope_positions',
            'scope_employment_types', 'scope_include_unpaid_leave',
            'scope_include_probation_end',
        ];

        $existing = collect(Schema::getColumnListing('payroll_runs'));
        $actualDrop = array_filter($toDrop, fn ($c) => $existing->contains($c));

        Schema::table('payroll_runs', function (Blueprint $table) use ($actualDrop) {
            if (! empty($actualDrop)) {
                $table->dropColumn(array_values($actualDrop));
            }
        });

        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll_hr');
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll_acctg');

        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_status');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_status
            CHECK (status IN (
                'draft','locked','processing','completed',
                'submitted','approved','posted','failed','cancelled'
            ))
        ");

        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_run_type');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_run_type
            CHECK (run_type IN ('regular','thirteenth_month','final_pay'))
        ");
    }
};
