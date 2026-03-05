<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old check constraint
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_loan_status');

        // Increase status column size to accommodate longer status values
        DB::statement('ALTER TABLE loans ALTER COLUMN status TYPE VARCHAR(30)');

        // Add new check constraint with all statuses
        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loan_status
            CHECK (status IN ('pending','supervisor_approved','approved','ready_for_disbursement','active','fully_paid','cancelled','rejected','written_off'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_loan_status');
        DB::statement('ALTER TABLE loans ALTER COLUMN status TYPE VARCHAR(20)');
        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loan_status
            CHECK (status IN ('pending','approved','active','fully_paid','cancelled','written_off'))");
    }
};
