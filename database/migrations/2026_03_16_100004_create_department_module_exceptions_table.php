<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department Module Exceptions - Custom permissions for specific departments.
 *
 * When a department needs permissions different from its module default,
 * create an exception here.
 *
 * Example: The HR department at Plant B might need additional inventory access
 * that other HR departments don't have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_module_exceptions', function (Blueprint $table) {
            $table->id();

            // The department with custom permissions
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');

            // Which role in this department gets custom permissions
            $table->string('role', 50);

            // Permissions to ADD to the module default
            $table->json('permissions_add')->nullable();

            // Permissions to REMOVE from the module default
            $table->json('permissions_remove')->nullable();

            // Why this exception exists (for audit trail)
            $table->text('reason')->nullable();

            // Active status
            $table->boolean('is_active')->default(true);

            // Audit trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Unique constraint: one exception per department+role
            $table->unique(['department_id', 'role']);

            // Indexes
            $table->index(['department_id', 'role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_module_exceptions');
    }
};
