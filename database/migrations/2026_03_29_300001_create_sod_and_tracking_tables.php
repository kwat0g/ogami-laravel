<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REC-01: SoD override audit log and approval delegates.
 * REC-16: Approval tracking for multi-step workflows.
 * REC-21: Notification delivery log for guaranteed delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        // REC-01: SoD override audit log
        Schema::create('sod_override_audit_log', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('override_type');         // payroll_hr_approve, ap_approve, etc.
            $table->string('entity_type');            // payroll_runs, vendor_invoices, etc.
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('original_actor_id');
            $table->unsignedBigInteger('granted_by_id');
            $table->text('reason');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at');
            $table->boolean('was_used')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('original_actor_id')->references('id')->on('users');
            $table->foreign('granted_by_id')->references('id')->on('users');
            $table->index(['entity_type', 'entity_id', 'override_type']);
        });

        // REC-01: Approval delegates (acting authority)
        Schema::create('approval_delegates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->unsignedBigInteger('delegator_id');
            $table->unsignedBigInteger('delegate_id');
            $table->string('permission_scope');       // payroll.hr_approve, payroll.vp_approve, etc.
            $table->date('effective_from');
            $table->date('effective_until');
            $table->text('reason');
            $table->unsignedBigInteger('created_by_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('delegator_id')->references('id')->on('users');
            $table->foreign('delegate_id')->references('id')->on('users');
            $table->foreign('created_by_id')->references('id')->on('users');
            $table->index(['permission_scope', 'effective_from', 'effective_until']);
        });

        // REC-16: Approval tracking for multi-step workflows
        Schema::create('approval_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('trackable_type');          // vendor_invoices, leave_requests, loans, payroll_runs
            $table->unsignedBigInteger('trackable_id');
            $table->unsignedSmallInteger('step_order');
            $table->string('step_name');               // head_noted, manager_checked, etc.
            $table->string('step_label');               // Department Head Review, Manager Check, etc.
            $table->string('status')->default('pending'); // pending, completed, returned, skipped
            $table->unsignedBigInteger('completed_by_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['trackable_type', 'trackable_id']);
            $table->foreign('completed_by_id')->references('id')->on('users');
        });

        // REC-21: Notification delivery log
        Schema::create('notification_delivery_log', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('notification_type');        // class name
            $table->string('channel');                  // mail, database, broadcast
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notifiable_type');
            $table->string('status')->default('pending'); // pending, sent, failed, retried
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('first_attempted_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'attempt_count']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_log');
        Schema::dropIfExists('approval_tracking');
        Schema::dropIfExists('approval_delegates');
        Schema::dropIfExists('sod_override_audit_log');
    }
};
