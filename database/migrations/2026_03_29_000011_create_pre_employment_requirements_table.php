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
        Schema::create('pre_employment_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pre_employment_checklist_id')->constrained('pre_employment_checklists')->cascadeOnDelete();
            $table->string('requirement_type', 50);
            $table->string('label', 255);
            $table->boolean('is_required')->default(true);
            $table->string('status', 30)->default('pending');
            $table->string('document_path', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('pre_employment_checklist_id', 'idx_per_checklist_id');
        });

        DB::statement("ALTER TABLE pre_employment_requirements ADD CONSTRAINT chk_per_status CHECK (status IN ('pending','submitted','verified','rejected','waived'))");
        DB::statement("ALTER TABLE pre_employment_requirements ADD CONSTRAINT chk_per_type CHECK (requirement_type IN ('nbi_clearance','medical_certificate','tin','sss','philhealth','pagibig','birth_certificate','diploma','transcript','id_photo','other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_employment_requirements');
    }
};
