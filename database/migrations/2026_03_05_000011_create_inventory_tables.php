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
        // ── 1. Item Categories ────────────────────────────────────────────────
        Schema::create('item_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 2. Item Master ────────────────────────────────────────────────────
        DB::statement('CREATE SEQUENCE IF NOT EXISTS item_code_seq START 1');

        Schema::create('item_masters', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('item_code', 30)->unique();
            $table->foreignId('category_id')->constrained('item_categories');
            $table->string('name', 200);
            $table->string('unit_of_measure', 20);
            $table->text('description')->nullable();
            $table->decimal('reorder_point', 15, 4)->default(0);
            $table->decimal('reorder_qty', 15, 4)->default(0);
            $table->string('type', 30)->default('raw_material')
                ->comment('raw_material|semi_finished|finished_good|consumable|spare_part');
            $table->boolean('requires_iqc')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category_id']);
            $table->index('item_code');
        });

        DB::statement("
            CREATE OR REPLACE FUNCTION trg_fn_item_code()
            RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.item_code IS NULL OR NEW.item_code = '' THEN
                    NEW.item_code := 'ITEM-' || LPAD(nextval('item_code_seq')::text, 6, '0');
                END IF;
                RETURN NEW;
            END;
            \$\$
        ");
        DB::statement('
            CREATE TRIGGER trg_item_code
            BEFORE INSERT ON item_masters
            FOR EACH ROW EXECUTE FUNCTION trg_fn_item_code()
        ');

        // ── 3. Warehouse Locations ────────────────────────────────────────────
        Schema::create('warehouse_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('zone', 50)->nullable();
            $table->string('bin', 50)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 4. Lot / Batch Tracking ───────────────────────────────────────────
        Schema::create('lot_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('lot_number', 50);
            $table->foreignId('item_id')->constrained('item_masters');
            $table->string('received_from', 30)->default('vendor')
                ->comment('vendor|production');
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('quantity_remaining', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['item_id', 'received_date']);
            $table->unique(['lot_number', 'item_id']);
        });

        // ── 5. Stock Ledger (append-only movement log) ────────────────────────
        Schema::create('stock_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('item_masters');
            $table->foreignId('location_id')->constrained('warehouse_locations');
            $table->foreignId('lot_batch_id')->nullable()->constrained('lot_batches');
            $table->string('transaction_type', 30)
                ->comment('goods_receipt|issue|transfer|adjustment|return|production_output');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 15, 4)->comment('positive = in, negative = out');
            $table->decimal('balance_after', 15, 4);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'location_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement("
            ALTER TABLE stock_ledger ADD CONSTRAINT chk_sl_txn_type
            CHECK (transaction_type IN ('goods_receipt','issue','transfer','adjustment','return','production_output'))
        ");

        // ── 6. Stock Balances (materialized per item × location) ──────────────
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->foreignId('item_id')->constrained('item_masters');
            $table->foreignId('location_id')->constrained('warehouse_locations');
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['item_id', 'location_id']);
        });

        DB::statement('
            CREATE OR REPLACE FUNCTION trg_fn_update_stock_balance()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                INSERT INTO stock_balances (item_id, location_id, quantity_on_hand)
                VALUES (NEW.item_id, NEW.location_id, NEW.quantity)
                ON CONFLICT (item_id, location_id)
                DO UPDATE SET
                    quantity_on_hand = stock_balances.quantity_on_hand + NEW.quantity,
                    updated_at = NOW();
                RETURN NEW;
            END;
            $$
        ');
        DB::statement('
            CREATE TRIGGER trg_update_stock_balance
            AFTER INSERT ON stock_ledger
            FOR EACH ROW EXECUTE FUNCTION trg_fn_update_stock_balance()
        ');

        // ── 7. Material Requisitions ──────────────────────────────────────────
        DB::statement('CREATE SEQUENCE IF NOT EXISTS mrq_ref_seq START 1');

        Schema::create('material_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('mr_reference', 30)->unique();
            $table->foreignId('requested_by_id')->constrained('users');
            $table->foreignId('department_id')->constrained('departments');
            $table->text('purpose');
            $table->string('status', 30)->default('draft');
            // SoD columns (Staff → Head → Manager → Officer → VP)
            $table->foreignId('submitted_by_id')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('noted_by_id')->nullable()->constrained('users');
            $table->timestamp('noted_at')->nullable();
            $table->text('noted_comments')->nullable();
            $table->foreignId('checked_by_id')->nullable()->constrained('users');
            $table->timestamp('checked_at')->nullable();
            $table->text('checked_comments')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewed_comments')->nullable();
            $table->foreignId('vp_approved_by_id')->nullable()->constrained('users');
            $table->timestamp('vp_approved_at')->nullable();
            $table->text('vp_comments')->nullable();
            $table->foreignId('rejected_by_id')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('fulfilled_by_id')->nullable()->constrained('users');
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'department_id']);
        });

        DB::statement("
            ALTER TABLE material_requisitions ADD CONSTRAINT mrq_status_check
            CHECK (status IN ('draft','submitted','noted','checked','reviewed','approved','rejected','cancelled','fulfilled'))
        ");

        // SoD constraints
        DB::statement('
            ALTER TABLE material_requisitions ADD CONSTRAINT chk_sod_mrq_head
            CHECK (noted_by_id IS NULL OR noted_by_id <> requested_by_id)
        ');
        DB::statement('
            ALTER TABLE material_requisitions ADD CONSTRAINT chk_sod_mrq_manager
            CHECK (checked_by_id IS NULL OR checked_by_id <> noted_by_id)
        ');
        DB::statement('
            ALTER TABLE material_requisitions ADD CONSTRAINT chk_sod_mrq_officer
            CHECK (reviewed_by_id IS NULL OR reviewed_by_id <> checked_by_id)
        ');
        DB::statement('
            ALTER TABLE material_requisitions ADD CONSTRAINT chk_sod_mrq_vp
            CHECK (vp_approved_by_id IS NULL OR vp_approved_by_id <> reviewed_by_id)
        ');

        // mr_reference trigger
        DB::statement("
            CREATE OR REPLACE FUNCTION trg_fn_mrq_reference()
            RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.mr_reference IS NULL OR NEW.mr_reference = '' THEN
                    NEW.mr_reference := 'MRQ-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' ||
                                        LPAD(nextval('mrq_ref_seq')::text, 5, '0');
                END IF;
                RETURN NEW;
            END;
            \$\$
        ");
        DB::statement('
            CREATE TRIGGER trg_mrq_reference
            BEFORE INSERT ON material_requisitions
            FOR EACH ROW EXECUTE FUNCTION trg_fn_mrq_reference()
        ');

        // ── Link: add item_master_id to goods_receipt_items ─────────────────
        // Enables automatic stock receive when GR passes three-way match.
        // Nullable so existing GRs are unaffected; Warehouse Head sets this per line.
        if (Schema::hasTable('goods_receipt_items') && ! Schema::hasColumn('goods_receipt_items', 'item_master_id')) {
            Schema::table('goods_receipt_items', function (Blueprint $table): void {
                $table->foreignId('item_master_id')->nullable()->after('po_item_id')->constrained('item_masters')->nullOnDelete();
            });
        }

        // ── 8. Material Requisition Items ─────────────────────────────────────
        Schema::create('material_requisition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_requisition_id')->constrained('material_requisitions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('item_masters');
            $table->decimal('qty_requested', 15, 4);
            $table->decimal('qty_issued', 15, 4)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedTinyInteger('line_order')->default(0);

            $table->index('material_requisition_id');
        });
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_update_stock_balance ON stock_ledger');
        DB::statement('DROP FUNCTION IF EXISTS trg_fn_update_stock_balance');
        DB::statement('DROP TRIGGER IF EXISTS trg_item_code ON item_masters');
        DB::statement('DROP FUNCTION IF EXISTS trg_fn_item_code');
        DB::statement('DROP TRIGGER IF EXISTS trg_mrq_reference ON material_requisitions');
        DB::statement('DROP FUNCTION IF EXISTS trg_fn_mrq_reference');

        Schema::dropIfExists('material_requisition_items');
        Schema::dropIfExists('material_requisitions');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_ledger');
        Schema::dropIfExists('lot_batches');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('item_masters');
        Schema::dropIfExists('item_categories');

        if (Schema::hasTable('goods_receipt_items') && Schema::hasColumn('goods_receipt_items', 'item_master_id')) {
            Schema::table('goods_receipt_items', function (Blueprint $table): void {
                $table->dropForeign(['item_master_id']);
                $table->dropColumn('item_master_id');
            });
        }

        DB::statement('DROP SEQUENCE IF EXISTS item_code_seq');
        DB::statement('DROP SEQUENCE IF EXISTS mrq_ref_seq');
    }
};
