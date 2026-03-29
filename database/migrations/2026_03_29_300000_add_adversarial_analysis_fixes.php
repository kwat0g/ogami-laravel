<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adversarial analysis fixes requiring schema changes.
 *
 * REC-20: Payroll processing progress tracking columns on payroll_runs.
 * REC-24: Attendance freeze flag on pay_periods.
 */
return new class extends Migration
{
    public function up(): void
    {
        // REC-20: Payroll progress tracking
        if (Schema::hasTable('payroll_runs')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_runs', 'processing_started_at')) {
                    $table->timestamp('processing_started_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('payroll_runs', 'total_employees')) {
                    $table->unsignedInteger('total_employees')->default(0)->after('processing_started_at');
                }
                if (! Schema::hasColumn('payroll_runs', 'processed_employees')) {
                    $table->unsignedInteger('processed_employees')->default(0)->after('total_employees');
                }
                if (! Schema::hasColumn('payroll_runs', 'processing_failure_reason')) {
                    $table->string('processing_failure_reason')->nullable()->after('processed_employees');
                }
            });
        }

        // REC-24: Attendance freeze during payroll processing
        if (Schema::hasTable('pay_periods')) {
            Schema::table('pay_periods', function (Blueprint $table) {
                if (! Schema::hasColumn('pay_periods', 'attendance_frozen')) {
                    $table->boolean('attendance_frozen')->default(false)->after('status');
                }
                if (! Schema::hasColumn('pay_periods', 'attendance_frozen_at')) {
                    $table->timestamp('attendance_frozen_at')->nullable()->after('attendance_frozen');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_runs')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->dropColumn([
                    'processing_started_at',
                    'total_employees',
                    'processed_employees',
                    'processing_failure_reason',
                ]);
            });
        }

        if (Schema::hasTable('pay_periods')) {
            Schema::table('pay_periods', function (Blueprint $table) {
                $table->dropColumn(['attendance_frozen', 'attendance_frozen_at']);
            });
        }
    }
};
