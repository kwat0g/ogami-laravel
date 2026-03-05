<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds LN-007 (minimum wage protection) and EDGE case tracking columns
 * to payroll_details, required by the 8-step workflow v1.0.
 *
 * Columns:
 *  edge_cases_applied  — JSON array of edge case codes applied (e.g. ["EDGE-001","EDGE-005"])
 *  ln007_applied       — TRUE when loan deductions were truncated to protect min wage
 *  ln007_truncated_amt — centavos that were truncated from loan deductions this period
 *  ln007_carried_fwd   — centavos deferred to the next payroll period
 *  employee_flag       — HR review flag ('none'|'flagged'|'resolved'), set in Step 5
 *  review_note         — Optional note added during Step 5 review
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->jsonb('edge_cases_applied')->nullable()->after('deduction_stack_trace');
            $table->boolean('ln007_applied')->default(false)->after('edge_cases_applied');
            $table->unsignedBigInteger('ln007_truncated_amt')->default(0)->after('ln007_applied');
            $table->unsignedBigInteger('ln007_carried_fwd')->default(0)->after('ln007_truncated_amt');
            $table->string('employee_flag', 20)->default('none')->after('ln007_carried_fwd');
            $table->text('review_note')->nullable()->after('employee_flag');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn([
                'edge_cases_applied', 'ln007_applied',
                'ln007_truncated_amt', 'ln007_carried_fwd',
                'employee_flag', 'review_note',
            ]);
        });
    }
};
