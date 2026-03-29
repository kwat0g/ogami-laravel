<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add QC workflow columns to goods_receipts and goods_receipt_items.
 *
 * Fixes:
 *   - 500 error on submit-for-qc (missing submitted_for_qc_* columns)
 *   - CHECK constraint blocking 'pending_qc' and 'returned' statuses
 *   - Missing return-to-supplier columns (returned_at, returned_by_id, return_reason)
 *
 * New GR statuses: draft -> pending_qc -> qc_passed|qc_failed -> partial_accept -> confirmed -> returned
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── GR header: QC submission fields ──────────────────────────────────
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('submitted_for_qc_by_id')
                ->nullable()
                ->after('rejected_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('submitted_for_qc_at')->nullable()->after('submitted_for_qc_by_id');

            // QC result tracking
            $table->string('qc_result', 20)->nullable()->after('submitted_for_qc_at');
            $table->timestamp('qc_completed_at')->nullable()->after('qc_result');
            $table->foreignId('qc_completed_by_id')
                ->nullable()
                ->after('qc_completed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('qc_notes')->nullable()->after('qc_completed_by_id');

            // Return-to-supplier fields
            $table->timestamp('returned_at')->nullable()->after('qc_notes');
            $table->foreignId('returned_by_id')
                ->nullable()
                ->after('returned_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('return_reason')->nullable()->after('returned_by_id');
        });

        // ── Update CHECK constraint to allow all workflow statuses ────────────
        // Drop both possible constraint names (original and rejection migration)
        DB::statement('ALTER TABLE goods_receipts DROP CONSTRAINT IF EXISTS chk_gr_status');
        DB::statement('ALTER TABLE goods_receipts DROP CONSTRAINT IF EXISTS goods_receipts_status_check');
        DB::statement("
            ALTER TABLE goods_receipts
            ADD CONSTRAINT chk_gr_status
                CHECK (status IN ('draft','pending_qc','qc_passed','qc_failed','partial_accept','confirmed','rejected','returned'))
        ");

        // ── QC result CHECK constraint ───────────────────────────────────────
        DB::statement("
            ALTER TABLE goods_receipts
            ADD CONSTRAINT chk_gr_qc_result
                CHECK (qc_result IS NULL OR qc_result IN ('passed','failed','partial'))
        ");

        // ── GR items: per-item QC tracking ───────────────────────────────────
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->string('qc_status', 30)->nullable()->after('remarks');
            $table->decimal('quantity_accepted', 12, 3)->nullable()->after('qc_status');
            $table->decimal('quantity_rejected', 12, 3)->nullable()->after('quantity_accepted');
            $table->foreignId('ncr_id')->nullable()->after('quantity_rejected')
                ->constrained('non_conformance_reports')->nullOnDelete();
            $table->string('defect_type', 30)->nullable()->after('ncr_id');
            $table->text('defect_description')->nullable()->after('defect_type');
        });

        DB::statement("
            ALTER TABLE goods_receipt_items
            ADD CONSTRAINT chk_gri_qc_status
                CHECK (qc_status IS NULL OR qc_status IN ('pending','passed','failed','accepted_with_ncr'))
        ");

        DB::statement("
            ALTER TABLE goods_receipt_items
            ADD CONSTRAINT chk_gri_defect_type
                CHECK (defect_type IS NULL OR defect_type IN ('cosmetic','dimensional','functional','material','other'))
        ");
    }

    public function down(): void
    {
        // ── Remove item-level QC fields ──────────────────────────────────────
        DB::statement('ALTER TABLE goods_receipt_items DROP CONSTRAINT IF EXISTS chk_gri_qc_status');
        DB::statement('ALTER TABLE goods_receipt_items DROP CONSTRAINT IF EXISTS chk_gri_defect_type');

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['ncr_id']);
            $table->dropColumn([
                'qc_status', 'quantity_accepted', 'quantity_rejected',
                'ncr_id', 'defect_type', 'defect_description',
            ]);
        });

        // ── Restore original CHECK constraint ────────────────────────────────
        DB::statement('ALTER TABLE goods_receipts DROP CONSTRAINT IF EXISTS chk_gr_status');
        DB::statement('ALTER TABLE goods_receipts DROP CONSTRAINT IF EXISTS chk_gr_qc_result');
        DB::statement("
            ALTER TABLE goods_receipts
            ADD CONSTRAINT goods_receipts_status_check
                CHECK (status IN ('draft','confirmed','rejected'))
        ");

        // ── Remove GR header QC fields ───────────────────────────────────────
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropForeign(['submitted_for_qc_by_id']);
            $table->dropForeign(['qc_completed_by_id']);
            $table->dropForeign(['returned_by_id']);
            $table->dropColumn([
                'submitted_for_qc_by_id', 'submitted_for_qc_at',
                'qc_result', 'qc_completed_at', 'qc_completed_by_id', 'qc_notes',
                'returned_at', 'returned_by_id', 'return_reason',
            ]);
        });
    }
};
