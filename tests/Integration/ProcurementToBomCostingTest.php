<?php

declare(strict_types=1);

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorItem;
use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Models\PurchaseRequestItem;
use App\Domains\Procurement\Services\GoodsReceiptItemCostSyncService;
use App\Domains\Procurement\Services\PurchaseOrderService;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Services\CostingService;
use App\Events\Inventory\ItemPriceChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('integration', 'procurement', 'production', 'costing');

it('uses procurement vendor price as stored material cost for BOM rollup', function (): void {
    Event::fake([ItemPriceChanged::class]);

    $user = \App\Models\User::factory()->create();

    $department = Department::firstOrCreate(
        ['code' => 'PUR'],
        ['name' => 'Purchasing', 'is_active' => true]
    );

    $vendor = Vendor::factory()->create(['created_by' => $user->id]);

    $category = ItemCategory::firstOrCreate(
        ['code' => 'RAW-COST'],
        ['name' => 'Raw Materials Cost Test', 'is_active' => true]
    );

    $rawMaterial = ItemMaster::factory()->create([
        'ulid' => (string) Str::ulid(),
        'category_id' => $category->id,
        'item_code' => 'RM-COST-'.uniqid(),
        'name' => 'Resin Grade A',
        'type' => 'raw_material',
        'unit_of_measure' => 'kg',
        'is_active' => true,
        'standard_price_centavos' => 1_000, // seed a different baseline (P10.00)
    ]);

    // Procurement source-of-truth price from vendor catalog (P123.45)
    VendorItem::query()->forceCreate([
        'ulid' => (string) Str::ulid(),
        'vendor_id' => $vendor->id,
        'item_code' => 'VEN-RESIN-A',
        'item_name' => 'Resin Grade A',
        'unit_of_measure' => 'kg',
        'unit_price' => 12_345,
        'is_active' => true,
        'created_by_id' => $user->id,
    ]);

    $pr = PurchaseRequest::query()->forceCreate([
        'ulid' => (string) Str::ulid(),
        'pr_reference' => 'PR-COST-'.now()->format('YmdHis'),
        'department_id' => $department->id,
        'requested_by_id' => $user->id,
        'vendor_id' => $vendor->id,
        'urgency' => 'normal',
        'justification' => 'Cost traceability test',
        'status' => 'approved',
    ]);

    PurchaseRequestItem::create([
        'purchase_request_id' => $pr->id,
        'item_master_id' => $rawMaterial->id,
        'item_description' => 'Resin Grade A',
        'unit_of_measure' => 'kg',
        'quantity' => 100,
        'estimated_unit_cost' => 77.77, // intentionally different from vendor catalog
        'line_order' => 1,
    ]);

    $po = app(PurchaseOrderService::class)->createFromApprovedPr($pr->fresh('items'));
    $poItem = $po->items()->firstOrFail();

    // Fetch check: PO should use vendor catalog unit_price (centavos -> pesos)
    expect((float) $poItem->agreed_unit_cost)->toBe(123.45);

    $gr = GoodsReceipt::create([
        'ulid' => (string) Str::ulid(),
        'gr_reference' => 'GR-COST-'.now()->format('His'),
        'purchase_order_id' => $po->id,
        'received_by_id' => $user->id,
        'received_date' => now()->toDateString(),
        'status' => 'confirmed',
        'three_way_match_passed' => true,
    ]);

    GoodsReceiptItem::create([
        'goods_receipt_id' => $gr->id,
        'po_item_id' => $poItem->id,
        'item_master_id' => $rawMaterial->id,
        'quantity_received' => 10,
        'quantity_accepted' => 10,
        'unit_of_measure' => 'kg',
        'condition' => 'good',
        'qc_status' => 'passed',
    ]);

    // Store check: sync item cost via dedicated GR cost sync service.
    app(GoodsReceiptItemCostSyncService::class)->syncFromGoodsReceipt($gr->fresh('items.poItem'));

    expect($rawMaterial->fresh()->standard_price_centavos)->toBe(12_345);

    $finishedGood = ItemMaster::factory()->create([
        'ulid' => (string) Str::ulid(),
        'category_id' => $category->id,
        'item_code' => 'FG-COST-'.uniqid(),
        'name' => 'Finished Product Cost Test',
        'type' => 'finished_good',
        'unit_of_measure' => 'pcs',
        'is_active' => true,
        'standard_price_centavos' => 0,
    ]);

    $bom = BillOfMaterials::create([
        'product_item_id' => $finishedGood->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    BomComponent::create([
        'bom_id' => $bom->id,
        'component_item_id' => $rawMaterial->id,
        'qty_per_unit' => 2,
        'unit_of_measure' => 'kg',
        'scrap_factor_pct' => 0,
    ]);

    $cost = app(CostingService::class)->standardCost($bom, 'material_only');

    // Read check: BOM material rollup must use stored procurement-updated price.
    expect($cost['material_cost_centavos'])->toBe(24_690); // 2 * 12,345
    expect($cost['total_standard_cost_centavos'])->toBe(24_690);
});

it('recalculates weighted-average standard price from confirmed goods receipt costs', function (): void {
    $user = \App\Models\User::factory()->create();

    $department = Department::firstOrCreate(
        ['code' => 'PUR'],
        ['name' => 'Purchasing', 'is_active' => true]
    );

    $vendor = Vendor::factory()->create(['created_by' => $user->id]);

    $category = ItemCategory::firstOrCreate(
        ['code' => 'RAW-COST-WA'],
        ['name' => 'Raw Materials Cost Test WA', 'is_active' => true]
    );

    $rawMaterial = ItemMaster::factory()->create([
        'ulid' => (string) Str::ulid(),
        'category_id' => $category->id,
        'item_code' => 'RM-WA-'.uniqid(),
        'name' => 'Resin Grade WA',
        'type' => 'raw_material',
        'unit_of_measure' => 'kg',
        'is_active' => true,
        'costing_method' => 'weighted_average',
        'standard_price_centavos' => 10_000,
    ]);

    VendorItem::query()->forceCreate([
        'ulid' => (string) Str::ulid(),
        'vendor_id' => $vendor->id,
        'item_code' => 'VEN-RESIN-WA',
        'item_name' => 'Resin Grade WA',
        'unit_of_measure' => 'kg',
        'unit_price' => 30_000,
        'is_active' => true,
        'created_by_id' => $user->id,
    ]);

    $pr = PurchaseRequest::query()->forceCreate([
        'ulid' => (string) Str::ulid(),
        'pr_reference' => 'PR-WA-'.now()->format('YmdHis'),
        'department_id' => $department->id,
        'requested_by_id' => $user->id,
        'vendor_id' => $vendor->id,
        'urgency' => 'normal',
        'justification' => 'Weighted average price sync test',
        'status' => 'approved',
    ]);

    PurchaseRequestItem::create([
        'purchase_request_id' => $pr->id,
        'item_master_id' => $rawMaterial->id,
        'item_description' => 'Resin Grade WA',
        'unit_of_measure' => 'kg',
        'quantity' => 100,
        'estimated_unit_cost' => 20.00,
        'line_order' => 1,
    ]);

    $po = app(PurchaseOrderService::class)->createFromApprovedPr($pr->fresh('items'));
    $poItem = $po->items()->firstOrFail();

    $location = WarehouseLocation::query()->create([
        'name' => 'WH-WA',
        'code' => 'WH-WA-01',
        'is_active' => true,
    ]);

    // Simulate current stock already updated by inventory receipt flow before sync runs.
    StockBalance::query()->create([
        'item_id' => $rawMaterial->id,
        'location_id' => $location->id,
        'quantity_on_hand' => 200,
    ]);

    $gr = GoodsReceipt::create([
        'ulid' => (string) Str::ulid(),
        'gr_reference' => 'GR-WA-'.now()->format('His'),
        'purchase_order_id' => $po->id,
        'received_by_id' => $user->id,
        'received_date' => now()->toDateString(),
        'status' => 'confirmed',
        'three_way_match_passed' => true,
    ]);

    GoodsReceiptItem::create([
        'goods_receipt_id' => $gr->id,
        'po_item_id' => $poItem->id,
        'item_master_id' => $rawMaterial->id,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_of_measure' => 'kg',
        'condition' => 'good',
        'qc_status' => 'passed',
    ]);

    app(GoodsReceiptItemCostSyncService::class)->syncFromGoodsReceipt($gr->fresh('items.poItem'));

    // Current WA formula: (current_qty*old_avg + receipt_qty*receipt_cost)/(current_qty+receipt_qty)
    // => (200*10000 + 100*30000) / 300 = 16667
    expect($rawMaterial->fresh()->standard_price_centavos)->toBe(16_667);
});

it('does not emit price change when agreed cost matches current standard cost', function (): void {
    Event::fake([ItemPriceChanged::class]);

    $user = \App\Models\User::factory()->create();

    $department = Department::firstOrCreate(
        ['code' => 'PUR'],
        ['name' => 'Purchasing', 'is_active' => true]
    );

    $vendor = Vendor::factory()->create(['created_by' => $user->id]);

    $category = ItemCategory::firstOrCreate(
        ['code' => 'RAW-COST-ZERO'],
        ['name' => 'Raw Materials Cost Zero Test', 'is_active' => true]
    );

    $rawMaterial = ItemMaster::factory()->create([
        'ulid' => (string) Str::ulid(),
        'category_id' => $category->id,
        'item_code' => 'RM-ZERO-'.uniqid(),
        'name' => 'Resin Grade Zero',
        'type' => 'raw_material',
        'unit_of_measure' => 'kg',
        'is_active' => true,
        'standard_price_centavos' => 10_000,
    ]);

    VendorItem::query()->forceCreate([
        'ulid' => (string) Str::ulid(),
        'vendor_id' => $vendor->id,
        'item_code' => 'VEN-RESIN-ZERO',
        'item_name' => 'Resin Grade Zero',
        'unit_of_measure' => 'kg',
        'unit_price' => 10_000,
        'is_active' => true,
        'created_by_id' => $user->id,
    ]);

    $pr = PurchaseRequest::query()->forceCreate([
        'ulid' => (string) Str::ulid(),
        'pr_reference' => 'PR-ZERO-'.now()->format('YmdHis'),
        'department_id' => $department->id,
        'requested_by_id' => $user->id,
        'vendor_id' => $vendor->id,
        'urgency' => 'normal',
        'justification' => 'Ignore zero-cost sync',
        'status' => 'approved',
    ]);

    PurchaseRequestItem::create([
        'purchase_request_id' => $pr->id,
        'item_master_id' => $rawMaterial->id,
        'item_description' => 'Resin Grade Zero',
        'unit_of_measure' => 'kg',
        'quantity' => 10,
        'estimated_unit_cost' => 1.00,
        'line_order' => 1,
    ]);

    $po = app(PurchaseOrderService::class)->createFromApprovedPr($pr->fresh('items'));
    $poItem = $po->items()->firstOrFail();

    $gr = GoodsReceipt::create([
        'ulid' => (string) Str::ulid(),
        'gr_reference' => 'GR-ZERO-'.now()->format('His'),
        'purchase_order_id' => $po->id,
        'received_by_id' => $user->id,
        'received_date' => now()->toDateString(),
        'status' => 'confirmed',
        'three_way_match_passed' => true,
    ]);

    GoodsReceiptItem::create([
        'goods_receipt_id' => $gr->id,
        'po_item_id' => $poItem->id,
        'item_master_id' => $rawMaterial->id,
        'quantity_received' => 10,
        'quantity_accepted' => 10,
        'unit_of_measure' => 'kg',
        'condition' => 'good',
        'qc_status' => 'passed',
    ]);

    app(GoodsReceiptItemCostSyncService::class)->syncFromGoodsReceipt($gr->fresh('items.poItem'));

    expect($rawMaterial->fresh()->standard_price_centavos)->toBe(10_000);
    Event::assertNothingDispatched();
});
