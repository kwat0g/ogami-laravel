<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7: Data cleanup migration.
 *
 * 1. Fix DRs with invalid 'pending_delivery' status -> 'draft'
 * 2. Fix ClientOrders with 'completed' status -> 'fulfilled'
 * 3. Add 'require_pod_for_delivery' system setting
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Fix invalid DR statuses created by old CDS dispatch
        DB::table('delivery_receipts')
            ->where('status', 'pending_delivery')
            ->update(['status' => 'draft']);

        // 2. Rename completed -> fulfilled on client orders
        DB::table('client_orders')
            ->where('status', 'completed')
            ->update(['status' => 'fulfilled']);

        // 3. Add system setting for POD requirement
        $exists = DB::table('system_settings')
            ->where('key', 'require_pod_for_delivery')
            ->exists();

        if (! $exists) {
            DB::table('system_settings')->insert([
                'key' => 'require_pod_for_delivery',
                'label' => 'Require Proof of Delivery',
                'value' => json_encode(false),
                'data_type' => 'boolean',
                'is_sensitive' => false,
                'editable_by_role' => 'admin',
                'group' => 'delivery',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Reverse completed -> fulfilled rename
        DB::table('client_orders')
            ->where('status', 'fulfilled')
            ->update(['status' => 'completed']);

        DB::table('system_settings')
            ->where('key', 'require_pod_for_delivery')
            ->delete();
    }
};
