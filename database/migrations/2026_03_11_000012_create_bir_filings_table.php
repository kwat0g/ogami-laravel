<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BIR Filing Tracker — records the status of each BIR tax form submission.
     *
     * Philippine businesses are required to file:
     *   1601C  — Monthly Remittance of Income Taxes Withheld on Compensation
     *   0619E  — Monthly Remittance of Creditable Income Taxes Withheld (EWT)
     *   1601EQ — Quarterly Remittance Return of Creditable Income Taxes Withheld
     *   2550M  — Monthly Value-Added Tax Declaration
     *   2550Q  — Quarterly Value-Added Tax Return
     *   0605   — Payment Form (annual registration, business permits)
     *   1702Q  — Quarterly Income Tax Return
     *   1702RT — Annual Income Tax Return (RCIT)
     */
    public function up(): void
    {
        Schema::create('bir_filings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            // Which BIR form this row tracks
            $table->string('form_type', 20);

            // The taxable period (month/quarter) this filing covers
            $table->foreignId('fiscal_period_id')
                ->constrained('fiscal_periods')
                ->restrictOnDelete();

            // BIR-calculated due date (e.g., 10th / 25th of the following month)
            $table->date('due_date');

            // Aggregate payable amount for this filing in centavos
            $table->unsignedBigInteger('total_tax_due_centavos')->default(0);

            // Actual filing information (filled in after filing)
            $table->date('filed_date')->nullable();
            $table->string('confirmation_number', 100)->nullable();
            $table->string('status', 20)->default('pending');

            $table->text('notes')->nullable();

            $table->foreignId('created_by_id')->constrained('users');
            $table->foreignId('filed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // One filing record per form type per period
            $table->unique(['form_type', 'fiscal_period_id'], 'uq_bir_filing_form_period');

            $table->index('due_date');
            $table->index('status');
        });

        DB::statement("ALTER TABLE bir_filings
            ADD CONSTRAINT chk_bir_filing_form_type
            CHECK (form_type IN ('1601C','0619E','1601EQ','2550M','2550Q','0605','1702Q','1702RT','2307_alpha'))");

        DB::statement("ALTER TABLE bir_filings
            ADD CONSTRAINT chk_bir_filing_status
            CHECK (status IN ('pending','filed','late','amended','cancelled'))");

        DB::statement("ALTER TABLE bir_filings
            ADD CONSTRAINT chk_bir_filing_date_order
            CHECK (filed_date IS NULL OR filed_date >= due_date - INTERVAL '60 days')");
    }

    public function down(): void
    {
        Schema::dropIfExists('bir_filings');
    }
};
