<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring Journal Entry Templates (GL-REC-001).
 *
 * Stores parameterised JE blueprints (rent, depreciation, amortisation etc.)
 * that the `journals:generate-recurring` command materialises automatically
 * on each scheduled run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_journal_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('description');
            $table->string('frequency', 20)->default('monthly');
            $table->tinyInteger('day_of_month')->nullable()->comment('1–28; used for monthly / semi_monthly frequencies');
            $table->date('next_run_date');
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('lines')->comment('[{account_id, debit, credit, description}]');
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("
            ALTER TABLE recurring_journal_templates
            ADD CONSTRAINT chk_rjt_frequency
            CHECK (frequency IN ('daily','weekly','monthly','semi_monthly','annual'))
        ");

        DB::statement("
            ALTER TABLE recurring_journal_templates
            ADD CONSTRAINT chk_rjt_day_of_month
            CHECK (day_of_month IS NULL OR (day_of_month BETWEEN 1 AND 28))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_templates');
    }
};
