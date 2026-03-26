<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add module_key to departments table.
 *
 * This links each department to a permission module.
 * Example:
 *   - HR department → module_key: 'hr'
 *   - Accounting department → module_key: 'accounting'
 *   - Production department → module_key: 'production'
 *
 * The FK constraint ensures only valid module_keys can be assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Module key links to modules table (nullable for backward compatibility)
            $table->string('module_key', 50)->nullable()->after('is_active');

            // Optional: Custom permission overrides for this specific department
            $table->json('permissions_override')->nullable()->after('module_key');

            // Foreign key constraint prevents invalid module assignments
            // RESTRICT: Cannot delete module if departments are using it
            $table->foreign('module_key')
                ->references('module_key')
                ->on('modules')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            // Index for faster lookups
            $table->index('module_key');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['module_key']);

            // Drop columns
            $table->dropColumn('module_key');
            $table->dropColumn('permissions_override');
        });
    }
};
