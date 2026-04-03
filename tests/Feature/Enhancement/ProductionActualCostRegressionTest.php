<?php

declare(strict_types=1);

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\CostingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class)->group('feature', 'enhancement', 'production-costing');

it('computes actual cost without maintenance reference columns and uses qty_produced fallback', function () {
    expect(Schema::hasColumns('maintenance_work_orders', ['reference_type', 'reference_id']))->toBeFalse();

    $user = User::factory()->create();

    $product = ItemMaster::factory()->create([
        'type' => 'finished_good',
        'standard_price_centavos' => 0,
    ]);

    $bom = BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 2,
    ]);

    $order = ProductionOrder::create([
        'product_item_id' => $product->id,
        'bom_id' => $bom->id,
        'qty_required' => 10,
        'target_start_date' => now()->toDateString(),
        'target_end_date' => now()->addDays(2)->toDateString(),
        'status' => 'completed',
        'created_by_id' => $user->id,
    ]);

    DB::table('production_orders')->where('id', $order->id)->update([
        'qty_produced' => 5,
        'qty_rejected' => 0,
    ]);

    $result = app(CostingService::class)->actualCost($order->fresh());

    // Falls back to estimated labor when no explicit work-order labor hours exist:
    // standard_production_days (2) * 8h/day * 15_000 centavos/h = 240_000 centavos.
    expect($result['quantity_produced'])->toBe(5.0);
    expect($result['labor_cost_centavos'])->toBe(240000);
    expect($result['total_cost_centavos'])->toBe(240000);
});

it('ignores unrelated maintenance work orders when deriving production labor fallback', function () {
    expect(Schema::hasColumns('maintenance_work_orders', ['reference_type', 'reference_id']))->toBeFalse();

    $user = User::factory()->create();

    $product = ItemMaster::factory()->create([
        'type' => 'finished_good',
        'standard_price_centavos' => 0,
    ]);

    $bom = BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 2,
    ]);

    $order = ProductionOrder::create([
        'product_item_id' => $product->id,
        'bom_id' => $bom->id,
        'qty_required' => 10,
        'target_start_date' => now()->toDateString(),
        'target_end_date' => now()->addDays(2)->toDateString(),
        'status' => 'completed',
        'created_by_id' => $user->id,
    ]);

    DB::table('production_orders')->where('id', $order->id)->update([
        'qty_produced' => 5,
        'qty_rejected' => 0,
    ]);

    MaintenanceWorkOrder::factory()->create([
        'status' => 'completed',
        'labor_hours' => 10.0,
        'created_by_id' => $user->id,
    ]);

    $result = app(CostingService::class)->actualCost($order->fresh());

    // Labor still uses BOM-based fallback because maintenance rows are un-linkable
    // without production reference columns.
    expect($result['quantity_produced'])->toBe(5.0);
    expect($result['labor_cost_centavos'])->toBe(240000);
    expect($result['total_cost_centavos'])->toBe(240000);
});
