<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core employees table.
 *
 * Business rules enforced at DB level:
 *  EMP-001 : employee_code unique, format EMP-YYYY-NNNN enforced by app
 *  EMP-002 : date_hired required
 *  EMP-004 : employment_type restricted to known values
 *  EMP-005 : basic_monthly_rate > 0
 *  EMP-007 : government IDs stored encrypted (handled in model, NOT this layer)
 *  EMP-009 : sss_no/tin/philhealth/pagibig unique when non-null (partial indexes)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 20)->unique();  // EMP-YYYY-NNNN

            // ── Personal info ─────────────────────────────────────────────────
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 10)->nullable();        // Jr., Sr., III
            $table->date('birth_date');
            $table->string('gender', 10)
                ->comment('male|female|prefer_not_to_say');
            $table->string('civil_status', 20)
                ->comment('single|married|widowed|separated|legally_separated');
            $table->unsignedSmallInteger('qualified_dependents')->default(0);
            $table->string('bir_status', 10)
                ->comment('S|M|ME1|ME2|ME3|ME4|HF|HF1|HF2|HF3|HF4');

            // ── Contact ───────────────────────────────────────────────────────
            $table->string('personal_email', 254)->nullable();
            $table->string('work_email', 254)->nullable()->unique();
            $table->string('mobile_number', 20)->nullable();
            $table->text('permanent_address')->nullable();
            $table->text('current_address')->nullable();

            // ── Government IDs (encrypted at model layer) ─────────────────────
            $table->text('tin_encrypted')->nullable();
            $table->text('sss_no_encrypted')->nullable();
            $table->text('philhealth_no_encrypted')->nullable();
            $table->text('pagibig_no_encrypted')->nullable();
            // Hashed versions for uniqueness checks (SHA-256, non-reversible)
            $table->string('tin_hash', 64)->nullable()->unique();
            $table->string('sss_no_hash', 64)->nullable()->unique();
            $table->string('philhealth_no_hash', 64)->nullable()->unique();
            $table->string('pagibig_no_hash', 64)->nullable()->unique();

            // ── Employment ────────────────────────────────────────────────────
            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete();
            $table->foreignId('position_id')
                ->constrained('positions')
                ->restrictOnDelete();
            $table->foreignId('salary_grade_id')
                ->nullable()
                ->constrained('salary_grades')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('employment_type', 30);
            $table->string('employment_status', 30)->default('active');
            $table->date('date_hired');
            $table->date('regularization_date')->nullable();
            $table->date('separation_date')->nullable();
            $table->string('separation_reason', 100)->nullable();

            // ── Compensation ──────────────────────────────────────────────────
            $table->string('pay_basis', 20)->default('monthly')
                ->comment('monthly|daily');
            $table->unsignedBigInteger('basic_monthly_rate');   // centavos — EMP-005
            // daily_rate & hourly_rate are maintained by DB trigger (S2)
            $table->unsignedBigInteger('daily_rate')->storedAs(
                'ROUND(basic_monthly_rate / 22.0)'
            );
            $table->unsignedBigInteger('hourly_rate')->storedAs(
                'ROUND(basic_monthly_rate / 22.0 / 8.0)'
            );
            $table->boolean('is_minimum_wage_earner')->default(false);

            // ── Bank disbursement ─────────────────────────────────────────────
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 30)->nullable();
            $table->string('bank_account_name', 200)->nullable();

            // ── Meta ──────────────────────────────────────────────────────────
            $table->string('plant_id', 30)->nullable()
                ->comment('Physical plant/branch code for multi-site');
            $table->boolean('is_active')->default(true);
            $table->string('onboarding_status', 30)->default('draft')
                ->comment('draft|documents_pending|active|offboarding|offboarded');
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index(['department_id', 'employment_status']);
            $table->index(['employment_status', 'is_active']);
            $table->index('date_hired');
            $table->index('separation_date');
        });

        // ── DB-level constraints ──────────────────────────────────────────────
        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_emp_employment_type
            CHECK (employment_type IN ('regular','contractual','project_based','casual','probationary'))");

        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_emp_employment_status
            CHECK (employment_status IN ('active','on_leave','suspended','resigned','terminated'))");

        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_emp_pay_basis
            CHECK (pay_basis IN ('monthly','daily'))");

        DB::statement('ALTER TABLE employees ADD CONSTRAINT chk_emp_monthly_rate
            CHECK (basic_monthly_rate > 0)');

        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_emp_onboarding_status
            CHECK (onboarding_status IN ('draft','documents_pending','active','offboarding','offboarded'))");

        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_emp_bir_status
            CHECK (bir_status IN ('S','M','ME1','ME2','ME3','ME4','HF','HF1','HF2','HF3','HF4'))");

        DB::statement('ALTER TABLE employees ADD CONSTRAINT chk_emp_separation_logic
            CHECK (separation_date IS NULL OR separation_date >= date_hired)');
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
