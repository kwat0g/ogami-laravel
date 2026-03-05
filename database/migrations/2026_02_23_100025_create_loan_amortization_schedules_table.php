<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loan amortization schedule — one row per installment.
 * LN-007: is_protected flag set when deducting this installment would push
 *         net pay below minimum wage threshold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_amortization_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')
                ->constrained('loans')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_no');     // 1, 2, 3 …
            $table->date('due_date');
            $table->unsignedBigInteger('principal_portion_centavos');
            $table->unsignedBigInteger('interest_portion_centavos');
            $table->unsignedBigInteger('total_due_centavos');
            $table->unsignedBigInteger('paid_centavos')->default(0);
            $table->string('status', 20)->default('pending');
            $table->boolean('is_protected_by_min_wage')->default(false);  // LN-007
            $table->foreignId('payroll_run_id')->nullable()
                ->comment('Set when deducted via payroll');
            $table->date('paid_date')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_no']);
            $table->index(['loan_id', 'status']);
            $table->index('due_date');
        });

        DB::statement("ALTER TABLE loan_amortization_schedules ADD CONSTRAINT chk_lam_status
            CHECK (status IN ('pending','paid','skipped','protected'))");

        DB::statement('ALTER TABLE loan_amortization_schedules ADD CONSTRAINT chk_lam_amounts
            CHECK (principal_portion_centavos > 0 AND interest_portion_centavos >= 0
                   AND total_due_centavos = principal_portion_centavos + interest_portion_centavos)');

        DB::statement('ALTER TABLE loan_amortization_schedules ADD CONSTRAINT chk_lam_paid
            CHECK (paid_centavos <= total_due_centavos)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_amortization_schedules');
    }
};
