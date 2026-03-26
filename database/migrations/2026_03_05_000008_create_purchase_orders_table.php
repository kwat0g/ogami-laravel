<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2B: Create purchase_orders and purchase_order_items tables.
 *
 * A PO is created by a Purchasing Officer from an APPROVED PR.
 * quantity_pending and total_cost are PostgreSQL GENERATED ALWAYS AS columns.
 *
 * State machine:
 *   draft → sent → partially_received → fully_received → closed (auto)
 *   draft/sent → cancelled
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS purchase_order_seq START 1');

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('po_reference', 30)->unique();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->date('po_date')->default(DB::raw('CURRENT_DATE'));
            $table->date('delivery_date');
            $table->string('payment_terms', 50);
            $table->text('delivery_address')->nullable();
            $table->string('status', 30)->default('draft');
            $table->decimal('total_po_amount', 15, 2)->default(0);
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('vendor_id');
            $table->index('purchase_request_id');
        });

        DB::statement("
            ALTER TABLE purchase_orders
            ADD CONSTRAINT chk_po_status
                CHECK (status IN ('draft','sent','partially_received','fully_received','closed','cancelled')),
            ADD CONSTRAINT chk_po_delivery_after_po_date
                CHECK (delivery_date >= po_date)
        ");

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('pr_item_id')->nullable()->constrained('purchase_request_items')->nullOnDelete();
            $table->string('item_description', 255);
            $table->string('unit_of_measure', 30);
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('agreed_unit_cost', 12, 2);
            $table->decimal('total_cost', 15, 2)->storedAs('quantity_ordered * agreed_unit_cost');
            $table->decimal('quantity_received', 12, 3)->default(0);
            $table->decimal('quantity_pending', 12, 3)->storedAs('quantity_ordered - quantity_received');
            $table->smallInteger('line_order')->default(1);
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE purchase_order_items
            ADD CONSTRAINT chk_poi_qty_positive     CHECK (quantity_ordered > 0),
            ADD CONSTRAINT chk_poi_cost_positive    CHECK (agreed_unit_cost > 0),
            ADD CONSTRAINT chk_poi_received_valid   CHECK (quantity_received >= 0 AND quantity_received <= quantity_ordered)
        ');

        // Trigger: update PO total when items change
        DB::statement('
            CREATE OR REPLACE FUNCTION update_po_total()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE purchase_orders
                SET total_po_amount = (
                    SELECT COALESCE(SUM(total_cost), 0)
                    FROM purchase_order_items
                    WHERE purchase_order_id = COALESCE(NEW.purchase_order_id, OLD.purchase_order_id)
                ),
                updated_at = NOW()
                WHERE id = COALESCE(NEW.purchase_order_id, OLD.purchase_order_id);
                RETURN NEW;
            END;
            $$
        ');

        DB::statement('
            CREATE TRIGGER trg_po_total
            AFTER INSERT OR UPDATE OR DELETE ON purchase_order_items
            FOR EACH ROW EXECUTE FUNCTION update_po_total()
        ');

        DB::statement('CREATE INDEX idx_po_status ON purchase_orders(status)');
        DB::statement('CREATE INDEX idx_po_vendor ON purchase_orders(vendor_id)');
        DB::statement('CREATE INDEX idx_po_pr    ON purchase_orders(purchase_request_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_po_total ON purchase_order_items');
        DB::statement('DROP FUNCTION IF EXISTS update_po_total()');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        DB::statement('DROP SEQUENCE IF EXISTS purchase_order_seq');
    }
};
