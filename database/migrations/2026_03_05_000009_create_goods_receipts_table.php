<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2D: Create goods_receipts and goods_receipt_items tables.
 *
 * A GR is created by a Warehouse Head against a SENT or PARTIALLY_RECEIVED PO.
 * Confirming a GR triggers the three-way match check (PR ↔ PO ↔ GR) which
 * auto-creates an AP invoice draft if the match passes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS goods_receipt_seq START 1');

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('gr_reference', 30)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('received_by_id')->constrained('users');
            $table->date('received_date')->default(DB::raw('CURRENT_DATE'));
            $table->string('delivery_note_number', 100)->nullable();
            $table->text('condition_notes')->nullable();
            $table->string('status', 20)->default('draft');

            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->boolean('three_way_match_passed')->default(false);
            $table->boolean('ap_invoice_created')->default(false);
            $table->unsignedBigInteger('ap_invoice_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_order_id');
            $table->index('status');
        });

        DB::statement("
            ALTER TABLE goods_receipts
            ADD CONSTRAINT chk_gr_status
                CHECK (status IN ('draft','confirmed'))
        ");

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('po_item_id')->constrained('purchase_order_items');
            $table->decimal('quantity_received', 12, 3);
            $table->string('unit_of_measure', 30);
            $table->string('condition', 20)->default('good');
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("
            ALTER TABLE goods_receipt_items
            ADD CONSTRAINT chk_gri_qty_positive CHECK (quantity_received > 0),
            ADD CONSTRAINT chk_gri_condition
                CHECK (condition IN ('good','damaged','partial','rejected'))
        ");

        DB::statement('CREATE SEQUENCE IF NOT EXISTS goods_receipt_seq START 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        DB::statement('DROP SEQUENCE IF EXISTS goods_receipt_seq');
    }
};
