<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add safe defaults so employee creation without civil_status or
        // bir_status does not violate the NOT NULL constraint.
        DB::statement("ALTER TABLE employees ALTER COLUMN civil_status SET DEFAULT 'single'");
        DB::statement("ALTER TABLE employees ALTER COLUMN bir_status SET DEFAULT 'S'");

        // Make optional FK columns truly nullable so employee creation without
        // department/position/salary_grade/user assignments succeeds.
        DB::statement('ALTER TABLE employees ALTER COLUMN department_id DROP NOT NULL');
        DB::statement('ALTER TABLE employees ALTER COLUMN position_id DROP NOT NULL');
        DB::statement('ALTER TABLE employees ALTER COLUMN salary_grade_id DROP NOT NULL');
        DB::statement('ALTER TABLE employees ALTER COLUMN user_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE employees ALTER COLUMN civil_status DROP DEFAULT');
        DB::statement('ALTER TABLE employees ALTER COLUMN bir_status DROP DEFAULT');
        DB::statement('ALTER TABLE employees ALTER COLUMN department_id SET NOT NULL');
        DB::statement('ALTER TABLE employees ALTER COLUMN position_id SET NOT NULL');
        DB::statement('ALTER TABLE employees ALTER COLUMN salary_grade_id SET NOT NULL');
        DB::statement('ALTER TABLE employees ALTER COLUMN user_id SET NOT NULL');
    }
};
