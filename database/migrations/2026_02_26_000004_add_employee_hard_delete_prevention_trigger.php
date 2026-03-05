<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 2.8 (partial) — Add employee hard-delete prevention trigger.
 *
 * The JE balance and JE immutability triggers already exist.
 * This migration adds only the missing employee hard-delete prevention trigger.
 *
 * Business rule: Employees must never be hard-deleted; use soft-delete
 * (deleted_at) instead so payroll history remains intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Trigger function: raises an exception if a non-soft-deleted employee
        // row is about to be hard-deleted from the employees table.
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_employee_hard_delete()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.deleted_at IS NULL THEN
                    RAISE EXCEPTION
                        'Employees cannot be hard-deleted (EMP-PROTECT). '
                        'Use soft delete (deleted_at) so payroll history is preserved.';
                END IF;
                RETURN OLD;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        // Trigger: fires BEFORE DELETE on the employees table.
        // It only blocks deletes where deleted_at IS NULL (i.e. not soft-deleted).
        // Soft-deleted rows (deleted_at IS NOT NULL) can still be hard-purged
        // by a privileged maintenance operation if required.
        DB::statement('
            CREATE TRIGGER trg_prevent_employee_hard_delete
                BEFORE DELETE ON employees
                FOR EACH ROW
                EXECUTE FUNCTION prevent_employee_hard_delete()
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_prevent_employee_hard_delete ON employees');
        DB::statement('DROP FUNCTION IF EXISTS prevent_employee_hard_delete()');
    }
};
