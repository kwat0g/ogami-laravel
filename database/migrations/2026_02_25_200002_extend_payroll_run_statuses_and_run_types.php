<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 10 — State machine expansion.
 *
 * Extends payroll_runs:
 *   1. status CHECK constraint: adds submitted | approved | posted | failed
 *   2. run_type CHECK constraint: adds final_pay  (EDGE-002)
 *   3. Workflow audit columns: submitted_by, submitted_at, failure_reason
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Drop old status constraint, create wider one ───────────────────
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_status');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_status
            CHECK (status IN (
                'draft','locked','processing',
                'completed','submitted','approved','posted',
                'failed','cancelled'
            ))
        ");

        // ── 2. Drop old run_type constraint, add final_pay ────────────────────
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_run_type');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_run_type
            CHECK (run_type IN ('regular','thirteenth_month','final_pay'))
        ");

        // ── 3. Workflow audit columns ─────────────────────────────────────────
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_by');
            $table->timestamp('submitted_at')->nullable()->after('approved_at');
            $table->timestamp('posted_at')->nullable()->after('submitted_at');
            $table->text('failure_reason')->nullable()->after('posted_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['submitted_at', 'posted_at', 'failure_reason']);
        });

        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_status');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_status
            CHECK (status IN ('draft','locked','processing','completed','cancelled'))
        ");

        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_pr_run_type');
        DB::statement("
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_pr_run_type
            CHECK (run_type IN ('regular','thirteenth_month'))
        ");
    }
};
