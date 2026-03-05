<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System-wide configuration key-value store.
 *
 * All configurable operational parameters live here — never hardcoded in PHP.
 * See ogami_erp_roadmap_v3.md §10 "Zero Hardcoding Policy" for the full list.
 *
 * The `value` column is JSONB to support all data types (string, number, boolean,
 * array). Use `data_type` to cast the value correctly when reading via
 * SystemSetting::decimal(), SystemSetting::integer(), etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique()->comment('Dot-namespaced key, e.g. payroll.annual_periods_count');
            $table->string('label', 200)->comment('Human-readable label for the settings UI');
            $table->jsonb('value')->comment('Value stored as JSON; cast via data_type');
            $table->string('data_type', 20)->comment('string|integer|decimal|boolean|json');
            $table->boolean('is_sensitive')->default(false)->comment('Sensitive settings are masked in the Pulse/Horizon UI');
            $table->string('editable_by_role', 50)->default('admin')->comment('Minimum role that may edit this setting');
            $table->string('group', 50)->default('general')->comment('Grouping for the settings UI (payroll, tax, leave, security, etc.)');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        // Enforce allowed data_type values at the database level
        DB::statement("
            ALTER TABLE system_settings
            ADD CONSTRAINT chk_system_settings_data_type
            CHECK (data_type IN ('string', 'integer', 'decimal', 'boolean', 'json'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
