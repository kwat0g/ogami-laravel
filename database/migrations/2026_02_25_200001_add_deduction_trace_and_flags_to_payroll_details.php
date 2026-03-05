<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DED-004: Add deduction_stack_trace JSONB audit column.
 * EDGE-003/010: Add zero_pay flag for LWOP / zero-attendance employees.
 * DED-002: Add computation_error flag for NegativeNetPay / exception during pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            // DED-004 — full deduction audit trail per priority-stack slot
            $table->jsonb('deduction_stack_trace')->nullable()->after('has_deferred_deductions');

            // EDGE-003/010 — net pay = ₱0 (zero attendance, full LWOP)
            $table->boolean('zero_pay')->default(false)->after('deduction_stack_trace');

            // DED-002 — pipeline threw a recoverable exception (NegativeNetPay, ContributionTableNotFound, etc.)
            $table->boolean('computation_error')->default(false)->after('zero_pay');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn(['deduction_stack_trace', 'zero_pay', 'computation_error']);
        });
    }
};
