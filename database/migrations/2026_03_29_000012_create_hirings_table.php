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
        Schema::create('hirings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('application_id')->unique()->constrained('applications')->restrictOnDelete();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->timestamp('hired_at')->nullable();
            $table->date('start_date');
            $table->foreignId('hired_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('job_requisition_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE hirings ADD CONSTRAINT chk_hirings_status CHECK (status IN ('pending','hired','failed_preemployment'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('hirings');
    }
};
