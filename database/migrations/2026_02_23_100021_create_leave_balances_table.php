<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave balances per employee per leave type per year.
 * LV-001: balance cannot go negative.
 * LV-002: carry-over enforced by LeaveAccrualService (max_carry_over_days from leave_types).
 * LV-003: no accrual during LWOP (enforced at service layer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('opening_balance', 7, 2)->default(0.00);  // carried over
            $table->decimal('accrued', 7, 2)->default(0.00);          // earned this year
            $table->decimal('used', 7, 2)->default(0.00);             // approved requests
            $table->decimal('adjusted', 7, 2)->default(0.00);         // manual HR adjustments
            $table->decimal('monetized', 7, 2)->default(0.00);        // SIL monetized (LV-007)
            $table->decimal('balance', 7, 2)
                ->storedAs('opening_balance + accrued + adjusted - used - monetized');
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'uq_leave_balance');
        });

        DB::statement('ALTER TABLE leave_balances ADD CONSTRAINT chk_lb_no_negative
            CHECK (opening_balance + accrued + adjusted - used - monetized >= 0)');

        DB::statement('ALTER TABLE leave_balances ADD CONSTRAINT chk_lb_year
            CHECK (year BETWEEN 2020 AND 2100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
