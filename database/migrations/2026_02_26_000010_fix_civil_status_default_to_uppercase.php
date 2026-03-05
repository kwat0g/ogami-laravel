<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 9 fix — Update the civil_status column DEFAULT to uppercase 'SINGLE'.
 *
 * The add_defaults migration set the default to lowercase 'single', but the
 * normalise_civil_status_to_uppercase migration added a CHECK constraint
 * requiring uppercase values. This migration reconciles the two so that
 * employee creation without an explicit civil_status value works correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employees ALTER COLUMN civil_status SET DEFAULT 'SINGLE'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employees ALTER COLUMN civil_status SET DEFAULT 'single'");
    }
};
