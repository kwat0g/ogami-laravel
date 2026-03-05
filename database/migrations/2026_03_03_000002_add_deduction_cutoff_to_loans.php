<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add deduction_cutoff to loans.
 * Employees choose whether their loan amortization is deducted on the
 * 1st cut-off (1–15) or 2nd cut-off (16–end) of each month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('deduction_cutoff', 5)->default('2nd')
                ->comment('1st = first half deduction, 2nd = second half deduction')
                ->after('first_deduction_date');
        });

        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loan_deduction_cutoff
            CHECK (deduction_cutoff IN ('1st', '2nd'))");
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('deduction_cutoff');
        });
    }
};
