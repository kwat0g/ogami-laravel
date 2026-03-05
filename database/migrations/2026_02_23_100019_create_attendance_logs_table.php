<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance logs — one row per employee per work date.
 *
 * ATT rules enforced here (constraints) or in service (logic):
 *  ATT-001 : late_minutes >= 0
 *  ATT-002 : undertime_minutes >= 0
 *  ATT-003 : night_diff_minutes computed by service
 *  ATT-004 : night_diff_hours — 10 PM–6 AM window
 *  ATT-005 : overtime_minutes comes only from approved OT request
 *  ATT-006 : absent flag
 *  ATT-007 : rest_day flag from shift schedule
 *  ATT-008 : holiday type from holiday_calendars
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->date('work_date');

            // ── Raw biometric / time-in-out ───────────────────────────────────
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->string('source', 30)->default('manual')
                ->comment('biometric|csv_import|manual|system');

            // ── Computed fields (set by AttendanceProcessingService) ──────────
            $table->boolean('is_present')->default(false);
            $table->boolean('is_absent')->default(false);
            $table->boolean('is_rest_day')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_type', 20)->nullable()
                ->comment('regular|special_non_working|special_working');
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('undertime_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('night_diff_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->foreignId('overtime_request_id')->nullable()
                ->constrained('overtime_requests')
                ->nullOnDelete();
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();

            // ── Import metadata ───────────────────────────────────────────────
            $table->string('import_batch_id', 36)->nullable();  // UUID of the import job
            $table->text('processing_notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date'], 'uq_attendance_per_day');
            $table->index('work_date');
            $table->index('is_processed');
            $table->index('import_batch_id');
        });

        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT chk_att_source
            CHECK (source IN ('biometric','csv_import','manual','system'))");

        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT chk_att_holiday_type
            CHECK (holiday_type IS NULL OR holiday_type IN ('regular','special_non_working','special_working'))");

        DB::statement('ALTER TABLE attendance_logs ADD CONSTRAINT chk_att_mutual_exclusion
            CHECK (NOT (is_present = true AND is_absent = true))');

        DB::statement('ALTER TABLE attendance_logs ADD CONSTRAINT chk_att_worked_minutes
            CHECK (worked_minutes BETWEEN 0 AND 1440)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
