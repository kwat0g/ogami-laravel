<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance correction requests — formal workflow for employees to
 * request changes to their attendance records.
 *
 * StateMachine: draft → submitted → approved / rejected
 *               rejected → draft (resubmission allowed)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('attendance_log_id')
                ->constrained('attendance_logs')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->string('correction_type', 20);
            $table->timestamp('requested_time_in')->nullable();
            $table->timestamp('requested_time_out')->nullable();
            $table->text('requested_remarks')->nullable();
            $table->text('reason');
            $table->string('supporting_document_path')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE attendance_correction_requests ADD CONSTRAINT chk_acr_type
            CHECK (correction_type IN ('time_in','time_out','status','both'))");

        DB::statement("ALTER TABLE attendance_correction_requests ADD CONSTRAINT chk_acr_status
            CHECK (status IN ('draft','submitted','approved','rejected','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
    }
};
