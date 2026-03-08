<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the approved_minutes column to overtime_requests.
 *
 * This column was referenced in the OvertimeRequest model, OvertimeRequestService,
 * PayrollComputationContext (Step03AttendanceSummaryStep), and OvertimeRequestResource
 * but was never added to the schema. Its absence caused:
 *  - GoldenSuiteTest (Unit/Payroll) to fail with SQLSTATE[42703]: column does not exist
 *  - The weekly backup:verify command (scheduled Sundays 04:00) to fail
 *  - Because backup:verify runs Unit/Payroll tests which use RefreshDatabase,
 *    migrate:fresh ran against the wrong database and wiped production data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->unsignedInteger('approved_minutes')
                ->nullable()
                ->after('requested_minutes')
                ->comment('Manager-approved OT duration in minutes; null until approved');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->dropColumn('approved_minutes');
        });
    }
};
