<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave requests state machine:
 *   draft → submitted → approved | rejected → cancelled
 *
 * SoD (LV-004): submitted_by ≠ reviewed_by (enforced at service layer).
 * LV-001: balance deducted only when status = approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->restrictOnDelete();
            $table->foreignId('leave_balance_id')
                ->nullable()
                ->constrained('leave_balances')
                ->nullOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('total_days', 5, 2);
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_period', 10)->nullable()
                ->comment('am|pm');
            $table->text('reason');
            $table->string('status', 20)->default('draft');
            $table->foreignId('submitted_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['date_from', 'date_to']);
        });

        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT chk_lr_status
            CHECK (status IN ('draft','submitted','approved','rejected','cancelled'))");

        DB::statement('ALTER TABLE leave_requests ADD CONSTRAINT chk_lr_date_order
            CHECK (date_to >= date_from)');

        DB::statement('ALTER TABLE leave_requests ADD CONSTRAINT chk_lr_total_days
            CHECK (total_days > 0 AND total_days <= 365)');

        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT chk_lr_half_day
            CHECK (half_day_period IS NULL OR half_day_period IN ('am','pm'))");

        // LV-004: same user cannot be both submitter and reviewer (additional check in service)
        DB::statement('ALTER TABLE leave_requests ADD CONSTRAINT chk_lr_sod
            CHECK (reviewed_by IS NULL OR reviewed_by <> submitted_by)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
