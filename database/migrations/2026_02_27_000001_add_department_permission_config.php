<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add department code mapping config to prevent hardcoded code dependencies
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add permission_profile_role column to departments
        // This allows any department code to use a predefined permission profile
        Schema::table('departments', function (Blueprint $table) {
            $table->string('permission_profile_role', 30)
                ->nullable()
                ->after('is_active')
                ->comment('Maps to DepartmentPermissionProfile role (manager/supervisor/custom)');
            $table->json('custom_permissions')
                ->nullable()
                ->after('permission_profile_role')
                ->comment('Override permissions for this department');
        });

        // Create department profile templates table
        Schema::create('department_permission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 50)->unique(); // 'accounting', 'hr', 'operations'
            $table->string('label', 100);
            $table->json('manager_permissions');
            $table->json('supervisor_permissions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['permission_profile_role', 'custom_permissions']);
        });
        Schema::dropIfExists('department_permission_templates');
    }
};
