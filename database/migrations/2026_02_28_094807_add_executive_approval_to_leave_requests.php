<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Executive approval for manager-filed requests
            $table->foreignId('executive_id')->nullable()->after('reviewed_by')
                ->constrained('users')->nullOnDelete();
            $table->string('executive_remarks')->nullable()->after('review_remarks');
            $table->timestamp('executive_reviewed_at')->nullable()->after('reviewed_at');

            // Track requester role for workflow routing
            $table->string('requester_role', 20)->nullable()->after('submitted_by')
                ->comment('staff|supervisor|manager - determines approval path');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['executive_id']);
            $table->dropColumn([
                'executive_id',
                'executive_remarks',
                'executive_reviewed_at',
                'requester_role',
            ]);
        });
    }
};
