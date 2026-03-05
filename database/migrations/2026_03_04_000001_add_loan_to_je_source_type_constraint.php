<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Patch: Extend chk_je_source_type to include 'loan'.
 *
 * The original constraint only listed 'manual','payroll','ap','ar'.
 * LoanRequestService creates journal entries with source_type = 'loan'
 * for accounting approval and disbursement GL entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS chk_je_source_type');

        DB::statement("ALTER TABLE journal_entries
            ADD CONSTRAINT chk_je_source_type
            CHECK (source_type IN ('manual','payroll','ap','ar','loan'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS chk_je_source_type');

        DB::statement("ALTER TABLE journal_entries
            ADD CONSTRAINT chk_je_source_type
            CHECK (source_type IN ('manual','payroll','ap','ar'))");
    }
};
