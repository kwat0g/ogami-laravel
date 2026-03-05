<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Supervisor approval fields (first level)
            $table->foreignId('supervisor_id')->nullable()->after('reviewed_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('reviewed_at');
            $table->string('supervisor_remarks')->nullable()->after('review_remarks');

            // Rename reviewed_by to manager_id for clarity (optional, keep for compatibility)
            // We keep reviewed_by as the final approver (manager)
        });

        // Update status enum to include supervisor_approved
        DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS leave_requests_status_check');
        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_status_check CHECK (status IN ('draft', 'submitted', 'supervisor_approved', 'approved', 'rejected', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn(['supervisor_id', 'supervisor_reviewed_at', 'supervisor_remarks']);
        });

        DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS leave_requests_status_check');
        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'rejected', 'cancelled'))");
    }
};
