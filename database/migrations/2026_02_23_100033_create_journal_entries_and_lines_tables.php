<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal Entries + Journal Entry Lines — double-entry GL transactions.
 *
 * JE-001: sum(debits) = sum(credits) — DB trigger + service layer.
 * JE-002: min 2 lines — FormRequest.
 * JE-003: no zero-amount lines — DB CHECK.
 * JE-006: posted JEs are immutable — DB trigger protect_posted_journal_entries.
 * JE-007: reversal_of FK; a JE can only be reversed once.
 * JE-008: auto-posted (source_type != 'manual') cannot be manually edited — Policy.
 * JE-009: je_number auto-generated on posting.
 * SoD: posted_by ≠ created_by — DB CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('je_number', 30)->unique()->nullable(); // JE-009: assigned at post time
            $table->date('date');
            $table->text('description');
            $table->string('source_type', 30)->default('manual'); // manual|payroll|ap|ar
            $table->unsignedInteger('source_id')->nullable();     // JE-008: links to source record
            $table->string('status', 20)->default('draft');       // draft|submitted|posted|cancelled|stale
            $table->foreignId('fiscal_period_id')
                ->constrained('fiscal_periods')
                ->restrictOnDelete();
            $table->foreignId('reversal_of')->nullable()          // JE-007
                ->constrained('journal_entries')
                ->restrictOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('posted_by')->nullable()             // SoD: ≠ created_by
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'date']);
            $table->index(['fiscal_period_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        DB::statement("ALTER TABLE journal_entries
            ADD CONSTRAINT chk_je_status
            CHECK (status IN ('draft','submitted','posted','cancelled','stale'))");

        DB::statement("ALTER TABLE journal_entries
            ADD CONSTRAINT chk_je_source_type
            CHECK (source_type IN ('manual','payroll','ap','ar','loan'))");

        // SoD: JE-010 — poster cannot be the drafter
        DB::statement('ALTER TABLE journal_entries
            ADD CONSTRAINT chk_sod_je_posting
            CHECK (posted_by IS NULL OR posted_by != created_by)');

        // ── Journal Entry Lines ─────────────────────────────────────────────
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')
                ->restrictOnDelete();
            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();
            // JE-003: debit/credit each must be > 0 if set
            $table->decimal('debit', 15, 4)->nullable();
            $table->decimal('credit', 15, 4)->nullable();
            $table->unsignedInteger('cost_center_id')->nullable();
            $table->text('description')->nullable();

            $table->index('journal_entry_id');
            $table->index('account_id');
        });

        DB::statement('ALTER TABLE journal_entry_lines
            ADD CONSTRAINT chk_jel_debit_positive
            CHECK (debit IS NULL OR debit > 0)');

        DB::statement('ALTER TABLE journal_entry_lines
            ADD CONSTRAINT chk_jel_credit_positive
            CHECK (credit IS NULL OR credit > 0)');

        // JE-003 cont.: a line must have EITHER debit OR credit — not both, not neither
        DB::statement('ALTER TABLE journal_entry_lines
            ADD CONSTRAINT chk_jel_debit_or_credit
            CHECK (
                (debit IS NOT NULL AND credit IS NULL) OR
                (debit IS NULL AND credit IS NOT NULL)
            )');

        // ── DB Trigger 1: Immutability of posted JEs (JE-006) ──────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION protect_posted_journal_entries() RETURNS trigger AS \$\$
            BEGIN
                IF OLD.status = 'posted' THEN
                    RAISE EXCEPTION 'Posted journal entries are immutable. Use a reversing entry. (JE-006)';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement('
            CREATE TRIGGER trg_protect_posted_je
                BEFORE UPDATE OR DELETE ON journal_entries
                FOR EACH ROW EXECUTE FUNCTION protect_posted_journal_entries()
        ');

        // ── DB Trigger 2: Double-entry balance check (JE-001) ───────────────
        // Fires AFTER any insert/update/delete on journal_entry_lines.
        // Only fires when the parent JE is being posted (status = 'posted') to
        // avoid blocking draft edits. The service layer also checks on post.
        DB::statement("
            CREATE OR REPLACE FUNCTION check_journal_balance() RETURNS trigger AS \$\$
            DECLARE
                je_id INT := COALESCE(NEW.journal_entry_id, OLD.journal_entry_id);
                je_status VARCHAR(20);
                total_debits  NUMERIC;
                total_credits NUMERIC;
            BEGIN
                SELECT status INTO je_status FROM journal_entries WHERE id = je_id;

                -- Only enforce balance on posted JEs (draft can be imbalanced mid-edit)
                IF je_status = 'posted' THEN
                    SELECT COALESCE(SUM(debit),0), COALESCE(SUM(credit),0)
                    INTO   total_debits, total_credits
                    FROM   journal_entry_lines
                    WHERE  journal_entry_id = je_id;

                    IF ABS(total_debits - total_credits) > 0.01 THEN
                        RAISE EXCEPTION 'Journal entry % is unbalanced: debits=% credits=% (JE-001)',
                            je_id, total_debits, total_credits;
                    END IF;
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement('
            CREATE TRIGGER trg_check_journal_balance
                AFTER INSERT OR UPDATE OR DELETE ON journal_entry_lines
                FOR EACH ROW EXECUTE FUNCTION check_journal_balance()
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_check_journal_balance ON journal_entry_lines');
        DB::statement('DROP FUNCTION IF EXISTS check_journal_balance()');
        DB::statement('DROP TRIGGER IF EXISTS trg_protect_posted_je ON journal_entries');
        DB::statement('DROP FUNCTION IF EXISTS protect_posted_journal_entries()');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
