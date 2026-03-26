<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Cost Centers ───────────────────────────────────────────────────────
        Schema::create('cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();

            // Optional department link for auto-tagging JE lines from dept transactions
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            // Self-referential parent for hierarchy (e.g., Plant > Line > Cell)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('cost_centers')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('department_id');
            $table->index('parent_id');
        });

        // ── Annual Budgets ─────────────────────────────────────────────────────
        // One budget row per (cost_center, fiscal_year, account) — amount in centavos
        Schema::create('annual_budgets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('cost_center_id')
                ->constrained('cost_centers')
                ->restrictOnDelete();

            // Four-digit fiscal year (e.g., 2025)
            $table->smallInteger('fiscal_year');

            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            // Budget amount stored as centavos (₱1 = 100)
            $table->unsignedBigInteger('budgeted_amount_centavos');

            $table->text('notes')->nullable();

            $table->foreignId('created_by_id')->constrained('users');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One budget per account per cost center per year
            $table->unique(['cost_center_id', 'fiscal_year', 'account_id'], 'uq_annual_budget_line');
            $table->index(['cost_center_id', 'fiscal_year']);
        });

        DB::statement('ALTER TABLE annual_budgets
            ADD CONSTRAINT chk_annual_budget_year
            CHECK (fiscal_year >= 2000 AND fiscal_year <= 2100)');

        DB::statement('ALTER TABLE annual_budgets
            ADD CONSTRAINT chk_annual_budget_amount
            CHECK (budgeted_amount_centavos >= 0)');

        // ── Widen journal_entry_lines.cost_center_id to bigint + add FK ────────
        // The original migration used unsignedInteger (int4); cost_centers.id is
        // bigint. Alter to match so the FK constraint is valid in PostgreSQL.
        DB::statement('ALTER TABLE journal_entry_lines
            ALTER COLUMN cost_center_id TYPE bigint USING cost_center_id::bigint');

        DB::statement('ALTER TABLE journal_entry_lines
            ADD CONSTRAINT fk_jel_cost_center
            FOREIGN KEY (cost_center_id) REFERENCES cost_centers (id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        // Remove FK before dropping cost_centers
        DB::statement('ALTER TABLE journal_entry_lines
            DROP CONSTRAINT IF EXISTS fk_jel_cost_center');

        DB::statement('ALTER TABLE journal_entry_lines
            ALTER COLUMN cost_center_id TYPE integer USING cost_center_id::integer');

        Schema::dropIfExists('annual_budgets');
        Schema::dropIfExists('cost_centers');
    }
};
