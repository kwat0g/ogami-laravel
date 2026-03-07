<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `remarks` and `processed_by` columns to attendance_logs.
 *
 * These columns are referenced by AttendanceLogController and the
 * AttendanceLog model but were absent from the original migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('processing_notes');
            $table->foreignId('processed_by')->nullable()
                ->after('remarks')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn('remarks');
        });
    }
};
