<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add 13th month pay columns to payroll_details table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table): void {
            $table->unsignedBigInteger('thirteenth_month_centavos')->default(0)
                ->after('net_pay_centavos')
                ->comment('13th month pay amount (Step 18)');
            
            $table->unsignedBigInteger('thirteenth_month_taxable_centavos')->default(0)
                ->after('thirteenth_month_centavos')
                ->comment('Taxable portion of 13th month (excess over ₱90,000 exemption)');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table): void {
            $table->dropColumn(['thirteenth_month_centavos', 'thirteenth_month_taxable_centavos']);
        });
    }
};
