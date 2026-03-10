<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add annual procurement budget tracking to departments.
 *
 * annual_budget_centavos — 0 means "no budget ceiling enforced".
 * fiscal_year_start_month — 1=January ... 12=December (default: 1)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->unsignedBigInteger('annual_budget_centavos')->default(0)
                  ->after('cost_center_code')
                  ->comment('Procurement budget cap. 0 = no ceiling enforced. ₱1 = 100.');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1)
                  ->after('annual_budget_centavos')
                  ->comment('Month the fiscal year starts (1 = January, 7 = July, etc.)');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn(['annual_budget_centavos', 'fiscal_year_start_month']);
        });
    }
};
