<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll Adjustments — ad-hoc earnings or deductions attached to a payroll run.
 *
 * Examples: rice allowance, clothing allowance, bonus, charge-back, reimbursement.
 * type = 'earning'  → adds to gross_pay
 * type = 'deduction' → subtracts from net_pay (after tax base if nature = 'non_taxable')
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->string('type', 20);           // earning|deduction
            $table->string('nature', 20)->default('taxable'); // taxable|non_taxable
            $table->string('description', 200);
            $table->unsignedBigInteger('amount_centavos');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['payroll_run_id', 'employee_id']);
        });

        DB::statement("ALTER TABLE payroll_adjustments ADD CONSTRAINT chk_pa_type
            CHECK (type IN ('earning','deduction'))");

        DB::statement("ALTER TABLE payroll_adjustments ADD CONSTRAINT chk_pa_nature
            CHECK (nature IN ('taxable','non_taxable'))");

        DB::statement('ALTER TABLE payroll_adjustments ADD CONSTRAINT chk_pa_amount
            CHECK (amount_centavos > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
