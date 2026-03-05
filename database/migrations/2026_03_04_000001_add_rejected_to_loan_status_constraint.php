<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_loan_status');
        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loan_status
            CHECK (status IN ('pending','supervisor_approved','approved','ready_for_disbursement','active','fully_paid','cancelled','rejected','written_off'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_loan_status');
        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loan_status
            CHECK (status IN ('pending','supervisor_approved','approved','ready_for_disbursement','active','fully_paid','cancelled','written_off'))");
    }
};
