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
        Schema::table('interview_schedules', function (Blueprint $table): void {
            $table->foreignId('interviewer_department_id')
                ->nullable()
                ->after('interviewer_id')
                ->constrained('departments')
                ->nullOnDelete();

            $table->dropForeign(['interviewer_id']);
            $table->foreignId('interviewer_id')->nullable()->change();
            $table->foreign('interviewer_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE interview_schedules ADD CONSTRAINT chk_is_interviewer_assignment CHECK (interviewer_id IS NOT NULL OR interviewer_department_id IS NOT NULL)');

        Schema::table('hirings', function (Blueprint $table): void {
            $table->jsonb('employee_payload')->nullable()->after('employee_id');
            $table->foreignId('submitted_by_id')->nullable()->after('start_date')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by_id');
            $table->foreignId('vp_approved_by_id')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('vp_approved_at')->nullable()->after('vp_approved_by_id');
            $table->foreignId('vp_rejected_by_id')->nullable()->after('vp_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('vp_rejected_at')->nullable()->after('vp_rejected_by_id');
            $table->text('vp_rejection_reason')->nullable()->after('vp_rejected_at');
        });

        DB::statement('ALTER TABLE hirings DROP CONSTRAINT IF EXISTS chk_hirings_status');
        DB::statement("ALTER TABLE hirings ADD CONSTRAINT chk_hirings_status CHECK (status IN ('pending','pending_vp_approval','hired','failed_preemployment','rejected_by_vp'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE hirings DROP CONSTRAINT IF EXISTS chk_hirings_status');
        DB::statement("ALTER TABLE hirings ADD CONSTRAINT chk_hirings_status CHECK (status IN ('pending','hired','failed_preemployment'))");

        Schema::table('hirings', function (Blueprint $table): void {
            $table->dropForeign(['submitted_by_id']);
            $table->dropForeign(['vp_approved_by_id']);
            $table->dropForeign(['vp_rejected_by_id']);
            $table->dropColumn([
                'employee_payload',
                'submitted_by_id',
                'submitted_at',
                'vp_approved_by_id',
                'vp_approved_at',
                'vp_rejected_by_id',
                'vp_rejected_at',
                'vp_rejection_reason',
            ]);
        });

        DB::statement('ALTER TABLE interview_schedules DROP CONSTRAINT IF EXISTS chk_is_interviewer_assignment');

        Schema::table('interview_schedules', function (Blueprint $table): void {
            $table->dropForeign(['interviewer_department_id']);
            $table->dropForeign(['interviewer_id']);
            $table->dropColumn('interviewer_department_id');
            $table->foreignId('interviewer_id')->nullable(false)->change();
            $table->foreign('interviewer_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
