<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Budget Amendment module — Item 51 from enhancement plan.
 * Supports mid-year budget revisions with approval workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_amendments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('cost_center_id')->constrained('cost_centers');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('amendment_type', 20); // reallocation, increase, decrease
            $table->foreignId('source_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('target_account_id')->constrained('chart_of_accounts');
            $table->unsignedBigInteger('amount_centavos');
            $table->text('justification');
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, rejected
            $table->foreignId('requested_by_id')->constrained('users');
            $table->foreignId('approved_by_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cost_center_id', 'fiscal_year']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE budget_amendments ADD CONSTRAINT chk_ba_type CHECK (amendment_type IN ('reallocation', 'increase', 'decrease'))");
        DB::statement("ALTER TABLE budget_amendments ADD CONSTRAINT chk_ba_status CHECK (status IN ('draft', 'submitted', 'approved', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_amendments');
    }
};
