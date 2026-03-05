<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates payroll_run_exclusions table.
 *
 * Stores manually excluded employees for a specific payroll run.
 * Each exclusion requires a written reason (for audit purposes).
 * The excluding manager is notified via the notification system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_exclusions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            $table->text('reason');

            $table->foreignId('excluded_by_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('excluded_at')->useCurrent();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_exclusions');
    }
};
