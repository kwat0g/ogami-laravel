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
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('posting_number', 20)->unique();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description');
            $table->text('requirements');
            $table->string('location', 255)->nullable();
            $table->string('employment_type', 30)->default('regular');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_external')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('job_requisition_id');
            $table->index('published_at');
            $table->index('closes_at');
        });

        DB::statement("ALTER TABLE job_postings ADD CONSTRAINT chk_jp_status CHECK (status IN ('draft','published','closed','expired'))");
        DB::statement("ALTER TABLE job_postings ADD CONSTRAINT chk_jp_employment_type CHECK (employment_type IN ('regular','contractual','project_based','part_time'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
