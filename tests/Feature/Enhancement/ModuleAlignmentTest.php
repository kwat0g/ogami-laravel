<?php

declare(strict_types=1);

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Services\BomService;
use App\Domains\Production\Services\CostingService;
use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Models\QuotationItem;
use App\Domains\Sales\Services\ProfitMarginService;
use App\Models\User;

uses()->group('feature', 'enhancement', 'module-alignment');

// ── BOM Auto-Cost on Create ──────────────────────────────────────────────────

it('auto-calculates standard cost when BOM is created', function () {
    $product = ItemMaster::factory()->create(['type' => 'finished_good', 'standard_price' => 0]);
    $rawA = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 5000]);
    $rawB = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 3000]);

    $service = app(BomService::class);
    $bom = $service->store([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'components' => [
            ['component_item_id' => $rawA->id, 'qty_per_unit' => 2, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0],
            ['component_item_id' => $rawB->id, 'qty_per_unit' => 3, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0],
        ],
    ]);

    // Cost should be auto-calculated: rawA(2*5000=10000) + rawB(3*3000=9000) = 19000
    // (may include labor/overhead estimate if standard_production_days > 0)
    expect($bom->standard_cost_centavos)->toBeGreaterThanOrEqual(19000);
    expect($bom->last_cost_rollup_at)->not->toBeNull();
});

it('auto-recalculates cost when BOM components are updated', function () {
    $product = ItemMaster::factory()->create(['type' => 'finished_good']);
    $rawA = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 1000]);
    $rawB = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 5000]);

    $service = app(BomService::class);
    $bom = $service->store([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'components' => [
            ['component_item_id' => $rawA->id, 'qty_per_unit' => 1, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0],
        ],
    ]);

    $initialCost = $bom->standard_cost_centavos;

    // Update: replace component A with more expensive component B
    $bom = $service->update($bom, [
        'components' => [
            ['component_item_id' => $rawB->id, 'qty_per_unit' => 1, 'unit_of_measure' => 'pcs', 'scrap_factor_pct' => 0],
        ],
    ]);

    expect($bom->standard_cost_centavos)->toBeGreaterThan($initialCost);
});

// ── BOM Cost Breakdown ───────────────────────────────────────────────────────

it('returns cost breakdown for a BOM', function () {
    $product = ItemMaster::factory()->create(['type' => 'finished_good']);
    $raw = ItemMaster::factory()->create(['type' => 'raw_material', 'standard_price_centavos' => 2000]);

    $bom = BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    BomComponent::create([
        'bom_id' => $bom->id,
        'component_item_id' => $raw->id,
        'qty_per_unit' => 5,
        'unit_of_measure' => 'pcs',
        'scrap_factor_pct' => 0,
    ]);

    $service = app(BomService::class);
    $breakdown = $service->getCostBreakdown($bom);

    expect($breakdown)->toHaveKeys([
        'material_cost_centavos',
        'labor_cost_centavos',
        'overhead_cost_centavos',
        'total_standard_cost_centavos',
        'components',
        'routings',
    ]);
    expect($breakdown['material_cost_centavos'])->toBe(10000); // 5 * 2000
});

// ── Profit Margin Service ────────────────────────────────────────────────────

it('calculates quotation profit margin per line item', function () {
    $user = User::factory()->create();

    // Create a product with a known BOM cost
    $product = ItemMaster::factory()->create([
        'type' => 'finished_good',
        'standard_price_centavos' => 0,
    ]);
    $raw = ItemMaster::factory()->create([
        'type' => 'raw_material',
        'standard_price_centavos' => 10000, // P100.00
    ]);

    $bom = BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_cost_centavos' => 50000, // P500.00 (pre-computed)
    ]);
    BomComponent::create([
        'bom_id' => $bom->id,
        'component_item_id' => $raw->id,
        'qty_per_unit' => 5,
        'unit_of_measure' => 'pcs',
        'scrap_factor_pct' => 0,
    ]);

    // Create quotation with selling price above cost
    $quotation = Quotation::create([
        'quotation_number' => 'QT-TEST-001',
        'customer_id' => \App\Domains\AR\Models\Customer::factory()->create()->id,
        'validity_date' => now()->addDays(30),
        'total_centavos' => 100000,
        'status' => 'draft',
        'created_by_id' => $user->id,
    ]);
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'item_id' => $product->id,
        'quantity' => 1,
        'unit_price_centavos' => 100000, // P1,000.00
        'line_total_centavos' => 100000,
    ]);

    $service = app(ProfitMarginService::class);
    $result = $service->quotationMargin($quotation);

    expect($result)->toHaveKeys([
        'quotation_id',
        'total_revenue_centavos',
        'total_cost_centavos',
        'total_margin_centavos',
        'overall_margin_pct',
        'lines',
    ]);
    expect($result['lines'])->toHaveCount(1);
    expect($result['lines'][0]['unit_cost_centavos'])->toBe(50000);
    expect($result['lines'][0]['margin_per_unit_centavos'])->toBe(50000);
    expect($result['lines'][0]['margin_pct'])->toBe(50.0);
    expect($result['lines'][0]['below_cost'])->toBeFalse();
});

it('flags items priced below cost', function () {
    $user = User::factory()->create();

    $product = ItemMaster::factory()->create([
        'type' => 'finished_good',
        'standard_price_centavos' => 0,
    ]);

    BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_cost_centavos' => 100000, // P1,000.00 cost
    ]);

    $quotation = Quotation::create([
        'quotation_number' => 'QT-TEST-002',
        'customer_id' => \App\Domains\AR\Models\Customer::factory()->create()->id,
        'validity_date' => now()->addDays(30),
        'total_centavos' => 50000,
        'status' => 'draft',
        'created_by_id' => $user->id,
    ]);
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'item_id' => $product->id,
        'quantity' => 1,
        'unit_price_centavos' => 50000, // P500.00 — below cost!
        'line_total_centavos' => 50000,
    ]);

    $service = app(ProfitMarginService::class);
    $result = $service->quotationMargin($quotation);

    expect($result['lines'][0]['below_cost'])->toBeTrue();
    expect($result['lines'][0]['margin_per_unit_centavos'])->toBeLessThan(0);
    expect($result['overall_margin_pct'])->toBeLessThan(0);
});

it('suggests minimum price for target margin', function () {
    $product = ItemMaster::factory()->create([
        'type' => 'finished_good',
        'standard_price_centavos' => 0,
    ]);

    BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_cost_centavos' => 70000, // P700.00 cost
    ]);

    $service = app(ProfitMarginService::class);
    $result = $service->suggestPrice($product->id, 30.0);

    expect($result)->toHaveKeys([
        'item_id',
        'unit_cost_centavos',
        'target_margin_pct',
        'suggested_price_centavos',
    ]);
    // Cost=700, margin=30% => price = 700 / 0.70 = 1000
    expect($result['unit_cost_centavos'])->toBe(70000);
    expect($result['suggested_price_centavos'])->toBe(100000);
});
