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
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('application_number', 20)->unique();
            $table->foreignId('job_posting_id')->constrained('job_postings')->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->text('cover_letter')->nullable();
            $table->date('application_date');
            $table->string('source', 30)->default('walk_in');
            $table->string('status', 30)->default('new');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('withdrawn_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['job_posting_id', 'candidate_id'], 'uq_applications_posting_candidate');
            $table->index('status');
            $table->index('candidate_id');
            $table->index('application_date');
        });

        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_app_status CHECK (status IN ('new','under_review','shortlisted','rejected','withdrawn'))");
        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_app_source CHECK (source IN ('referral','walk_in','job_board','agency','internal'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
