<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.2 — Inventory enhancements.
 *
 * 1. Add costing_method to item_masters
 * 2. Create physical_counts and physical_count_items tables
 * 3. Add expiry_date to lot_batches
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Costing method on item masters
        Schema::table('item_masters', function (Blueprint $table): void {
            $table->string('costing_method', 30)->default('standard')->after('type');
        });

        DB::statement("ALTER TABLE item_masters ADD CONSTRAINT chk_item_masters_costing_method CHECK (costing_method IN ('standard','fifo','weighted_average'))");

        // 2. Physical count tables
        Schema::create('physical_counts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('reference_number', 50)->unique();
            $table->foreignId('location_id')->constrained('warehouse_locations');
            $table->string('status', 30)->default('draft');
            $table->date('count_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE physical_counts ADD CONSTRAINT chk_physical_counts_status CHECK (status IN ('draft','in_progress','pending_approval','approved','cancelled'))");

        Schema::create('physical_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('physical_count_id')->constrained('physical_counts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('item_masters');
            $table->decimal('system_qty', 15, 4)->default(0);
            $table->decimal('counted_qty', 15, 4)->nullable();
            $table->decimal('variance_qty', 15, 4)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 3. Expiry date on lot batches
        Schema::table('lot_batches', function (Blueprint $table): void {
            $table->date('expiry_date')->nullable()->after('received_date');
        });
    }

    public function down(): void
    {
        Schema::table('lot_batches', function (Blueprint $table): void {
            $table->dropColumn('expiry_date');
        });

        Schema::dropIfExists('physical_count_items');
        Schema::dropIfExists('physical_counts');

        DB::statement('ALTER TABLE item_masters DROP CONSTRAINT IF EXISTS chk_item_masters_costing_method');

        Schema::table('item_masters', function (Blueprint $table): void {
            $table->dropColumn('costing_method');
        });
    }
};
