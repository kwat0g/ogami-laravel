<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permission Templates - Configurable permission sets for positions.
 *
 * This enables dynamic RBAC where permissions are data, not code.
 * Templates can be assigned to any department's positions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->unique(); // e.g., 'hr_manager', 'accounting_officer'
            $table->string('label'); // Display name
            $table->text('description')->nullable();

            // Hierarchy level for approval routing (1-10)
            // 1 = staff, 2 = senior staff, 3 = supervisor, 4 = manager, 5 = senior manager, 6+ = executive
            $table->tinyInteger('level')->default(1);

            // Permissions stored as JSON array for flexibility
            $table->json('permissions');

            // Permission groups this template belongs to (for UI organization)
            $table->json('permission_groups')->nullable(); // ['hr', 'payroll']

            // Template type
            $table->enum('type', ['system', 'custom'])->default('custom');
            $table->boolean('is_active')->default(true);

            // SoD configuration
            $table->json('sod_restrictions')->nullable(); // ['cannot_approve_own' => true]

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('level');
            $table->index(['type', 'is_active']);
            $table->index('template_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_templates');
    }
};
