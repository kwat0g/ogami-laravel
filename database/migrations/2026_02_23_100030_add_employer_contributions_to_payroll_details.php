<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add employer (ER) contribution columns to payroll_details.
 *
 * These are stored alongside EE columns so government remittance reports
 * (SSS SBR2, PhilHealth RF-1, Pag-IBIG monthly) can query frozen
 * historical values without re-deriving them from rate tables.
 *
 *  - sss_er_centavos        : Employer SSS share (0 on 1st cutoff, full on 2nd)
 *  - philhealth_er_centavos : Employer PhilHealth share per semi-monthly period
 *  - pagibig_er_centavos    : Employer Pag-IBIG share per semi-monthly period
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->unsignedBigInteger('sss_er_centavos')
                ->default(0)
                ->after('sss_ee_centavos')
                ->comment('Employer SSS share — 0 on 1st cutoff, full on 2nd');

            $table->unsignedBigInteger('philhealth_er_centavos')
                ->default(0)
                ->after('philhealth_ee_centavos')
                ->comment('Employer PhilHealth share per semi-monthly period');

            $table->unsignedBigInteger('pagibig_er_centavos')
                ->default(0)
                ->after('pagibig_ee_centavos')
                ->comment('Employer Pag-IBIG share per semi-monthly period');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn(['sss_er_centavos', 'philhealth_er_centavos', 'pagibig_er_centavos']);
        });
    }
};
