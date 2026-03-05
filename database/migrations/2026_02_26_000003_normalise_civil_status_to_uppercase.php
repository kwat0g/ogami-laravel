<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 2.5 — Normalise civil_status to uppercase.
 *
 * The DB stores lowercase values ('single', 'married') but the Zod schemas,
 * BIR report forms, and TaxStatusDeriver all expect uppercase canonical values.
 * This migration uppercases existing data and adds a CHECK constraint to
 * enforce valid values going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Normalise all existing values to uppercase
        DB::statement('
            UPDATE employees
            SET civil_status = UPPER(civil_status)
            WHERE civil_status IS NOT NULL
        ');

        // 2. Add CHECK constraint for valid TRAIN Law civil status values
        DB::statement("
            ALTER TABLE employees
            ADD CONSTRAINT chk_employees_civil_status
            CHECK (
                civil_status IS NULL OR
                civil_status IN ('SINGLE', 'MARRIED', 'WIDOWED', 'LEGALLY_SEPARATED', 'HEAD_OF_FAMILY')
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS chk_employees_civil_status');

        // Revert to lowercase (best-effort; original mixed case is unrecoverable)
        DB::statement('
            UPDATE employees
            SET civil_status = LOWER(civil_status)
            WHERE civil_status IS NOT NULL
        ');
    }
};
