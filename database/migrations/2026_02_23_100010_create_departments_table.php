<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Departments — organizational units used for RBAC scoping and payroll cost centers.
 *
 * EMP-006: Cannot assign to archived department.
 * DepartmentScope: Every model with department_id has this global scope applied.
 *
 * plant_id is included for multi-plant future-readiness (§18 of roadmap).
 * Adding a second plant requires only new department rows + user assignments;
 * no code change.
 *
 * Soft delete: department records cannot be hard-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique()->comment('Short code, e.g. HRD, PROD, ACCTG');
            $table->string('name', 150);
            $table->unsignedBigInteger('parent_department_id')->nullable();
            $table->unsignedInteger('plant_id')->nullable()->comment('Future multi-plant support');
            $table->string('cost_center_code', 30)->nullable()->comment('GL cost center for payroll posting');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('parent_department_id')->references('id')->on('departments')->nullOnDelete();
        });

        // Prevent a department from being its own parent
        DB::statement('
            ALTER TABLE departments
            ADD CONSTRAINT chk_dept_no_self_parent CHECK (parent_department_id IS NULL OR parent_department_id != id)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
