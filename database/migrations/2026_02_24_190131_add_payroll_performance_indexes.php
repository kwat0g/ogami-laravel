<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes — Phase 1E Sprint 18
 *
 * Issues identified via EXPLAIN ANALYZE on critical payroll/accounting paths:
 *
 * 1. payroll_runs: YTD accumulation (step04LoadYtd) uses whereYear() which generates
 *    date_part('year', cutoff_end) — a function-based filter that cannot use btree.
 *    Fix: add (status, cutoff_end) composite index; query updated to use date ranges.
 *
 * 2. leave_requests: payroll step03 inner query uses employee_id + status + date_from
 *    together. Existing separate indexes are less efficient than a single composite.
 *
 * 3. attendance_logs: partial index on (employee_id, work_date) WHERE is_present = true
 *    eliminates scanning absent-day rows for the payroll computation hot path.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. payroll_runs: supports YTD accumulation
        //    WHERE status = 'completed' AND cutoff_end >= '2025-01-01' AND cutoff_end < '2026-01-01'
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->index(['status', 'cutoff_end'], 'idx_payroll_runs_status_cutoff_end');
        });

        // 2. leave_requests: covering composite for payroll leave scope
        //    WHERE employee_id = ? AND status = 'approved' AND date_from BETWEEN ?
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index(
                ['employee_id', 'status', 'date_from'],
                'idx_leave_requests_emp_status_from',
            );
        });

        // 3. Partial index: attendance_logs — only present-day rows for payroll computation
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_att_logs_emp_date_present '
            .'ON attendance_logs (employee_id, work_date) '
            .'WHERE is_present = true',
        );
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropIndex('idx_payroll_runs_status_cutoff_end');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('idx_leave_requests_emp_status_from');
        });

        DB::statement('DROP INDEX IF EXISTS idx_att_logs_emp_date_present');
    }
};
