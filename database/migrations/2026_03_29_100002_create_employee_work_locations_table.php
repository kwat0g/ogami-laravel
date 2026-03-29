<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee-to-work-location assignment table.
 *
 * An employee can be assigned to one or more work locations with
 * effective date ranges. The is_primary flag determines which location
 * is used for geofence validation during time-in/out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_work_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->foreignId('work_location_id')
                ->constrained('work_locations')
                ->restrictOnDelete();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->foreignId('assigned_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_location_id', 'effective_date'], 'uq_emp_wl_effective');
            $table->index(['employee_id', 'is_primary']);
        });

        DB::statement('ALTER TABLE employee_work_locations ADD CONSTRAINT chk_ewl_dates
            CHECK (end_date IS NULL OR end_date > effective_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_locations');
    }
};
