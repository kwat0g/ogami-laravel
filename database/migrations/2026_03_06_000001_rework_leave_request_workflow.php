<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reworks the leave_requests workflow from a 2-step (supervisor → manager)
 * pipeline to the 4-step chain documented on physical form AD-084-00:
 *
 *   submitted → head_approved → manager_checked → ga_processed → approved
 *                                                             ↘ rejected (GA disapproves)
 *
 * Removes old supervisor / executive columns and adds the new per-step columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Drop old CHECK constraint ─────────────────────────────────────
        DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS leave_requests_status_check');

        // ── 2. Drop old columns ──────────────────────────────────────────────
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropForeign(['executive_id']);
            $table->dropColumn([
                'requester_role',
                'supervisor_id',
                'supervisor_remarks',
                'supervisor_reviewed_at',
                'executive_id',
                'executive_remarks',
                'executive_reviewed_at',
            ]);
        });

        // ── 3. Add new workflow columns ──────────────────────────────────────
        Schema::table('leave_requests', function (Blueprint $table) {
            // Step 2 — Department Head (Approved By on form)
            $table->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete()->after('reason');
            $table->text('head_remarks')->nullable()->after('head_id');
            $table->timestamp('head_approved_at')->nullable()->after('head_remarks');

            // Step 3 — Plant Manager (Checked By on form)
            $table->foreignId('manager_checked_by')->nullable()->constrained('users')->nullOnDelete()->after('head_approved_at');
            $table->text('manager_check_remarks')->nullable()->after('manager_checked_by');
            $table->timestamp('manager_checked_at')->nullable()->after('manager_check_remarks');

            // Step 4 — GA Officer (Received By / HR Personnel Use on form)
            $table->foreignId('ga_processed_by')->nullable()->constrained('users')->nullOnDelete()->after('manager_checked_at');
            $table->text('ga_remarks')->nullable()->after('ga_processed_by');
            $table->timestamp('ga_processed_at')->nullable()->after('ga_remarks');
            $table->string('action_taken')->nullable()->after('ga_processed_at');
            // Balance snapshot captured when GA processes
            $table->decimal('beginning_balance', 5, 2)->nullable()->after('action_taken');
            $table->decimal('applied_days', 5, 2)->nullable()->after('beginning_balance');
            $table->decimal('ending_balance', 5, 2)->nullable()->after('applied_days');

            // Step 5 — Vice President (Noted By on form)
            $table->foreignId('vp_id')->nullable()->constrained('users')->nullOnDelete()->after('ending_balance');
            $table->text('vp_remarks')->nullable()->after('vp_id');
            $table->timestamp('vp_noted_at')->nullable()->after('vp_remarks');
        });

        // ── 4. Migrate any in-flight records to nearest equivalent status ────
        DB::statement("
            UPDATE leave_requests
            SET status = 'submitted'
            WHERE status = 'pending_executive'
        ");
        DB::statement("
            UPDATE leave_requests
            SET status = 'head_approved'
            WHERE status = 'supervisor_approved'
        ");

        // ── 5. Apply new CHECK constraint ─────────────────────────────────────
        DB::statement("
            ALTER TABLE leave_requests
            ADD CONSTRAINT leave_requests_status_check
            CHECK (status IN (
                'draft',
                'submitted',
                'head_approved',
                'manager_checked',
                'ga_processed',
                'approved',
                'rejected',
                'cancelled'
            ))
        ");

        // ── 6. Add CHECK constraint for action_taken ──────────────────────────
        DB::statement("
            ALTER TABLE leave_requests
            ADD CONSTRAINT leave_requests_action_taken_check
            CHECK (action_taken IS NULL OR action_taken IN (
                'approved_with_pay',
                'approved_without_pay',
                'disapproved'
            ))
        ");
    }

    public function down(): void
    {
        // Drop new constraints
        DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS leave_requests_status_check');
        DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS leave_requests_action_taken_check');

        // Drop new columns
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['head_id']);
            $table->dropForeign(['manager_checked_by']);
            $table->dropForeign(['ga_processed_by']);
            $table->dropForeign(['vp_id']);
            $table->dropColumn([
                'head_id', 'head_remarks', 'head_approved_at',
                'manager_checked_by', 'manager_check_remarks', 'manager_checked_at',
                'ga_processed_by', 'ga_remarks', 'ga_processed_at',
                'action_taken', 'beginning_balance', 'applied_days', 'ending_balance',
                'vp_id', 'vp_remarks', 'vp_noted_at',
            ]);
        });

        // Re-add old columns
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('requester_role')->default('staff')->after('reason');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete()->after('requester_role');
            $table->text('supervisor_remarks')->nullable()->after('supervisor_id');
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('supervisor_remarks');
            $table->foreignId('executive_id')->nullable()->constrained('users')->nullOnDelete()->after('supervisor_reviewed_at');
            $table->text('executive_remarks')->nullable()->after('executive_id');
            $table->timestamp('executive_reviewed_at')->nullable()->after('executive_remarks');
        });

        // Restore old CHECK constraint
        DB::statement("
            ALTER TABLE leave_requests
            ADD CONSTRAINT leave_requests_status_check
            CHECK (status IN (
                'draft', 'submitted', 'supervisor_approved',
                'approved', 'rejected', 'cancelled', 'pending_executive'
            ))
        ");
    }
};
