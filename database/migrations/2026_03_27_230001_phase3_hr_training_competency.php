<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4 — HR Training and Competency management.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('internal');
            $table->string('provider')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('cost_centavos')->default(0);
            $table->string('status', 30)->default('scheduled');
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE trainings ADD CONSTRAINT chk_trainings_type CHECK (type IN ('internal','external','online','on_the_job'))");
        DB::statement("ALTER TABLE trainings ADD CONSTRAINT chk_trainings_status CHECK (status IN ('scheduled','in_progress','completed','cancelled'))");

        Schema::create('training_attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('status', 30)->default('enrolled');
            $table->unsignedSmallInteger('score')->nullable();
            $table->boolean('passed')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['training_id', 'employee_id']);
        });

        DB::statement("ALTER TABLE training_attendees ADD CONSTRAINT chk_training_attendees_status CHECK (status IN ('enrolled','attended','completed','no_show','cancelled'))");

        Schema::create('competency_matrices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('skill_name');
            $table->string('category', 50)->nullable();
            $table->unsignedSmallInteger('current_level')->default(1);
            $table->unsignedSmallInteger('required_level')->default(1);
            $table->date('assessed_at')->nullable();
            $table->foreignId('assessed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE competency_matrices ADD CONSTRAINT chk_competency_level CHECK (current_level >= 1 AND current_level <= 5)');
        DB::statement('ALTER TABLE competency_matrices ADD CONSTRAINT chk_competency_required CHECK (required_level >= 1 AND required_level <= 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_matrices');
        Schema::dropIfExists('training_attendees');
        Schema::dropIfExists('trainings');
    }
};
