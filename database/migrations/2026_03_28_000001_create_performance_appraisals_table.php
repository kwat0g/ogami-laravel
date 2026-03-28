<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR Performance Appraisal module — Item 54 from enhancement plan.
 *
 * Creates tables for periodic employee performance evaluations with
 * weighted KPI criteria and a 4-step approval workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_appraisals', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users');
            $table->string('review_type', 30); // annual, mid_year, probationary, project_based
            $table->date('review_period_start');
            $table->date('review_period_end');
            $table->string('status', 30)->default('draft'); // draft, submitted, manager_reviewed, hr_approved, completed
            $table->unsignedSmallInteger('overall_rating_pct')->nullable(); // 0-100
            $table->text('employee_comments')->nullable();
            $table->text('reviewer_comments')->nullable();
            $table->text('hr_comments')->nullable();
            $table->foreignId('hr_approved_by_id')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'review_period_end']);
            $table->index('status');
        });

        // CHECK constraint for review_type (PG-compatible)
        DB::statement("ALTER TABLE performance_appraisals ADD CONSTRAINT chk_pa_review_type CHECK (review_type IN ('annual', 'mid_year', 'probationary', 'project_based'))");

        // CHECK constraint for status
        DB::statement("ALTER TABLE performance_appraisals ADD CONSTRAINT chk_pa_status CHECK (status IN ('draft', 'submitted', 'manager_reviewed', 'hr_approved', 'completed'))");

        Schema::create('performance_appraisal_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained('performance_appraisals')->cascadeOnDelete();
            $table->string('criteria_name', 200);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('weight_pct'); // 0-100, all should sum to 100
            $table->unsignedSmallInteger('rating_pct')->nullable(); // 0-100, filled during review
            $table->text('comments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_appraisal_criteria');
        Schema::dropIfExists('performance_appraisals');
    }
};
