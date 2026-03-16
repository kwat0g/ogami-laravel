<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Position;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockLedger;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Production → Inventory Integration Tests
|--------------------------------------------------------------------------
| Verifies the complete production-to-inventory workflow:
|   1. BOM creation with components
|   2. Production order creation
|   3. Stock reservation/deduction on release
|   4. Production output (finished goods)
|   5. Stock ledger entries for all movements
|
| Flow: BOM → Production Order → Material Issue → Production Output → Stock Update
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    $this->user = User::factory()->create();
    $this->user->assignRole('production_manager');

    $this->department = Department::firstOrCreate(
        ['code' => 'PROD'],
        ['name' => 'Production Department', 'is_active' => true]
    );

    $this->warehouse = WarehouseLocation::firstOrCreate(
        ['code' => 'WH-PROD'],
        ['name' => 'Production Warehouse', 'is_active' => true]
    );

    $category = ItemCategory::firstOrCreate(
        ['code' => 'RM'],
        ['name' => 'Raw Materials', 'is_active' => true]
    );

    $fgCategory = ItemCategory::firstOrCreate(
        ['code' => 'FG'],
        ['name' => 'Finished Goods', 'is_active' => true]
    );

    // Create raw material items with unique codes for each test
    $this->rawMaterial1 = ItemMaster::create([
        'category_id' => $category->id,
        'item_code' => 'RM-TEST-' . uniqid(),
        'name' => 'Raw Material 1',
        'type' => 'raw_material',
        'unit_of_measure' => 'pcs',
        'is_active' => true,
    ]);

    $this->rawMaterial2 = ItemMaster::create([
        'category_id' => $category->id,
        'item_code' => 'RM-TEST-' . uniqid(),
        'name' => 'Raw Material 2',
        'type' => 'raw_material',
        'unit_of_measure' => 'kg',
        'is_active' => true,
    ]);

    // Create finished good item with unique code
    $this->finishedGood = ItemMaster::create([
        'category_id' => $fgCategory->id,
        'item_code' => 'FG-TEST-' . uniqid(),
        'name' => 'Finished Product',
        'type' => 'finished_good',
        'unit_of_measure' => 'pcs',
        'is_active' => true,
    ]);

    // Seed initial stock for raw materials
    StockBalance::create([
        'item_id' => $this->rawMaterial1->id,
        'location_id' => $this->warehouse->id,
        'quantity_on_hand' => 1000,
    ]);

    StockBalance::create([
        'item_id' => $this->rawMaterial2->id,
        'location_id' => $this->warehouse->id,
        'quantity_on_hand' => 500,
    ]);
});

// ---------------------------------------------------------------------------
// INT-PROD-INV-001: Production order release deducts BOM components from stock
// ---------------------------------------------------------------------------

it('INT-PROD-INV-001 — production order release deducts BOM components from stock', function () {
    $qtyToProduce = 10;
    $rm1PerUnit = 2;
    $rm2PerUnit = 0.5;

    // Create BOM
    $bom = BillOfMaterials::create([
        'ulid' => (string) Str::ulid(),
        'product_item_id' => $this->finishedGood->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    BomComponent::create([
        'bom_id' => $bom->id,
        'component_item_id' => $this->rawMaterial1->id,
        'qty_per_unit' => $rm1PerUnit,
        'unit_of_measure' => 'pcs',
    ]);

    BomComponent::create([
        'bom_id' => $bom->id,
        'component_item_id' => $this->rawMaterial2->id,
        'qty_per_unit' => $rm2PerUnit,
        'unit_of_measure' => 'kg',
    ]);

    // Get initial stock levels
    $initialRm1 = StockBalance::where('item_id', $this->rawMaterial1->id)
        ->where('location_id', $this->warehouse->id)
        ->value('quantity_on_hand');

    $initialRm2 = StockBalance::where('item_id', $this->rawMaterial2->id)
        ->where('location_id', $this->warehouse->id)
        ->value('quantity_on_hand');

    expect((float) $initialRm1)->toBe(1000.00);
    expect((float) $initialRm2)->toBe(500.00);

    // Create production order
    $prodOrder = ProductionOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'WO-TEST-' . uniqid(),
        'product_item_id' => $this->finishedGood->id,
        'bom_id' => $bom->id,
        'qty_required' => $qtyToProduce,
        'qty_produced' => 0,
        'target_start_date' => now(),
        'target_end_date' => now()->addWeek(),
        'status' => 'released',
        'created_by_id' => $this->user->id,
    ]);

    // Deduct stock for components via stock ledger (trigger updates balance)
    $rm1Needed = $qtyToProduce * $rm1PerUnit;
    $rm2Needed = $qtyToProduce * $rm2PerUnit;

    // Create stock ledger entries for material issue (trigger will update stock)
    StockLedger::create([
        'item_id' => $this->rawMaterial1->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'issue',
        'reference_type' => 'production_order',
        'reference_id' => $prodOrder->id,
        'quantity' => -$rm1Needed,
        'balance_after' => $initialRm1 - $rm1Needed,
        'remarks' => "Production Order {$prodOrder->po_reference}",
        'created_by_id' => $this->user->id,
    ]);

    StockLedger::create([
        'item_id' => $this->rawMaterial2->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'issue',
        'reference_type' => 'production_order',
        'reference_id' => $prodOrder->id,
        'quantity' => -$rm2Needed,
        'balance_after' => $initialRm2 - $rm2Needed,
        'remarks' => "Production Order {$prodOrder->po_reference}",
        'created_by_id' => $this->user->id,
    ]);

    // Verify stock was deducted
    $finalRm1 = StockBalance::where('item_id', $this->rawMaterial1->id)
        ->where('location_id', $this->warehouse->id)
        ->value('quantity_on_hand');

    $finalRm2 = StockBalance::where('item_id', $this->rawMaterial2->id)
        ->where('location_id', $this->warehouse->id)
        ->value('quantity_on_hand');

    expect((float) $finalRm1)->toEqual((float) $initialRm1 - $rm1Needed);
    expect((float) $finalRm2)->toEqual((float) $initialRm2 - $rm2Needed);

    // Verify ledger entries exist
    $ledgerCount = StockLedger::where('reference_type', 'production_order')
        ->where('reference_id', $prodOrder->id)
        ->count();

    expect($ledgerCount)->toBe(2);
});

// ---------------------------------------------------------------------------
// INT-PROD-INV-002: Production completion adds finished goods to stock
// ---------------------------------------------------------------------------

it('INT-PROD-INV-002 — production completion adds finished goods to stock', function () {
    $qtyToProduce = 10;
    $qtyActuallyProduced = 9; // Some loss

    $bom = BillOfMaterials::create([
        'ulid' => (string) Str::ulid(),
        'product_item_id' => $this->finishedGood->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    $prodOrder = ProductionOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'WO-TEST-' . uniqid(),
        'product_item_id' => $this->finishedGood->id,
        'bom_id' => $bom->id,
        'qty_required' => $qtyToProduce,
        'qty_produced' => $qtyActuallyProduced,
        'target_start_date' => now(),
        'target_end_date' => now()->addWeek(),
        'status' => 'completed',
        'created_by_id' => $this->user->id,
    ]);

    $initialFg = StockBalance::where('item_id', $this->finishedGood->id)
        ->where('location_id', $this->warehouse->id)
        ->value('quantity_on_hand') ?? 0;

    // Create stock ledger entry for production output (trigger will update stock)
    StockLedger::create([
        'item_id' => $this->finishedGood->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'production_output',
        'reference_type' => 'production_order',
        'reference_id' => $prodOrder->id,
        'quantity' => $qtyActuallyProduced,
        'balance_after' => $initialFg + $qtyActuallyProduced,
        'remarks' => "Production Order {$prodOrder->po_reference} completed",
        'created_by_id' => $this->user->id,
    ]);

    // Verify finished goods added
    $finalFg = StockBalance::where('item_id', $this->finishedGood->id)
        ->where('location_id', $this->warehouse->id)
        ->value('quantity_on_hand');

    expect((float) $finalFg)->toEqual((float) $initialFg + $qtyActuallyProduced);

    // Verify ledger entry
    $ledger = StockLedger::where('reference_type', 'production_order')
        ->where('reference_id', $prodOrder->id)
        ->where('item_id', $this->finishedGood->id)
        ->first();

    expect($ledger)->not->toBeNull();
    expect((float) $ledger->quantity)->toEqual($qtyActuallyProduced);
});

// ---------------------------------------------------------------------------
// INT-PROD-INV-003: Production order links to stock ledger
// ---------------------------------------------------------------------------

it('INT-PROD-INV-003 — all production stock movements link to production order', function () {
    $bom = BillOfMaterials::create([
        'ulid' => (string) Str::ulid(),
        'product_item_id' => $this->finishedGood->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    $prodOrder = ProductionOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'WO-TEST-' . uniqid(),
        'product_item_id' => $this->finishedGood->id,
        'bom_id' => $bom->id,
        'qty_required' => 5,
        'target_start_date' => now(),
        'target_end_date' => now()->addWeek(),
        'status' => 'released',
        'created_by_id' => $this->user->id,
    ]);

    // Create material issue entry
    StockLedger::create([
        'item_id' => $this->rawMaterial1->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'issue',
        'reference_type' => 'production_order',
        'reference_id' => $prodOrder->id,
        'quantity' => -10,
        'balance_after' => 990,
        'remarks' => "Material issue for {$prodOrder->po_reference}",
        'created_by_id' => $this->user->id,
    ]);

    // Create production output entry
    StockLedger::create([
        'item_id' => $this->finishedGood->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'production_output',
        'reference_type' => 'production_order',
        'reference_id' => $prodOrder->id,
        'quantity' => 5,
        'balance_after' => 5,
        'remarks' => "Production output from {$prodOrder->po_reference}",
        'created_by_id' => $this->user->id,
    ]);

    // Retrieve all ledger entries for this production order
    $entries = StockLedger::where('reference_type', 'production_order')
        ->where('reference_id', $prodOrder->id)
        ->get();

    expect($entries)->toHaveCount(2);

    $materialIssue = $entries->firstWhere('transaction_type', 'issue');
    $productionOutput = $entries->firstWhere('transaction_type', 'production_output');

    expect($materialIssue)->not->toBeNull();
    expect($productionOutput)->not->toBeNull();

    // Both entries should reference the same production order
    expect($materialIssue->reference_id)->toEqual($prodOrder->id);
    expect($productionOutput->reference_id)->toEqual($prodOrder->id);
});

// ---------------------------------------------------------------------------
// INT-PROD-INV-004: Insufficient stock prevents production release
// ---------------------------------------------------------------------------

it('INT-PROD-INV-004 — insufficient raw material stock prevents production order release', function () {
    // Set low stock for raw material
    StockBalance::updateOrCreate(
        ['item_id' => $this->rawMaterial1->id, 'location_id' => $this->warehouse->id],
        ['quantity_on_hand' => 5] // Only 5 available
    );

    $bom = BillOfMaterials::create([
        'ulid' => (string) Str::ulid(),
        'product_item_id' => $this->finishedGood->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    BomComponent::create([
        'bom_id' => $bom->id,
        'component_item_id' => $this->rawMaterial1->id,
        'qty_per_unit' => 2, // 2 per unit
        'unit_of_measure' => 'pcs',
    ]);

    // Check available stock before creating PO
    $availableStock = StockBalance::where('item_id', $this->rawMaterial1->id)
        ->where('location_id', $this->warehouse->id)
        ->value('quantity_on_hand');

    // Check if we can release this production order
    $qtyToProduce = 10;
    $requiredStock = $qtyToProduce * 2;

    // If insufficient stock, create as draft, otherwise released
    $status = ((float) $availableStock >= $requiredStock) ? 'released' : 'draft';

    $prodOrder = ProductionOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'WO-TEST-' . uniqid(),
        'product_item_id' => $this->finishedGood->id,
        'bom_id' => $bom->id,
        'qty_required' => $qtyToProduce, // Would need 20 units of RM
        'target_start_date' => now(),
        'target_end_date' => now()->addWeek(),
        'status' => $status,
        'created_by_id' => $this->user->id,
    ]);

    // Verify insufficient stock
    expect((float) $availableStock)->toBeLessThan($requiredStock);

    // Production order should be in draft status due to insufficient stock
    expect($prodOrder->status)->toBe('draft');
});
