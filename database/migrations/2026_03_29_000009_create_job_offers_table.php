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
        Schema::create('job_offers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('application_id')->unique()->constrained('applications')->restrictOnDelete();
            $table->string('offer_number', 20)->unique();
            $table->foreignId('offered_position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('offered_department_id')->constrained('departments')->restrictOnDelete();
            $table->unsignedBigInteger('offered_salary');
            $table->string('employment_type', 30)->default('regular');
            $table->date('start_date');
            $table->string('offer_letter_path', 500)->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('expires_at');
        });

        DB::statement("ALTER TABLE job_offers ADD CONSTRAINT chk_jo_status CHECK (status IN ('draft','sent','accepted','rejected','expired','withdrawn'))");
        DB::statement("ALTER TABLE job_offers ADD CONSTRAINT chk_jo_employment_type CHECK (employment_type IN ('regular','contractual','project_based','part_time'))");
        DB::statement("ALTER TABLE job_offers ADD CONSTRAINT chk_jo_salary_nonneg CHECK (offered_salary > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
