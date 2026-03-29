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
        Schema::create('interview_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_schedule_id')->unique()->constrained('interview_schedules')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->jsonb('scorecard');
            $table->decimal('overall_score', 3, 2)->default(0);
            $table->string('recommendation', 30);
            $table->text('general_remarks')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE interview_evaluations ADD CONSTRAINT chk_ie_recommendation CHECK (recommendation IN ('endorse','reject','hold'))");
        DB::statement("ALTER TABLE interview_evaluations ADD CONSTRAINT chk_ie_score CHECK (overall_score >= 0 AND overall_score <= 5)");
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_evaluations');
    }
};
