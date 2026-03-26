<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 1C: Extend the loans table with a 5-stage approval chain.
 *
 * Strategy: A `workflow_version` discriminator column separates in-flight data:
 *   - workflow_version = 1 → legacy 3-stage chain (unchanged)
 *   - workflow_version = 2 → new 5-stage chain (head → manager → officer → vp)
 *
 * The expanded status CHECK also fixes an existing gap: the old constraint only
 * listed 6 of the 9 statuses that application code already uses.
 *
 * SoD DB constraints enforce that consecutive approvers must be different users.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Expand the status CHECK to cover all statuses (v1 + v2) ──────
        // Drop the constraint created in 2026_02_23_100024_create_loans_table.php
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_loan_status');
        // Also drop loans_status_check in case it was created by an intermediate version
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS loans_status_check');

        DB::statement("
            ALTER TABLE loans ADD CONSTRAINT loans_status_check
            CHECK (status IN (
                -- v1 statuses (preserve all, including ones missing from original constraint)
                'pending','supervisor_approved','approved','ready_for_disbursement',
                'active','fully_paid','cancelled','written_off','rejected',
                -- v2 new statuses
                'head_noted','manager_checked','officer_reviewed','vp_approved','disbursing'
            ))
        ");

        // ── 2. Add workflow_version discriminator + v2 approval columns ─────
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedSmallInteger('workflow_version')->default(1)->after('loan_type_id');

            // v2 approval chain — Head (Step 1)
            $table->foreignId('head_noted_by')
                ->nullable()->constrained('users')->nullOnDelete()->after('workflow_version');
            $table->timestamp('head_noted_at')->nullable()->after('head_noted_by');
            $table->text('head_remarks')->nullable()->after('head_noted_at');

            // v2 approval chain — Manager (Step 2)
            $table->foreignId('manager_checked_by')
                ->nullable()->constrained('users')->nullOnDelete()->after('head_remarks');
            $table->timestamp('manager_checked_at')->nullable()->after('manager_checked_by');
            $table->text('manager_remarks')->nullable()->after('manager_checked_at');

            // v2 approval chain — Officer (Step 3)
            $table->foreignId('officer_reviewed_by')
                ->nullable()->constrained('users')->nullOnDelete()->after('manager_remarks');
            $table->timestamp('officer_reviewed_at')->nullable()->after('officer_reviewed_by');
            $table->text('officer_remarks')->nullable()->after('officer_reviewed_at');

            // v2 approval chain — Vice President (Step 4)
            $table->foreignId('vp_approved_by')
                ->nullable()->constrained('users')->nullOnDelete()->after('officer_remarks');
            $table->timestamp('vp_approved_at')->nullable()->after('vp_approved_by');
            $table->text('vp_remarks')->nullable()->after('vp_approved_at');
        });

        // ── 3. SoD DB-level constraints for the v2 chain ────────────────────
        DB::statement('
            ALTER TABLE loans
            ADD CONSTRAINT chk_sod_loan_head
                CHECK (head_noted_by IS NULL OR head_noted_by <> requested_by),
            ADD CONSTRAINT chk_sod_loan_manager
                CHECK (manager_checked_by IS NULL OR manager_checked_by <> head_noted_by),
            ADD CONSTRAINT chk_sod_loan_officer
                CHECK (officer_reviewed_by IS NULL OR officer_reviewed_by <> manager_checked_by),
            ADD CONSTRAINT chk_sod_loan_vp
                CHECK (vp_approved_by IS NULL OR vp_approved_by <> officer_reviewed_by)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_head');
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_manager');
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_officer');
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_vp');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('head_noted_by');
            $table->dropColumn(['head_noted_at', 'head_remarks']);

            $table->dropConstrainedForeignId('manager_checked_by');
            $table->dropColumn(['manager_checked_at', 'manager_remarks']);

            $table->dropConstrainedForeignId('officer_reviewed_by');
            $table->dropColumn(['officer_reviewed_at', 'officer_remarks']);

            $table->dropConstrainedForeignId('vp_approved_by');
            $table->dropColumn(['vp_approved_at', 'vp_remarks']);

            $table->dropColumn('workflow_version');
        });

        // Restore original (partial) constraint
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS loans_status_check');
        DB::statement("
            ALTER TABLE loans ADD CONSTRAINT loans_status_check
            CHECK (status IN ('pending','approved','active','fully_paid','cancelled','written_off'))
        ");
    }
};
