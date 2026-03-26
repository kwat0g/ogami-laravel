<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Negotiation tracking
            $table->text('vendor_remarks')->nullable()->after('notes');
            $table->unsignedSmallInteger('negotiation_round')->default(0)->after('vendor_remarks');
            $table->timestamp('change_requested_at')->nullable()->after('negotiation_round');
            $table->timestamp('change_reviewed_at')->nullable()->after('change_requested_at');
            $table->foreignId('change_reviewed_by_id')->nullable()->constrained('users')->nullOnDelete()->after('change_reviewed_at');
            $table->text('change_review_remarks')->nullable()->after('change_reviewed_by_id');
            $table->timestamp('vendor_acknowledged_at')->nullable()->after('change_review_remarks');
            $table->timestamp('in_transit_at')->nullable()->after('vendor_acknowledged_at');
            $table->string('tracking_number', 255)->nullable()->after('in_transit_at');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('negotiated_quantity', 15, 4)->nullable()->after('quantity_ordered');
            $table->text('vendor_item_notes')->nullable()->after('negotiated_quantity');
        });

        // Drop old CHECK constraints (both names used across different migration runs)
        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS chk_po_status');
        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft','sent','negotiating','acknowledged','in_transit','partially_received','fully_received','closed','cancelled'))");

        // Update VendorFulfillmentNotes note_type to include negotiation types
        DB::statement('ALTER TABLE vendor_fulfillment_notes DROP CONSTRAINT IF EXISTS chk_vfn_note_type');
        DB::statement('ALTER TABLE vendor_fulfillment_notes DROP CONSTRAINT IF EXISTS vendor_fulfillment_notes_note_type_check');
        DB::statement("ALTER TABLE vendor_fulfillment_notes ADD CONSTRAINT vendor_fulfillment_notes_note_type_check CHECK (note_type IN ('in_transit','delivered','partial','acknowledged','change_requested','change_accepted','change_rejected'))");
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['change_reviewed_by_id']);
            $table->dropColumn([
                'vendor_remarks', 'negotiation_round', 'change_requested_at',
                'change_reviewed_at', 'change_reviewed_by_id', 'change_review_remarks',
                'vendor_acknowledged_at', 'in_transit_at', 'tracking_number',
            ]);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['negotiated_quantity', 'vendor_item_notes']);
        });

        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft','sent','in_transit','partially_received','fully_received','closed','cancelled'))");

        DB::statement('ALTER TABLE vendor_fulfillment_notes DROP CONSTRAINT IF EXISTS vendor_fulfillment_notes_note_type_check');
        DB::statement("ALTER TABLE vendor_fulfillment_notes ADD CONSTRAINT vendor_fulfillment_notes_note_type_check CHECK (note_type IN ('in_transit','delivered','partial'))");
    }
};
