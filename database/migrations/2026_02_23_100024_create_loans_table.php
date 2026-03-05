<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loans — LN-001 to LN-008.
 * LN-007: is_protected_by_min_wage is set per installment by LoanAmortizationService.
 * SoD: requester ≠ approver (enforced at service layer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();   // LN-YYYY-NNNNNN
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->foreignId('loan_type_id')
                ->constrained('loan_types')
                ->restrictOnDelete();
            $table->unsignedBigInteger('principal_centavos');       // requested amount
            $table->decimal('interest_rate_annual', 6, 4)->default(0);
            $table->unsignedSmallInteger('term_months');
            $table->unsignedBigInteger('monthly_amortization_centavos')->default(0);
            $table->unsignedBigInteger('total_payable_centavos')->default(0);   // computed on approval
            $table->unsignedBigInteger('outstanding_balance_centavos')->default(0);
            $table->date('loan_date');
            $table->date('first_deduction_date')->nullable();   // set on approval/disbursal
            $table->timestamp('disbursed_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('purpose')->nullable();
            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approver_remarks')->nullable();   // renamed from approval_remarks
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
        });

        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loan_status
            CHECK (status IN ('pending','approved','active','fully_paid','cancelled','written_off'))");

        DB::statement('ALTER TABLE loans ADD CONSTRAINT chk_loan_principal
            CHECK (principal_centavos > 0)');

        DB::statement('ALTER TABLE loans ADD CONSTRAINT chk_loan_term
            CHECK (term_months BETWEEN 1 AND 120)');

        DB::statement('ALTER TABLE loans ADD CONSTRAINT chk_loan_sod
            CHECK (approved_by IS NULL OR approved_by <> requested_by)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
