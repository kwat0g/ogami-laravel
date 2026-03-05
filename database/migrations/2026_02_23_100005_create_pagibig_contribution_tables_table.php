<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pag-IBIG (HDMF) contribution table — effective-date versioned.
 *
 * PAGIBIG-002: Employee rate = 1% if monthly_basic ≤ salary_threshold; 2% if above.
 * PAGIBIG-003: Employee contribution is CAPPED at employee_cap_monthly (₱100/month).
 *              Per semi-monthly period: ₱50 maximum.
 * PAGIBIG-004: Employer rate = 2%, no cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagibig_contribution_tables', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_date');
            $table->decimal('salary_threshold', 10, 2)->comment('If monthly basic ≤ this, use employee_rate_below; otherwise employee_rate_above');
            $table->decimal('employee_rate_below', 8, 6)->comment('Employee rate when monthly basic ≤ salary_threshold');
            $table->decimal('employee_rate_above', 8, 6)->comment('Employee rate when monthly basic > salary_threshold');
            $table->decimal('employee_cap_monthly', 10, 2)->comment('Maximum employee contribution per month (e.g. ₱100.00)');
            $table->decimal('employer_rate', 8, 6)->comment('Employer contribution rate (no cap)');
            $table->text('legal_basis')->nullable();
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE pagibig_contribution_tables
            ADD CONSTRAINT chk_pagibig_rates_valid CHECK (
                employee_rate_below >= 0 AND employee_rate_below <= 1 AND
                employee_rate_above >= 0 AND employee_rate_above <= 1 AND
                employer_rate >= 0 AND employer_rate <= 1
            ),
            ADD CONSTRAINT chk_pagibig_cap_positive CHECK (employee_cap_monthly >= 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('pagibig_contribution_tables');
    }
};
