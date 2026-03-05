<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAIN Law (RA 10963) income tax brackets — effective-date versioned.
 *
 * DESIGN NOTE (TAX-004): This table has NO tax_status_group column.
 * Under TRAIN Law (effective January 1, 2018), personal and additional
 * exemptions were abolished. A single universal bracket table applies to ALL
 * employees regardless of civil status or number of dependents.
 *
 * The old BIR codes (S, ME, S1–S4, ME1–ME4) are derived only when printing
 * BIR Form 2316 or the Alphalist, via TaxStatusDeriver::derive(). They are
 * never stored in this table and never used in payroll computation.
 *
 * Always query using the versioned pattern:
 *   TrainTaxBracket::where('effective_date', '<=', $periodEnd)
 *       ->where('income_from', '<=', $annualizedIncome)
 *       ->where(fn($q) => $q->whereNull('income_to')->orWhere('income_to', '>=', $annualizedIncome))
 *       ->orderByDesc('effective_date')
 *       ->firstOrFail();
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('train_tax_brackets', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_date')->comment('Rate is active from this date onward. Superseded by a newer row.');
            $table->decimal('income_from', 15, 4)->comment('Annual taxable income lower bound (inclusive)');
            $table->decimal('income_to', 15, 4)->nullable()->comment('Annual taxable income upper bound (inclusive). NULL = no ceiling (top bracket).');
            $table->decimal('base_tax', 15, 4)->default(0)->comment('Fixed base tax for this bracket');
            $table->decimal('excess_rate', 8, 6)->comment('Rate applied to income above income_from');
            $table->text('notes')->nullable()->comment('Legal basis (e.g. "TRAIN Law §24(A)(2)(a)")');
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE train_tax_brackets
            ADD CONSTRAINT chk_train_bracket_from_positive CHECK (income_from >= 0),
            ADD CONSTRAINT chk_train_bracket_to_gte_from CHECK (income_to IS NULL OR income_to >= income_from),
            ADD CONSTRAINT chk_train_bracket_base_tax_non_negative CHECK (base_tax >= 0),
            ADD CONSTRAINT chk_train_bracket_excess_rate_valid CHECK (excess_rate >= 0 AND excess_rate <= 1)
        ');

        // Composite index for the versioned lookup pattern (TAX-002)
        DB::statement('
            CREATE INDEX idx_train_tax_brackets_lookup
            ON train_tax_brackets (effective_date DESC, income_from, income_to)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('train_tax_brackets');
    }
};
