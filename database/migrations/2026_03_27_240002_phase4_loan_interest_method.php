<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.2 — Loan interest method options on LoanType.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('loan_types', 'interest_method')) {
            Schema::table('loan_types', function (Blueprint $table): void {
                $table->string('interest_method', 30)->default('simple')->after('interest_rate');
            });

            DB::statement("ALTER TABLE loan_types ADD CONSTRAINT chk_loan_types_interest_method CHECK (interest_method IN ('simple','diminishing_balance','flat'))");
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE loan_types DROP CONSTRAINT IF EXISTS chk_loan_types_interest_method');

        Schema::table('loan_types', function (Blueprint $table): void {
            $table->dropColumn('interest_method');
        });
    }
};
