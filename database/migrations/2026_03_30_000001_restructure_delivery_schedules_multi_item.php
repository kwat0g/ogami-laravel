<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure delivery_schedules to support multi-item records.
 *
 * BEFORE: 1 delivery_schedule per item, grouped by combined_delivery_schedules
 * AFTER:  1 delivery_schedule per order, with delivery_schedule_items as children
 *
 * This migration:
 * 1. Creates delivery_schedule_items table
 * 2. Migrates existing per-item DS rows into items of their combined parent
 * 3. Absorbs combined_delivery_schedules fields into delivery_schedules
 * 4. Updates production_orders to reference delivery_schedule_items
 * 5. Cleans up old tables
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Create delivery_schedule_items table ─────────────────────
        Schema::create('delivery_schedule_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('delivery_schedule_id')->constrained('delivery_schedules')->cascadeOnDelete();
            $table->foreignId('product_item_id')->constrained('item_masters');
            $table->decimal('qty_ordered', 15, 4);
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['delivery_schedule_id', 'status']);
            $table->index('product_item_id');
        });

        DB::statement("
            ALTER TABLE delivery_schedule_items ADD CONSTRAINT chk_dsi_status
            CHECK (status IN ('pending','in_production','ready','dispatched','delivered','cancelled'))
        ");

        // ── Step 2: Add new columns to delivery_schedules ────────────────────
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->foreignId('client_order_id')->nullable()->after('ds_reference')
                ->constrained('client_orders')->nullOnDelete();
            $table->date('actual_delivery_date')->nullable()->after('target_delivery_date');
            $table->text('delivery_address')->nullable()->after('notes');
            $table->text('delivery_instructions')->nullable()->after('delivery_address');
            $table->json('item_status_summary')->nullable()->after('delivery_instructions');
            $table->unsignedInteger('total_items')->default(0)->after('item_status_summary');
            $table->unsignedInteger('ready_items')->default(0)->after('total_items');
            $table->unsignedInteger('missing_items')->default(0)->after('ready_items');
            $table->boolean('has_dispute')->default(false)->after('missing_items');
            $table->json('dispute_summary')->nullable()->after('has_dispute');
            $table->timestamp('dispute_resolved_at')->nullable()->after('dispute_summary');
            $table->foreignId('dispatched_by_id')->nullable()->after('dispute_resolved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable()->after('dispatched_by_id');
            $table->foreignId('created_by_id')->nullable()->after('dispatched_at')
                ->constrained('users')->nullOnDelete();
        });

        // ── Step 3: Add delivery_schedule_item_id to production_orders ───────
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->foreignId('delivery_schedule_item_id')->nullable()->after('delivery_schedule_id')
                ->constrained('delivery_schedule_items')->nullOnDelete();
        });

        // ── Step 4: Update the DS status CHECK constraint to include new statuses
        DB::statement('ALTER TABLE delivery_schedules DROP CONSTRAINT IF EXISTS chk_ds_status');
        DB::statement("
            ALTER TABLE delivery_schedules ADD CONSTRAINT chk_ds_status
            CHECK (status IN ('open','planning','in_production','partially_ready','ready','dispatched','delivered','cancelled'))
        ");

        // ── Step 5: Data migration ───────────────────────────────────────────
        // For each combined_delivery_schedule, consolidate its child DS records:
        // - The combined record becomes the parent delivery_schedule
        // - Each old per-item DS becomes a delivery_schedule_item
        DB::statement("
            INSERT INTO delivery_schedule_items (ulid, delivery_schedule_id, product_item_id, qty_ordered, unit_price, status, notes, created_at, updated_at)
            SELECT
                ds.ulid,
                ds.id,
                ds.product_item_id,
                ds.qty_ordered,
                ds.unit_price,
                CASE ds.status
                    WHEN 'open' THEN 'pending'
                    WHEN 'in_production' THEN 'in_production'
                    WHEN 'ready' THEN 'ready'
                    WHEN 'dispatched' THEN 'dispatched'
                    WHEN 'delivered' THEN 'delivered'
                    WHEN 'cancelled' THEN 'cancelled'
                    ELSE 'pending'
                END,
                ds.notes,
                ds.created_at,
                ds.updated_at
            FROM delivery_schedules ds
            WHERE ds.product_item_id IS NOT NULL
        ");

        // Copy CDS fields into corresponding parent DS records
        DB::statement("
            UPDATE delivery_schedules ds
            SET
                client_order_id = cds.client_order_id,
                actual_delivery_date = cds.actual_delivery_date,
                delivery_address = cds.delivery_address,
                delivery_instructions = cds.delivery_instructions,
                item_status_summary = cds.item_status_summary,
                total_items = cds.total_items,
                ready_items = cds.ready_items,
                missing_items = cds.missing_items,
                has_dispute = COALESCE(cds.has_dispute, false),
                dispute_summary = cds.dispute_summary,
                dispatched_by_id = cds.dispatched_by_id,
                dispatched_at = cds.dispatched_at,
                created_by_id = cds.created_by_id,
                status = CASE cds.status
                    WHEN 'planning' THEN 'open'
                    ELSE cds.status
                END
            FROM combined_delivery_schedules cds
            WHERE ds.combined_delivery_schedule_id = cds.id
        ");

        // Link production_orders to their delivery_schedule_items
        DB::statement("
            UPDATE production_orders po
            SET delivery_schedule_item_id = dsi.id
            FROM delivery_schedule_items dsi
            JOIN delivery_schedules ds ON ds.id = dsi.delivery_schedule_id
            WHERE po.delivery_schedule_id = ds.id
            AND dsi.product_item_id = po.product_item_id
        ");

        // Update item counts for DS records that don't have a CDS
        DB::statement("
            UPDATE delivery_schedules ds
            SET total_items = sub.cnt
            FROM (
                SELECT delivery_schedule_id, COUNT(*) as cnt
                FROM delivery_schedule_items
                GROUP BY delivery_schedule_id
            ) sub
            WHERE ds.id = sub.delivery_schedule_id
            AND ds.total_items = 0
        ");

        // ── Step 6: Make product_item_id nullable (items are now in child table)
        // We keep the column for backward compatibility but it's no longer required
        DB::statement('ALTER TABLE delivery_schedules ALTER COLUMN product_item_id DROP NOT NULL');
        DB::statement('ALTER TABLE delivery_schedules ALTER COLUMN qty_ordered DROP NOT NULL');
    }

    public function down(): void
    {
        // Restore NOT NULL constraints
        DB::statement('ALTER TABLE delivery_schedules ALTER COLUMN product_item_id SET NOT NULL');
        DB::statement('ALTER TABLE delivery_schedules ALTER COLUMN qty_ordered SET NOT NULL');

        // Restore original status CHECK
        DB::statement('ALTER TABLE delivery_schedules DROP CONSTRAINT IF EXISTS chk_ds_status');
        DB::statement("
            ALTER TABLE delivery_schedules ADD CONSTRAINT chk_ds_status
            CHECK (status IN ('open','in_production','ready','dispatched','delivered','cancelled'))
        ");

        // Remove added columns from delivery_schedules
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->dropForeign(['client_order_id']);
            $table->dropForeign(['dispatched_by_id']);
            $table->dropForeign(['created_by_id']);
            $table->dropColumn([
                'client_order_id', 'actual_delivery_date', 'delivery_address',
                'delivery_instructions', 'item_status_summary', 'total_items',
                'ready_items', 'missing_items', 'has_dispute', 'dispute_summary',
                'dispute_resolved_at', 'dispatched_by_id', 'dispatched_at', 'created_by_id',
            ]);
        });

        // Remove delivery_schedule_item_id from production_orders
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropForeign(['delivery_schedule_item_id']);
            $table->dropColumn('delivery_schedule_item_id');
        });

        Schema::dropIfExists('delivery_schedule_items');
    }
};
