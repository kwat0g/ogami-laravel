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
        Schema::create('vendor_fulfillment_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('vendor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note_type', 20);
            $table->text('notes')->nullable();
            $table->json('items')->nullable(); // [{po_item_id, qty_delivered}]
            $table->timestamps();

            $table->index(['purchase_order_id', 'created_at']);
        });

        DB::statement("
            ALTER TABLE vendor_fulfillment_notes
            ADD CONSTRAINT chk_vfn_note_type
                CHECK (note_type IN ('in_transit','delivered','partial'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_fulfillment_notes');
    }
};
