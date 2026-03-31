<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_disputes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('dispute_reference', 30)->unique();
            $table->foreignId('delivery_schedule_id')->nullable()->constrained('delivery_schedules')->nullOnDelete();
            $table->foreignId('client_order_id')->nullable()->constrained('client_orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('delivery_receipt_id')->nullable()->constrained('delivery_receipts')->nullOnDelete();
            $table->foreignId('reported_by_id')->constrained('users');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->string('resolution_type', 30)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('client_notes')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('replacement_schedule_id')->nullable()->constrained('delivery_schedules')->nullOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained('customer_credit_notes')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("
            ALTER TABLE delivery_disputes
            ADD CONSTRAINT chk_dispute_status
            CHECK (status IN ('open', 'investigating', 'pending_resolution', 'resolved', 'closed'))
        ");

        DB::statement("
            ALTER TABLE delivery_disputes
            ADD CONSTRAINT chk_dispute_resolution_type
            CHECK (resolution_type IS NULL OR resolution_type IN ('replace_items', 'credit_note', 'partial_accept', 'full_replacement'))
        ");

        Schema::create('delivery_dispute_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_dispute_id')->constrained('delivery_disputes')->cascadeOnDelete();
            $table->foreignId('item_master_id')->constrained('item_masters');
            $table->decimal('expected_qty', 12, 4);
            $table->decimal('received_qty', 12, 4);
            $table->string('condition', 20)->default('good');
            $table->text('notes')->nullable();
            $table->string('resolution_action', 30)->nullable();
            $table->decimal('resolution_qty', 12, 4)->nullable();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE delivery_dispute_items
            ADD CONSTRAINT chk_dispute_item_condition
            CHECK (condition IN ('good', 'damaged', 'missing', 'wrong_item'))
        ");

        DB::statement("
            ALTER TABLE delivery_dispute_items
            ADD CONSTRAINT chk_dispute_item_resolution
            CHECK (resolution_action IS NULL OR resolution_action IN ('replace', 'credit', 'accept'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_dispute_items');
        Schema::dropIfExists('delivery_disputes');
    }
};
