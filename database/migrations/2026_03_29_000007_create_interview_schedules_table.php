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
        Schema::create('interview_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->unsignedTinyInteger('round')->default(1);
            $table->string('type', 30)->default('hr_screening');
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('location', 500)->nullable();
            $table->foreignId('interviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('application_id');
            $table->index('interviewer_id');
            $table->index('scheduled_at');
            $table->index('status');
        });

        DB::statement("ALTER TABLE interview_schedules ADD CONSTRAINT chk_is_status CHECK (status IN ('scheduled','in_progress','completed','cancelled','no_show'))");
        DB::statement("ALTER TABLE interview_schedules ADD CONSTRAINT chk_is_type CHECK (type IN ('panel','one_on_one','technical','hr_screening','final'))");
        DB::statement("ALTER TABLE interview_schedules ADD CONSTRAINT chk_is_round CHECK (round >= 1)");
        DB::statement("ALTER TABLE interview_schedules ADD CONSTRAINT chk_is_duration CHECK (duration_minutes >= 15)");
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_schedules');
    }
};
