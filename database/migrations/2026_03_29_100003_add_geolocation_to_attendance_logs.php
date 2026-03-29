<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add geolocation columns to attendance_logs for GPS-based time-in/out.
 *
 * This migration extends the existing attendance_logs table with:
 *  - GPS coordinates captured at time-in and time-out
 *  - Geofence validation results (distance, within-fence flag)
 *  - Device metadata (browser, OS, IP)
 *  - Override reason when employee clocks in outside geofence
 *  - Richer attendance_status alongside existing boolean flags
 *  - Flagging columns for HR review
 *  - Correction audit trail
 *  - Work location FK
 *
 * Also updates the source CHECK constraint to include 'web_clock' and 'leave_correction'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            // ── Work location reference ─────────────────────────────────────
            $table->foreignId('work_location_id')->nullable()
                ->after('overtime_request_id')
                ->constrained('work_locations')
                ->nullOnDelete();

            // ── Geolocation: time-in ────────────────────────────────────────
            $table->decimal('time_in_latitude', 10, 7)->nullable()->after('time_in');
            $table->decimal('time_in_longitude', 10, 7)->nullable()->after('time_in_latitude');
            $table->decimal('time_in_accuracy_meters', 8, 2)->nullable()->after('time_in_longitude');
            $table->decimal('time_in_distance_meters', 10, 2)->nullable()->after('time_in_accuracy_meters');
            $table->boolean('time_in_within_geofence')->nullable()->after('time_in_distance_meters');
            $table->jsonb('time_in_device_info')->nullable()->after('time_in_within_geofence');
            $table->text('time_in_override_reason')->nullable()->after('time_in_device_info');

            // ── Geolocation: time-out ───────────────────────────────────────
            $table->decimal('time_out_latitude', 10, 7)->nullable()->after('time_out');
            $table->decimal('time_out_longitude', 10, 7)->nullable()->after('time_out_latitude');
            $table->decimal('time_out_accuracy_meters', 8, 2)->nullable()->after('time_out_longitude');
            $table->decimal('time_out_distance_meters', 10, 2)->nullable()->after('time_out_accuracy_meters');
            $table->boolean('time_out_within_geofence')->nullable()->after('time_out_distance_meters');
            $table->jsonb('time_out_device_info')->nullable()->after('time_out_within_geofence');
            $table->text('time_out_override_reason')->nullable()->after('time_out_device_info');

            // ── Richer status (alongside existing boolean flags) ────────────
            $table->string('attendance_status', 30)->nullable()->after('is_processed')
                ->comment('Enum-like status for UI display: present|late|undertime|late_and_undertime|absent|on_leave|holiday|rest_day|overtime_only|out_of_office|pending|corrected|no_schedule');

            // ── Flagging for HR review ──────────────────────────────────────
            $table->boolean('is_flagged')->default(false)->after('attendance_status');
            $table->text('flag_reason')->nullable()->after('is_flagged');

            // ── Correction audit trail ──────────────────────────────────────
            $table->text('correction_note')->nullable()->after('flag_reason');
            $table->foreignId('corrected_by')->nullable()->after('correction_note')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('corrected_at')->nullable()->after('corrected_by');

            // ── Indexes ─────────────────────────────────────────────────────
            $table->index('attendance_status');
            $table->index('is_flagged');
        });

        // Update the source CHECK constraint to include new sources.
        // Drop old constraint and recreate with expanded list.
        DB::statement('ALTER TABLE attendance_logs DROP CONSTRAINT IF EXISTS chk_att_source');
        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT chk_att_source
            CHECK (source IN ('biometric','csv_import','manual','system','web_clock','leave_correction'))");

        // Add CHECK for attendance_status valid values
        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT chk_att_status
            CHECK (attendance_status IS NULL OR attendance_status IN (
                'present','late','undertime','late_and_undertime','absent',
                'on_leave','holiday','rest_day','overtime_only','out_of_office',
                'pending','corrected','no_schedule'
            ))");
    }

    public function down(): void
    {
        // Restore original source constraint
        DB::statement('ALTER TABLE attendance_logs DROP CONSTRAINT IF EXISTS chk_att_status');
        DB::statement('ALTER TABLE attendance_logs DROP CONSTRAINT IF EXISTS chk_att_source');
        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT chk_att_source
            CHECK (source IN ('biometric','csv_import','manual','system'))");

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['work_location_id']);
            $table->dropForeign(['corrected_by']);

            $table->dropIndex(['attendance_status']);
            $table->dropIndex(['is_flagged']);

            $table->dropColumn([
                'work_location_id',
                'time_in_latitude', 'time_in_longitude', 'time_in_accuracy_meters',
                'time_in_distance_meters', 'time_in_within_geofence',
                'time_in_device_info', 'time_in_override_reason',
                'time_out_latitude', 'time_out_longitude', 'time_out_accuracy_meters',
                'time_out_distance_meters', 'time_out_within_geofence',
                'time_out_device_info', 'time_out_override_reason',
                'attendance_status', 'is_flagged', 'flag_reason',
                'correction_note', 'corrected_by', 'corrected_at',
            ]);
        });
    }
};
