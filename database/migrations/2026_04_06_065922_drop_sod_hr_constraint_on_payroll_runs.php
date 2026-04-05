<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payroll_runs DROP CONSTRAINT IF EXISTS chk_sod_payroll_hr');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE payroll_runs
            ADD CONSTRAINT chk_sod_payroll_hr
            CHECK (hr_approved_by_id IS NULL OR hr_approved_by_id != initiated_by_id)
        ');
    }
};
