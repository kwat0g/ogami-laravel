<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.6 — New Sales module: Quotation, PriceList, SalesOrder (future migration from ClientOrder).
 *
 * For now, SalesOrder is created as a new table alongside ClientOrder to avoid
 * breaking existing functionality. A future migration will migrate data and deprecate ClientOrder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Price Lists ──────────────────────────────────────────────────────
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('item_masters');
            $table->unsignedBigInteger('unit_price_centavos');
            $table->decimal('min_qty', 15, 4)->default(1);
            $table->decimal('max_qty', 15, 4)->nullable();
            $table->timestamps();

            $table->unique(['price_list_id', 'item_id', 'min_qty']);
        });

        DB::statement('ALTER TABLE price_list_items ADD CONSTRAINT chk_price_list_items_price_nonneg CHECK (unit_price_centavos >= 0)');

        // ── Quotations ───────────────────────────────────────────────────────
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('quotation_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();
            $table->date('validity_date');
            $table->unsignedBigInteger('total_centavos')->default(0);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE quotations ADD CONSTRAINT chk_quotations_status CHECK (status IN ('draft','sent','accepted','converted_to_order','rejected','expired'))");

        Schema::create('quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('item_masters');
            $table->decimal('quantity', 15, 4);
            $table->unsignedBigInteger('unit_price_centavos');
            $table->unsignedBigInteger('line_total_centavos')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // ── Sales Orders ─────────────────────────────────────────────────────
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('order_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('requested_delivery_date')->nullable();
            $table->date('promised_delivery_date')->nullable();
            $table->unsignedBigInteger('total_centavos')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE sales_orders ADD CONSTRAINT chk_sales_orders_status CHECK (status IN ('draft','confirmed','in_production','partially_delivered','delivered','invoiced','cancelled'))");

        Schema::create('sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('item_masters');
            $table->decimal('quantity', 15, 4);
            $table->unsignedBigInteger('unit_price_centavos');
            $table->unsignedBigInteger('line_total_centavos')->default(0);
            $table->decimal('quantity_delivered', 15, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
