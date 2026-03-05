<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PhilHealth premium contribution table — effective-date versioned.
 *
 * PHL-002: Premium = basic_salary × premium_rate. Base = basic_salary ONLY.
 * PHL-003: Both employee and employer share = premium_rate / 2.
 * PHL-004: Semi-monthly employee deduction = (basic_salary × premium_rate / 2) / 2.
 *
 * The min_monthly_premium and max_monthly_premium enforce the floor/ceiling,
 * per PhilHealth Circular rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('philhealth_premium_tables', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_date');
            $table->decimal('salary_floor', 10, 2)->nullable()->comment('Minimum salary this rate applies to. NULL = applies from zero.');
            $table->decimal('salary_ceiling', 10, 2)->nullable()->comment('Maximum salary this rate applies to. NULL = no ceiling.');
            $table->decimal('premium_rate', 8, 6)->comment('Total premium rate (employee + employer combined)');
            $table->decimal('min_monthly_premium', 10, 2)->comment('Minimum monthly total premium (floor)');
            $table->decimal('max_monthly_premium', 10, 2)->comment('Maximum monthly total premium (ceiling)');
            $table->text('legal_basis')->nullable();
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE philhealth_premium_tables
            ADD CONSTRAINT chk_ph_rate_valid CHECK (premium_rate > 0 AND premium_rate <= 1),
            ADD CONSTRAINT chk_ph_min_max CHECK (max_monthly_premium >= min_monthly_premium)
        ');

        DB::statement('
            CREATE INDEX idx_philhealth_lookup
            ON philhealth_premium_tables (effective_date DESC)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('philhealth_premium_tables');
    }
};
