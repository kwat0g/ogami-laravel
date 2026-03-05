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
        // ── 1. Bill of Materials ──────────────────────────────────────────────
        Schema::create('bill_of_materials', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('product_item_id')->constrained('item_masters');
            $table->string('version', 20)->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_item_id', 'is_active']);
        });

        // ── 2. BOM Components ─────────────────────────────────────────────────
        Schema::create('bom_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bom_id')->constrained('bill_of_materials')->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('item_masters');
            $table->decimal('qty_per_unit', 15, 4);
            $table->string('unit_of_measure', 20);
            $table->decimal('scrap_factor_pct', 5, 2)->default(0);

            $table->index('bom_id');
        });

        // ── 3. Delivery Schedules ─────────────────────────────────────────────
        DB::statement("CREATE SEQUENCE IF NOT EXISTS ds_ref_seq START 1");

        Schema::create('delivery_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('ds_reference', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('product_item_id')->constrained('item_masters');
            $table->decimal('qty_ordered', 15, 4);
            $table->date('target_delivery_date');
            $table->string('type', 10)->default('local')->comment('local|export');
            $table->string('status', 20)->default('open')
                ->comment('open|in_production|ready|dispatched|delivered|cancelled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'target_delivery_date']);
        });

        DB::statement("
            ALTER TABLE delivery_schedules ADD CONSTRAINT chk_ds_type
            CHECK (type IN ('local','export'))
        ");
        DB::statement("
            ALTER TABLE delivery_schedules ADD CONSTRAINT chk_ds_status
            CHECK (status IN ('open','in_production','ready','dispatched','delivered','cancelled'))
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION trg_fn_ds_reference()
            RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.ds_reference IS NULL OR NEW.ds_reference = '' THEN
                    NEW.ds_reference := 'DS-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' ||
                                        LPAD(nextval('ds_ref_seq')::text, 5, '0');
                END IF;
                RETURN NEW;
            END;
            \$\$
        ");
        DB::statement("
            CREATE TRIGGER trg_ds_reference
            BEFORE INSERT ON delivery_schedules
            FOR EACH ROW EXECUTE FUNCTION trg_fn_ds_reference()
        ");

        // ── 4. Production Orders ──────────────────────────────────────────────
        DB::statement("CREATE SEQUENCE IF NOT EXISTS prod_order_seq START 1");

        Schema::create('production_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('po_reference', 30)->unique()->comment('Internal WO number, not confused with Purchase Order');
            $table->foreignId('delivery_schedule_id')->nullable()->constrained('delivery_schedules')->nullOnDelete();
            $table->foreignId('product_item_id')->constrained('item_masters');
            $table->foreignId('bom_id')->constrained('bill_of_materials');
            $table->decimal('qty_required', 15, 4);
            $table->decimal('qty_produced', 15, 4)->default(0);
            $table->date('target_start_date');
            $table->date('target_end_date');
            $table->string('status', 20)->default('draft')
                ->comment('draft|released|in_progress|completed|cancelled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'target_start_date']);
        });

        DB::statement("
            ALTER TABLE production_orders ADD CONSTRAINT chk_prod_order_status
            CHECK (status IN ('draft','released','in_progress','completed','cancelled'))
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION trg_fn_prod_order_reference()
            RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.po_reference IS NULL OR NEW.po_reference = '' THEN
                    NEW.po_reference := 'WO-' || TO_CHAR(NOW(), 'YYYY-MM') || '-' ||
                                        LPAD(nextval('prod_order_seq')::text, 5, '0');
                END IF;
                RETURN NEW;
            END;
            \$\$
        ");
        DB::statement("
            CREATE TRIGGER trg_prod_order_reference
            BEFORE INSERT ON production_orders
            FOR EACH ROW EXECUTE FUNCTION trg_fn_prod_order_reference()
        ");

        // ── 5. Production Output Logs ─────────────────────────────────────────
        Schema::create('production_output_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->string('shift', 2)->default('A')->comment('A|B|C');
            $table->date('log_date');
            $table->decimal('qty_produced', 15, 4);
            $table->decimal('qty_rejected', 15, 4)->default(0);
            $table->foreignId('operator_id')->constrained('employees');
            $table->foreignId('recorded_by_id')->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['production_order_id', 'log_date']);
        });

        DB::statement("
            ALTER TABLE production_output_logs ADD CONSTRAINT chk_pol_shift
            CHECK (shift IN ('A','B','C'))
        ");

        // Trigger: update qty_produced on production_orders when output log inserted
        DB::statement("
            CREATE OR REPLACE FUNCTION trg_fn_update_production_qty()
            RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
            BEGIN
                UPDATE production_orders
                SET qty_produced = qty_produced + NEW.qty_produced
                WHERE id = NEW.production_order_id;
                RETURN NEW;
            END;
            \$\$
        ");
        DB::statement("
            CREATE TRIGGER trg_update_production_qty
            AFTER INSERT ON production_output_logs
            FOR EACH ROW EXECUTE FUNCTION trg_fn_update_production_qty()
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS trg_update_production_qty ON production_output_logs");
        DB::statement("DROP FUNCTION IF EXISTS trg_fn_update_production_qty");
        DB::statement("DROP TRIGGER IF EXISTS trg_prod_order_reference ON production_orders");
        DB::statement("DROP FUNCTION IF EXISTS trg_fn_prod_order_reference");
        DB::statement("DROP TRIGGER IF EXISTS trg_ds_reference ON delivery_schedules");
        DB::statement("DROP FUNCTION IF EXISTS trg_fn_ds_reference");

        Schema::dropIfExists('production_output_logs');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('delivery_schedules');
        Schema::dropIfExists('bom_components');
        Schema::dropIfExists('bill_of_materials');

        DB::statement("DROP SEQUENCE IF EXISTS prod_order_seq");
        DB::statement("DROP SEQUENCE IF EXISTS ds_ref_seq");
    }
};
