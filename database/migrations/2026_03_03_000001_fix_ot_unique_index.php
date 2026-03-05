<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the blanket unique index on (employee_id, work_date) with a partial
 * index that only prevents duplicates for *active* requests.
 * Cancelled / rejected records no longer block re-filing on the same date.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop old blanket unique constraint if it exists (from a previous run).
        // It was created as a table constraint via Blueprint::unique(), so we
        // must use ALTER TABLE DROP CONSTRAINT — not DROP INDEX.
        DB::statement('
            ALTER TABLE overtime_requests
            DROP CONSTRAINT IF EXISTS uq_ot_request_per_day
        ');

        // Drop the partial index too in case it already exists, then re-create
        // cleanly so running migrate:fresh still works.
        DB::statement('
            DROP INDEX IF EXISTS uq_ot_active_per_day
        ');

        DB::statement("
            CREATE UNIQUE INDEX uq_ot_active_per_day
            ON overtime_requests (employee_id, work_date)
            WHERE status NOT IN ('cancelled', 'rejected')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_ot_active_per_day');

        // Restore the old blanket index on rollback.
        DB::statement('
            ALTER TABLE overtime_requests
            ADD CONSTRAINT uq_ot_request_per_day UNIQUE (employee_id, work_date)
        ');
    }
};
