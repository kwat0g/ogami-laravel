<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS chk_app_status');
        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_app_status CHECK (status IN ('new','under_review','shortlisted','hired','rejected','withdrawn'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS chk_app_status');
        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_app_status CHECK (status IN ('new','under_review','shortlisted','rejected','withdrawn'))");
    }
};
