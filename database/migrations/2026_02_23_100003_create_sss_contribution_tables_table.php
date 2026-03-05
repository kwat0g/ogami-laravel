<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SSS contribution table — Monthly Salary Credit (MSC) brackets, effective-date versioned.
 *
 * SSS-001: MSC is looked up by bracket, not computed directly from salary.
 * SSS-005: If salary exceeds the maximum MSC, use the highest row.
 *
 * ec_contribution is the Employees' Compensation (EC) contribution
 * — a pure employer cost; never deducted from employee's net pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sss_contribution_tables', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_date');
            $table->decimal('salary_range_from', 10, 2)->comment('Minimum monthly salary for this MSC bracket');
            $table->decimal('salary_range_to', 10, 2)->nullable()->comment('Maximum monthly salary. NULL = no ceiling (highest bracket).');
            $table->decimal('monthly_salary_credit', 10, 2)->comment('MSC used to derive contribution amounts');
            $table->decimal('employee_contribution', 10, 2)->comment('Employee share (deducted from payroll)');
            $table->decimal('employer_contribution', 10, 2)->comment('Employer share (not deducted from employee)');
            $table->decimal('ec_contribution', 10, 2)->default(0)->comment('Employees\' Compensation — pure employer cost');
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE sss_contribution_tables
            ADD CONSTRAINT chk_sss_range_positive CHECK (salary_range_from >= 0),
            ADD CONSTRAINT chk_sss_range_order CHECK (salary_range_to IS NULL OR salary_range_to >= salary_range_from),
            ADD CONSTRAINT chk_sss_employee_contrib_positive CHECK (employee_contribution >= 0),
            ADD CONSTRAINT chk_sss_employer_contrib_positive CHECK (employer_contribution >= 0)
        ');

        DB::statement('
            CREATE INDEX idx_sss_contribution_lookup
            ON sss_contribution_tables (effective_date DESC, salary_range_from, salary_range_to)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('sss_contribution_tables');
    }
};
