<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP 4 + 5: Add rejection disposition tracking to GR items and
 * quantity_rejected tracking to PO items.
 *
 * - goods_receipt_items.reject_disposition: what happens to rejected qty
 * - purchase_order_items.quantity_rejected: tracks QC-rejected quantities
 *   (distinct from quantity_pending which only tracks undelivered)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── GR item: rejection disposition ────────────────────────────────────
        if (! Schema::hasColumn('goods_receipt_items', 'reject_disposition')) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                $table->string('reject_disposition', 30)->nullable()->after('defect_description');
                $table->timestamp('disposition_completed_at')->nullable()->after('reject_disposition');
            });

            DB::statement("
                ALTER TABLE goods_receipt_items
                ADD CONSTRAINT chk_gri_reject_disposition
                    CHECK (reject_disposition IS NULL OR reject_disposition IN ('return_to_vendor','scrap','rework','accept_as_is'))
            ");
        }

        // ── PO item: rejected quantity tracking ──────────────────────────────
        if (! Schema::hasColumn('purchase_order_items', 'quantity_rejected')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->decimal('quantity_rejected', 12, 3)->default(0)->after('quantity_received');
            });
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE goods_receipt_items DROP CONSTRAINT IF EXISTS chk_gri_reject_disposition');

        if (Schema::hasColumn('goods_receipt_items', 'reject_disposition')) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                $table->dropColumn(['reject_disposition', 'disposition_completed_at']);
            });
        }

        if (Schema::hasColumn('purchase_order_items', 'quantity_rejected')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('quantity_rejected');
            });
        }
    }
};
