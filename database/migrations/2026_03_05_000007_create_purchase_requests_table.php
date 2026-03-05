<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2A: Create purchase_requests and purchase_request_items tables.
 *
 * State machine:
 *   draft → submitted → noted → checked → reviewed → approved → converted_to_po
 *                ↓            ↓          ↓           ↓           (at any stage)
 *             cancelled     rejected  rejected    rejected       rejected
 *
 * SoD constraints (SOD-011 through SOD-014):
 *   noted_by_id   ≠ submitted_by_id  (Head ≠ submitter)
 *   checked_by_id ≠ noted_by_id      (Manager ≠ Head)
 *   reviewed_by_id≠ checked_by_id    (Officer ≠ Manager)
 *   vp_approved_by_id ≠ reviewed_by_id (VP ≠ Officer)
 *
 * total_estimated_cost is updated by the trg_pr_total trigger (not GENERATED AS,
 * since PostgreSQL GENERATED columns cannot reference other tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Sequence for reference number ────────────────────────────────────
        DB::statement('CREATE SEQUENCE IF NOT EXISTS purchase_request_seq START 1');

        // ── purchase_requests ────────────────────────────────────────────────
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('pr_reference', 30)->unique();
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('requested_by_id')->constrained('users');
            $table->string('urgency', 20)->default('normal');
            $table->text('justification');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft');

            // Approval chain actors
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('noted_by_id')->nullable()->constrained('users')->nullOnDelete();   // Head
            $table->timestamp('noted_at')->nullable();
            $table->text('noted_comments')->nullable();

            $table->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();  // Manager
            $table->timestamp('checked_at')->nullable();
            $table->text('checked_comments')->nullable();

            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete(); // Officer
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewed_comments')->nullable();

            $table->foreignId('vp_approved_by_id')->nullable()->constrained('users')->nullOnDelete(); // VP
            $table->timestamp('vp_approved_at')->nullable();
            $table->text('vp_comments')->nullable();

            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('rejection_stage', 20)->nullable();

            // Set after PO creation (deferred FK update via service layer)
            $table->unsignedBigInteger('converted_to_po_id')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->decimal('total_estimated_cost', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('department_id');
            $table->index('requested_by_id');
        });

        // ── Constraints ───────────────────────────────────────────────────────
        DB::statement("
            ALTER TABLE purchase_requests
            ADD CONSTRAINT chk_pr_urgency
                CHECK (urgency IN ('normal','urgent','critical')),
            ADD CONSTRAINT chk_pr_status
                CHECK (status IN ('draft','submitted','noted','checked','reviewed','approved','rejected','cancelled','converted_to_po')),
            ADD CONSTRAINT chk_pr_sod_noted
                CHECK (noted_by_id IS NULL OR noted_by_id <> submitted_by_id),
            ADD CONSTRAINT chk_pr_sod_checked
                CHECK (checked_by_id IS NULL OR checked_by_id <> noted_by_id),
            ADD CONSTRAINT chk_pr_sod_reviewed
                CHECK (reviewed_by_id IS NULL OR reviewed_by_id <> checked_by_id),
            ADD CONSTRAINT chk_pr_sod_vp
                CHECK (vp_approved_by_id IS NULL OR vp_approved_by_id <> reviewed_by_id)
        ");

        // ── purchase_request_items ────────────────────────────────────────────
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->string('item_description', 255);
            $table->string('unit_of_measure', 30);
            $table->decimal('quantity', 12, 3);
            $table->decimal('estimated_unit_cost', 12, 2);
            $table->decimal('estimated_total', 15, 2)->storedAs('quantity * estimated_unit_cost');
            $table->text('specifications')->nullable();
            $table->smallInteger('line_order')->default(1);
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("
            ALTER TABLE purchase_request_items
            ADD CONSTRAINT chk_pri_qty_positive  CHECK (quantity > 0),
            ADD CONSTRAINT chk_pri_cost_positive CHECK (estimated_unit_cost > 0)
        ");

        // ── Trigger: update total_estimated_cost on item changes ─────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION update_pr_total()
            RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
            BEGIN
                UPDATE purchase_requests
                SET total_estimated_cost = (
                    SELECT COALESCE(SUM(estimated_total), 0)
                    FROM purchase_request_items
                    WHERE purchase_request_id = COALESCE(NEW.purchase_request_id, OLD.purchase_request_id)
                ),
                updated_at = NOW()
                WHERE id = COALESCE(NEW.purchase_request_id, OLD.purchase_request_id);
                RETURN NEW;
            END;
            \$\$
        ");

        DB::statement("
            CREATE TRIGGER trg_pr_total
            AFTER INSERT OR UPDATE OR DELETE ON purchase_request_items
            FOR EACH ROW EXECUTE FUNCTION update_pr_total()
        ");

        // ── Indexes ──────────────────────────────────────────────────────────
        DB::statement('CREATE INDEX idx_pr_status     ON purchase_requests(status)');
        DB::statement('CREATE INDEX idx_pr_department ON purchase_requests(department_id)');
        DB::statement('CREATE INDEX idx_pr_reference  ON purchase_requests(pr_reference)');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_pr_total ON purchase_request_items');
        DB::statement('DROP FUNCTION IF EXISTS update_pr_total()');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        DB::statement('DROP SEQUENCE IF EXISTS purchase_request_seq');
    }
};
