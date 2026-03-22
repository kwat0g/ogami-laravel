<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules - Reference table for department permission modules.
 * 
 * This enables scalable RBAC where:
 * - 7 generic roles (superadmin, admin, executive, vp, manager, officer, head, staff)
 * - Permissions are determined by Role + Department Module
 * 
 * Example: manager + HR module = Full HR access
 *          manager + Accounting module = Full Accounting access
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            
            // Module key - acts as ENUM via FK constraint (prevents typos)
            $table->string('module_key', 50)->unique();
            
            // Display label
            $table->string('label', 100);
            
            // Description of what this module covers
            $table->text('description')->nullable();
            
            // Default permissions for this module (used as fallback)
            $table->json('default_permissions')->nullable();
            
            // Permission groups this module belongs to (for UI organization)
            $table->json('permission_groups')->nullable();
            
            // Active status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index('module_key');
            $table->index(['is_active', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
