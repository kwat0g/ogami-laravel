<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor Item Catalog — each vendor can list the items they supply.
 *
 * Used in:
 *  - Phase 4: Auto-PO vendor assignment (Purchasing Officer picks items from catalog)
 *  - Phase 5: Vendor Portal (vendor manages their own catalog)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_items', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();

            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->string('item_code', 100);
            $table->string('item_name', 255);
            $table->text('description')->nullable();
            $table->string('unit_of_measure', 50)->default('pc');

            // Price in centavos (₱1.00 = 100)
            $table->unsignedBigInteger('unit_price')->default(0);

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // A vendor cannot have two items with the same item code
            $table->unique(['vendor_id', 'item_code']);
            $table->index(['vendor_id', 'is_active']);
        });

        DB::statement('ALTER TABLE vendor_items ADD CONSTRAINT chk_vendor_items_unit_price CHECK (unit_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_items');
    }
};
