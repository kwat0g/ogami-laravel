<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Permissions - Defines permissions for each role within a module.
 *
 * Instead of 18 hardcoded roles, we have 7 roles × N modules.
 * Example rows:
 *   - module: hr, role: manager, permissions: [employees.*, attendance.*, ...]
 *   - module: hr, role: officer, permissions: [employees.view, attendance.*, ...]
 *   - module: accounting, role: manager, permissions: [journal_entries.*, ap.*, ...]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_permissions', function (Blueprint $table) {
            $table->id();

            // Foreign key to modules (prevents invalid module_key)
            $table->string('module_key', 50);

            // Role within the module (7 core roles)
            $table->string('role', 50);

            // Permissions array for this role+module combination
            $table->json('permissions');

            // SoD restrictions specific to this role+module
            $table->json('sod_restrictions')->nullable();

            // Active status
            $table->boolean('is_active')->default(true);

            // Audit trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Composite unique key: one permission set per role per module
            $table->unique(['module_key', 'role']);

            // Foreign key constraint prevents typos in module_key
            $table->foreign('module_key')
                ->references('module_key')
                ->on('modules')
                ->onDelete('cascade');

            // Indexes
            $table->index(['module_key', 'role', 'is_active']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_permissions');
    }
};
