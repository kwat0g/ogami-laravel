<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the department_permission_profiles table.
 *
 * Each row represents the set of permissions granted to a specific role
 * when that role is assigned to a specific department (e.g. HRD hr_manager,
 * ACCTG Supervisor).  The User::hasPermissionTo() override reads from this
 * table (via DepartmentPermissionService) instead of a PHP config file,
 * allowing per-department permission profiles to be updated at runtime
 * without any code changes.
 *
 * Unique constraint: one profile per (department, role) pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_permission_profiles', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            // Role name — matches the Spatie role slug ('hr_manager', 'accounting_manager', 'supervisor')
            $table->string('role', 50);

            // JSON array of Spatie permission names granted to this dept+role combo
            $table->json('permissions');

            // Human-readable label (e.g. "HRD Manager", "ACCTG Supervisor")
            $table->string('profile_label', 120)->nullable();

            // Soft-disable a profile without deleting it
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // One profile per department × role
            $table->unique(['department_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_permission_profiles');
    }
};
