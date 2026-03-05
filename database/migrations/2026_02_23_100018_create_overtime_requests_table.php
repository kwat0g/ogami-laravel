<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OT must be pre-approved before hours count toward OT pay — ATT-005.
 * SoD: requester ≠ approver enforced at service layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->date('work_date');
            $table->time('ot_start_time');
            $table->time('ot_end_time');
            $table->unsignedSmallInteger('requested_minutes');
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending')
                ->comment('pending|approved|rejected|cancelled');
            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
            $table->index('status');
        });

        // Partial unique index: only one *active* OT request per employee per day.
        // Cancelled / rejected requests do not block re-filing.
        DB::statement("
            CREATE UNIQUE INDEX uq_ot_active_per_day
            ON overtime_requests (employee_id, work_date)
            WHERE status NOT IN ('cancelled', 'rejected')
        ");

        DB::statement("ALTER TABLE overtime_requests ADD CONSTRAINT chk_ot_status
            CHECK (status IN ('pending','supervisor_approved','pending_executive','approved','rejected','cancelled'))");

        DB::statement('ALTER TABLE overtime_requests ADD CONSTRAINT chk_ot_times
            CHECK (ot_end_time > ot_start_time)');

        DB::statement('ALTER TABLE overtime_requests ADD CONSTRAINT chk_ot_minutes
            CHECK (requested_minutes BETWEEN 1 AND 480)');
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
