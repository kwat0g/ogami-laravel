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
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();         // SSS_LOAN, PAGIBIG_LOAN, COMPANY
            $table->string('name', 100);
            $table->string('category', 30)
                ->comment('government|company');
            $table->text('description')->nullable();
            $table->decimal('interest_rate_annual', 6, 4)->default(0.0000);
            $table->unsignedSmallInteger('max_term_months');
            $table->unsignedBigInteger('max_amount_centavos')->nullable(); // NULL = no cap
            $table->unsignedBigInteger('min_amount_centavos')->default(0);
            $table->boolean('subject_to_min_wage_protection')->default(true);  // LN-007
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE loan_types ADD CONSTRAINT chk_lntype_category
            CHECK (category IN ('government','company'))");

        DB::statement('ALTER TABLE loan_types ADD CONSTRAINT chk_lntype_interest
            CHECK (interest_rate_annual >= 0 AND interest_rate_annual <= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_types');
    }
};
