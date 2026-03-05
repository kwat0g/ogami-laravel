<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which shift an employee is on for a given date range.
 * Non-overlapping constraint enforced via EXCLUDE in PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->foreignId('shift_schedule_id')
                ->constrained('shift_schedules')
                ->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();   // NULL = open-ended
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });

        // Prevent overlapping shift assignments for the same employee
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement("
            ALTER TABLE employee_shift_assignments
            ADD CONSTRAINT no_overlapping_shifts
            EXCLUDE USING gist (
                employee_id WITH =,
                daterange(effective_from, COALESCE(effective_to, '9999-12-31'), '[]') WITH &&
            )
        ");

        DB::statement('ALTER TABLE employee_shift_assignments ADD CONSTRAINT chk_shift_dates
            CHECK (effective_to IS NULL OR effective_to > effective_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shift_assignments');
    }
};
