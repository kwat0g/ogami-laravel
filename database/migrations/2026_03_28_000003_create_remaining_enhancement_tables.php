<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates tables for remaining enhancement items:
 *   - blanket_purchase_orders (Item 31)
 *   - stock_quarantine_log (Item 36)
 *   - Adds POD columns to delivery_receipts (Item 42)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Blanket Purchase Orders (Item 31) ──────────────────────────────
        Schema::create('blanket_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('bpo_reference', 30)->unique();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('committed_amount_centavos');
            $table->unsignedBigInteger('released_amount_centavos')->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('terms')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'status']);
        });

        DB::statement("ALTER TABLE blanket_purchase_orders ADD CONSTRAINT chk_bpo_status CHECK (status IN ('draft', 'active', 'expired', 'closed'))");

        // Add blanket_po_id to purchase_orders for release tracking
        if (! Schema::hasColumn('purchase_orders', 'blanket_po_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('blanket_po_id')->nullable()->after('vendor_id')
                    ->constrained('blanket_purchase_orders')->nullOnDelete();
            });
        }

        // Create sequence for blanket PO reference numbers
        DB::statement("CREATE SEQUENCE IF NOT EXISTS blanket_po_seq START WITH 1 INCREMENT BY 1");

        // ── Stock Quarantine Log (Item 36) ─────────────────────────────────
        Schema::create('stock_quarantine_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('item_masters');
            $table->decimal('quantity', 14, 4);
            $table->foreignId('quarantine_location_id')->constrained('warehouse_locations');
            $table->foreignId('source_location_id')->constrained('warehouse_locations');
            $table->foreignId('target_location_id')->nullable()->constrained('warehouse_locations');
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('quarantined');
            $table->text('remarks')->nullable();
            $table->foreignId('quarantined_by_id')->constrained('users');
            $table->timestamp('quarantined_at');
            $table->foreignId('released_by_id')->nullable()->constrained('users');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE stock_quarantine_log ADD CONSTRAINT chk_quarantine_status CHECK (status IN ('quarantined', 'released', 'return_to_vendor', 'scrap'))");

        // ── Delivery Receipt POD columns (Item 42) ─────────────────────────
        if (! Schema::hasColumn('delivery_receipts', 'pod_receiver_name')) {
            Schema::table('delivery_receipts', function (Blueprint $table) {
                $table->string('pod_receiver_name', 200)->nullable()->after('remarks');
                $table->string('pod_receiver_designation', 100)->nullable();
                $table->string('pod_signature_path', 500)->nullable();
                $table->string('pod_photo_path', 500)->nullable();
                $table->decimal('pod_latitude', 10, 7)->nullable();
                $table->decimal('pod_longitude', 10, 7)->nullable();
                $table->text('pod_notes')->nullable();
                $table->timestamp('pod_recorded_at')->nullable();
                $table->foreignId('pod_recorded_by_id')->nullable()->constrained('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_quarantine_log');

        if (Schema::hasColumn('purchase_orders', 'blanket_po_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('blanket_po_id');
            });
        }

        Schema::dropIfExists('blanket_purchase_orders');
        DB::statement('DROP SEQUENCE IF EXISTS blanket_po_seq');

        if (Schema::hasColumn('delivery_receipts', 'pod_receiver_name')) {
            Schema::table('delivery_receipts', function (Blueprint $table) {
                $table->dropColumn([
                    'pod_receiver_name', 'pod_receiver_designation',
                    'pod_signature_path', 'pod_photo_path',
                    'pod_latitude', 'pod_longitude', 'pod_notes',
                    'pod_recorded_at', 'pod_recorded_by_id',
                ]);
            });
        }
    }
};
