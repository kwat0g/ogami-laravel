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
        Schema::create('combined_delivery_schedules', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('client_order_id')->constrained('client_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('cds_reference')->unique()->comment('CDS-YYYY-NNNNNN format');
            $table->string('status', 30)->default('planning'); // planning, ready, partially_ready, dispatched, delivered, cancelled
            $table->date('target_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->json('item_status_summary')->nullable()->comment('JSON summary of each item status');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('ready_items')->default(0);
            $table->unsignedInteger('missing_items')->default(0);
            $table->foreignId('created_by_id')->constrained('users');
            $table->foreignId('dispatched_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'target_delivery_date']);
            $table->index('cds_reference');
        });

        // CHECK constraint for status
        DB::statement("ALTER TABLE combined_delivery_schedules ADD CONSTRAINT chk_cds_status CHECK (status IN ('planning', 'ready', 'partially_ready', 'dispatched', 'delivered', 'cancelled'))");

        // Link delivery schedules to combined schedule
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->foreignId('combined_delivery_schedule_id')->nullable()->constrained('combined_delivery_schedules')->nullOnDelete();
            $table->index('combined_delivery_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table): void {
            $table->dropForeign(['combined_delivery_schedule_id']);
            $table->dropColumn('combined_delivery_schedule_id');
        });

        Schema::dropIfExists('combined_delivery_schedules');
    }
};
