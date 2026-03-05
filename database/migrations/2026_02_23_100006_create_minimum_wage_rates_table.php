<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Minimum wage rates per region — effective-date versioned.
 *
 * EMP-012: Employee basic_salary must be >= prevailing minimum wage for their region.
 * DED-001/LN-007: Minimum wage check before applying voluntary/loan deductions.
 *
 * The DEFAULT_REGION .env var sets the fallback for dev; production always uses
 * the employee's assigned region.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minimum_wage_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_date');
            $table->string('region', 20)->comment('Philippine region code: NCR, CAR, R1–R13, BARMM, etc.');
            $table->decimal('daily_rate', 10, 2)->comment('Minimum daily wage in PHP');
            $table->text('wage_order_reference')->nullable()->comment('Wage Order number (e.g. NCR-25)');
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE minimum_wage_rates
            ADD CONSTRAINT chk_min_wage_positive CHECK (daily_rate > 0)
        ');

        // Most lookups are by region + effective date descending
        DB::statement('
            CREATE INDEX idx_minimum_wage_region_date
            ON minimum_wage_rates (region, effective_date DESC)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('minimum_wage_rates');
    }
};
