<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the CHECK constraint to include 'converted_to_pr' status
        DB::statement('ALTER TABLE material_requisitions DROP CONSTRAINT IF EXISTS mrq_status_check');
        DB::statement("ALTER TABLE material_requisitions ADD CONSTRAINT mrq_status_check
            CHECK (status IN ('draft','submitted','noted','checked','reviewed','approved','rejected','cancelled','fulfilled','converted_to_pr'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE material_requisitions DROP CONSTRAINT IF EXISTS mrq_status_check');
        DB::statement("ALTER TABLE material_requisitions ADD CONSTRAINT mrq_status_check
            CHECK (status IN ('draft','submitted','noted','checked','reviewed','approved','rejected','cancelled','fulfilled'))
        ");
    }
};
