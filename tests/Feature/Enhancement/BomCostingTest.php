<?php

declare(strict_types=1);

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CostingService;

uses()->group('feature', 'enhancement', 'bom-costing');

it('computes material-only standard cost from BOM components', function () {
    $product = ItemMaster::factory()->create(['type' => 'finished_good', 'standard_price' => 0]);
    $rawA = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 5000]); // 50.00
    $rawB = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 3000]); // 30.00

    $bom = BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    BomComponent::create(['bom_id' => $bom->id, 'component_item_id' => $rawA->id, 'qty_per_unit' => 2, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0]);
    BomComponent::create(['bom_id' => $bom->id, 'component_item_id' => $rawB->id, 'qty_per_unit' => 3, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0]);

    $service = app(CostingService::class);
    $result = $service->standardCost($bom, 'material_only');

    // rawA: 2 * 5000 = 10000, rawB: 3 * 3000 = 9000, total = 19000
    expect($result['material_cost_centavos'])->toBe(19000);
    expect($result['labor_cost_centavos'])->toBe(0);
    expect($result['overhead_cost_centavos'])->toBe(0);
    expect($result['total_standard_cost_centavos'])->toBe(19000);
});

it('includes routing labor and overhead in standard cost', function () {
    $product = ItemMaster::factory()->create(['type' => 'finished_good']);
    $raw = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 1000]);

    $bom = BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    BomComponent::create(['bom_id' => $bom->id, 'component_item_id' => $raw->id, 'qty_per_unit' => 1, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0]);

    $wc = WorkCenter::create([
        'code' => 'WC-TEST',
        'name' => 'Test Work Center',
        'hourly_rate_centavos' => 20000, // 200/hr
        'overhead_rate_centavos' => 5000, // 50/hr
        'capacity_hours_per_day' => 8,
        'is_active' => true,
    ]);

    Routing::create([
        'bom_id' => $bom->id,
        'work_center_id' => $wc->id,
        'sequence' => 1,
        'operation_name' => 'Assembly',
        'setup_time_hours' => 0.5,
        'run_time_hours_per_unit' => 1.0,
    ]);

    $service = app(CostingService::class);
    $result = $service->standardCost($bom, 'material_labor_overhead');

    // Material: 1 * 1000 = 1000
    // Labor: (0.5 + 1.0) * 20000 = 30000
    // Overhead: (0.5 + 1.0) * 5000 = 7500
    expect($result['material_cost_centavos'])->toBe(1000);
    expect($result['labor_cost_centavos'])->toBe(30000);
    expect($result['overhead_cost_centavos'])->toBe(7500);
    expect($result['total_standard_cost_centavos'])->toBe(38500);
    expect($result['routings'])->toHaveCount(1);
});

it('computes scrap factor correctly', function () {
    $product = ItemMaster::factory()->create(['type' => 'finished_good']);
    $raw = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 10000]);

    $bom = BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    BomComponent::create(['bom_id' => $bom->id, 'component_item_id' => $raw->id, 'qty_per_unit' => 10, 'unit_of_measure' => 'kg', 'scrap_factor_pct' => 5]);

    $service = app(CostingService::class);
    $result = $service->standardCost($bom, 'material_only');

    // 10 * 1.05 (scrap) * 10000 = 105000
    expect($result['material_cost_centavos'])->toBe(105000);
});

it('returns where-used report for a component', function () {
    $product1 = ItemMaster::factory()->create(['type' => 'finished_good']);
    $product2 = ItemMaster::factory()->create(['type' => 'finished_good']);
    $raw = ItemMaster::factory()->create(['type' => 'raw_material']);

    $bom1 = BillOfMaterials::create(['product_item_id' => $product1->id, 'version' => '1.0', 'is_active' => true]);
    $bom2 = BillOfMaterials::create(['product_item_id' => $product2->id, 'version' => '1.0', 'is_active' => true]);

    BomComponent::create(['bom_id' => $bom1->id, 'component_item_id' => $raw->id, 'qty_per_unit' => 5, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0]);
    BomComponent::create(['bom_id' => $bom2->id, 'component_item_id' => $raw->id, 'qty_per_unit' => 3, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0]);

    $service = app(CostingService::class);
    $result = $service->whereUsed($raw->id);

    expect($result)->toHaveCount(2);
});
