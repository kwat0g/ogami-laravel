<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.5/3.6 — Delivery Route planning + Proof of Delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_routes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('route_number', 50)->unique();
            $table->date('planned_date');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 30)->default('planned');
            $table->unsignedSmallInteger('stop_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE delivery_routes ADD CONSTRAINT chk_delivery_routes_status CHECK (status IN ('planned','in_transit','completed','cancelled'))");

        // Proof of delivery on delivery receipts
        if (! Schema::hasColumn('delivery_receipts', 'proof_of_delivery')) {
            Schema::table('delivery_receipts', function (Blueprint $table): void {
                $table->json('proof_of_delivery')->nullable()->after('status');
                $table->timestamp('pod_received_at')->nullable()->after('proof_of_delivery');
                $table->foreignId('delivery_route_id')->nullable()->after('pod_received_at')
                    ->constrained('delivery_routes')->nullOnDelete();
                $table->unsignedBigInteger('freight_cost_centavos')->default(0)->after('delivery_route_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table): void {
            $table->dropForeign(['delivery_route_id']);
            $table->dropColumn(['proof_of_delivery', 'pod_received_at', 'delivery_route_id', 'freight_cost_centavos']);
        });

        Schema::dropIfExists('delivery_routes');
    }
};
