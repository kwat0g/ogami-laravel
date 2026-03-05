<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds 2-level approval workflow to overtime_requests:
 *
 *   Staff OT   : pending → supervisor_approved → approved
 *   Supervisor : pending → approved (manager directly)
 *   Manager    : pending_executive → approved (executive)
 *
 * Mirror of leave_requests supervisor/executive columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            // Requester role — determines workflow path
            $table->string('requester_role', 20)->nullable()
                ->after('requested_by')
                ->comment('staff|supervisor|manager — determines approval path');

            // Supervisor first-level endorsement
            $table->foreignId('supervisor_id')->nullable()
                ->after('reviewed_by')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Supervisor who endorsed the OT (staff requests only)');
            $table->string('supervisor_remarks', 500)->nullable()
                ->after('supervisor_id');
            $table->timestamp('supervisor_approved_at')->nullable()
                ->after('supervisor_remarks');

            // Executive approval for manager-filed requests
            $table->foreignId('executive_id')->nullable()
                ->after('supervisor_approved_at')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Executive who approved manager-filed OT');
            $table->string('executive_remarks', 500)->nullable()
                ->after('executive_id');
            $table->timestamp('executive_approved_at')->nullable()
                ->after('executive_remarks');
        });

        // Extend status enum to include new states
        DB::statement('ALTER TABLE overtime_requests DROP CONSTRAINT IF EXISTS overtime_requests_status_check');
        DB::statement(
            "ALTER TABLE overtime_requests ADD CONSTRAINT overtime_requests_status_check
             CHECK (status IN ('pending','supervisor_approved','pending_executive','approved','rejected','cancelled'))"
        );
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropForeign(['executive_id']);
            $table->dropColumn([
                'requester_role',
                'supervisor_id',
                'supervisor_remarks',
                'supervisor_approved_at',
                'executive_id',
                'executive_remarks',
                'executive_approved_at',
            ]);
        });

        DB::statement('ALTER TABLE overtime_requests DROP CONSTRAINT IF EXISTS overtime_requests_status_check');
        DB::statement(
            "ALTER TABLE overtime_requests ADD CONSTRAINT overtime_requests_status_check
             CHECK (status IN ('pending','approved','rejected','cancelled'))"
        );
    }
};
