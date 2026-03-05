<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();        // SL, VL, SIL, ML, PL
            $table->string('name', 100);
            $table->string('category', 30)
                ->comment('sick|vacation|service_incentive|maternity|paternity|solo_parent|bereavement|other');
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('can_be_monetized')->default(false);  // SIL — LV-007
            $table->boolean('accrues_monthly')->default(false);
            $table->decimal('monthly_accrual_days', 5, 2)->default(0.00);
            $table->unsignedSmallInteger('max_carry_over_days')->default(0);  // LV-002
            $table->unsignedSmallInteger('max_annual_days')->default(0);      // 0 = unlimited
            $table->unsignedSmallInteger('min_days_per_request')->default(1);
            $table->unsignedSmallInteger('max_days_per_request')->default(30);
            $table->unsignedSmallInteger('notice_days_required')->default(0); // advance notice
            $table->boolean('deducts_absent_on_lwop')->default(false);        // LV-006
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE leave_types ADD CONSTRAINT chk_lt_category
            CHECK (category IN ('sick','vacation','service_incentive','maternity','paternity','solo_parent','bereavement','other'))");

        DB::statement('ALTER TABLE leave_types ADD CONSTRAINT chk_lt_accrual
            CHECK (monthly_accrual_days >= 0 AND monthly_accrual_days <= 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
