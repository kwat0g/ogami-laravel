<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-007: Configurable approval workflow engine.
 *
 * Defines approval chains per document type, with support for:
 * - Sequential multi-level approvals
 * - Amount-based routing (different chain for POs over PHP 100k)
 * - Department-specific chains
 * - Configurable required permission per step
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflow_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 50)->comment('e.g. leave_request, loan, purchase_request, purchase_order, payroll_run, budget');
            $table->unsignedSmallInteger('step_order')->comment('Sequential step number (1, 2, 3...)');
            $table->string('step_name', 80)->comment('Human-readable step name, e.g. Department Head Approval');
            $table->string('required_permission', 100)->comment('Permission required to act at this step, e.g. leaves.head_approve');
            $table->string('target_status', 50)->comment('Status the document moves to after this step');
            $table->unsignedBigInteger('amount_threshold_centavos')->nullable()->comment('If set, this step only applies when document amount >= this threshold');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->comment('If set, this chain only applies to this department');
            $table->boolean('sod_with_creator')->default(true)->comment('Enforce SoD: approver cannot be the creator');
            $table->boolean('sod_with_previous_step')->default(false)->comment('Enforce SoD: approver cannot be the previous step approver');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['document_type', 'is_active', 'step_order'], 'idx_workflow_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_configs');
    }
};
