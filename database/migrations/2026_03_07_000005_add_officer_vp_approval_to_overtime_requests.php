<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C1: Extend OT approval workflow to 5 steps:
 *   pending → supervisor_approved → manager_checked → officer_reviewed → approved (VP)
 *
 * Adds tracking columns for the two new intermediate steps and extends the
 * status CHECK constraint to allow the new statuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->foreignId('officer_reviewed_by')->nullable()->constrained('users')->nullOnDelete()
                ->after('executive_approved_at');
            $table->timestamp('officer_reviewed_at')->nullable()->after('officer_reviewed_by');
            $table->foreignId('vp_approved_by')->nullable()->constrained('users')->nullOnDelete()
                ->after('officer_reviewed_at');
            $table->timestamp('vp_approved_at')->nullable()->after('vp_approved_by');
        });

        // Extend the status CHECK constraint to include the two new intermediate states
        DB::statement('ALTER TABLE overtime_requests DROP CONSTRAINT IF EXISTS overtime_requests_status_check');
        DB::statement(
            "ALTER TABLE overtime_requests ADD CONSTRAINT overtime_requests_status_check
             CHECK (status IN (
                 'pending',
                 'supervisor_approved',
                 'manager_checked',
                 'officer_reviewed',
                 'pending_executive',
                 'approved',
                 'rejected',
                 'cancelled'
             ))"
        );
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropForeign(['officer_reviewed_by']);
            $table->dropForeign(['vp_approved_by']);
            $table->dropColumn(['officer_reviewed_by', 'officer_reviewed_at', 'vp_approved_by', 'vp_approved_at']);
        });

        DB::statement('ALTER TABLE overtime_requests DROP CONSTRAINT IF EXISTS overtime_requests_status_check');
        DB::statement(
            "ALTER TABLE overtime_requests ADD CONSTRAINT overtime_requests_status_check
             CHECK (status IN ('pending','supervisor_approved','pending_executive','approved','rejected','cancelled'))"
        );
    }
};
