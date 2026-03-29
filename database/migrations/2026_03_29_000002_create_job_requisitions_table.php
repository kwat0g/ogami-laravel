<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('requisition_number', 20)->unique();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employment_type', 30)->default('regular');
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->text('reason');
            $table->text('justification')->nullable();
            $table->unsignedBigInteger('salary_range_min')->nullable();
            $table->unsignedBigInteger('salary_range_max')->nullable();
            $table->date('target_start_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('department_id');
            $table->index('requested_by');
            $table->index('created_at');
        });

        DB::statement("ALTER TABLE job_requisitions ADD CONSTRAINT chk_jr_status CHECK (status IN ('draft','pending_approval','approved','rejected','open','on_hold','closed','cancelled'))");
        DB::statement("ALTER TABLE job_requisitions ADD CONSTRAINT chk_jr_employment_type CHECK (employment_type IN ('regular','contractual','project_based','part_time'))");
        DB::statement("ALTER TABLE job_requisitions ADD CONSTRAINT chk_jr_salary_range CHECK (salary_range_max IS NULL OR salary_range_min IS NULL OR salary_range_max >= salary_range_min)");
        DB::statement("ALTER TABLE job_requisitions ADD CONSTRAINT chk_jr_headcount CHECK (headcount >= 1)");
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requisitions');
    }
};
