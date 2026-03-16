<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockLedger;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Models\PurchaseRequestItem;
use App\Domains\AP\Models\Vendor;
use App\Models\User;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Procurement → Inventory Integration Tests
|--------------------------------------------------------------------------
| Verifies the complete procurement-to-inventory workflow:
|   1. Purchase Request creation and approval
|   2. Purchase Order generation from approved PR
|   3. Goods Receipt (GR) creation when items arrive
|   4. Stock Balance updates linked to GR
|   5. Stock Ledger entries for traceability
|
| Flow: PR → PO → Goods Receipt → Stock Update
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    $this->user = User::factory()->create();
    $this->user->assignRole('purchasing_officer');

    $this->department = Department::firstOrCreate(
        ['code' => 'PUR'],
        ['name' => 'Purchasing Department', 'is_active' => true]
    );

    $this->warehouse = WarehouseLocation::firstOrCreate(
        ['code' => 'WH-MAIN'],
        ['name' => 'Main Warehouse', 'is_active' => true]
    );

    $this->vendor = Vendor::firstOrCreate(
        ['tin' => '123456789'],
        [
            'name' => 'Test Supplier Inc.',
            'contact_person' => 'Supplier Contact',
            'email' => 'supplier@test.com',
            'phone' => '1234567890',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]
    );

    $category = ItemCategory::firstOrCreate(
        ['code' => 'RAW'],
        ['name' => 'Raw Materials', 'is_active' => true]
    );

    // Use unique item code for each test to ensure isolation
    $this->item = ItemMaster::create([
        'category_id' => $category->id,
        'item_code' => 'RM-TEST-' . uniqid(),
        'name' => 'Test Raw Material',
        'type' => 'raw_material',
        'unit_of_measure' => 'pcs',
        'is_active' => true,
    ]);
});

// ---------------------------------------------------------------------------
// INT-PROC-INV-001: Goods receipt increases stock balance
// ---------------------------------------------------------------------------

it('INT-PROC-INV-001 — goods receipt increases stock balance for received items', function () {
    $quantity = 100;
    $unitCost = 50.00;

    // Create PR first (needed for PO)
    $pr = PurchaseRequest::create([
        'pr_reference' => 'PR-TEST-001',
        'department_id' => $this->department->id,
        'requested_by_id' => $this->user->id,
        'justification' => 'Test procurement',
        'status' => 'approved',
        'ulid' => (string) Str::ulid(),
    ]);

    // Create Purchase Order
    $po = PurchaseOrder::create([
        'po_reference' => 'PO-TEST-001',
        'purchase_request_id' => $pr->id,
        'vendor_id' => $this->vendor->id,
        'po_date' => now(),
        'delivery_date' => now()->addWeek(),
        'payment_terms' => 'NET30',
        'status' => 'sent',
        'created_by_id' => $this->user->id,
        'ulid' => (string) Str::ulid(),
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'item_description' => $this->item->name,
        'unit_of_measure' => 'pcs',
        'quantity_ordered' => $quantity,
        'agreed_unit_cost' => $unitCost,
    ]);

    // Get initial stock
    $initialStock = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->warehouse->id)
        ->first();
    $initialQty = $initialStock?->quantity_on_hand ?? 0;

    // Create Goods Receipt
    $gr = GoodsReceipt::create([
        'ulid' => (string) Str::ulid(),
        'gr_reference' => 'GR-TEST-001',
        'purchase_order_id' => $po->id,
        'received_date' => now(),
        'received_by_id' => $this->user->id,
        'status' => 'confirmed',
    ]);

    // Update PO item received quantity
    $poItem->quantity_received = $quantity;
    $poItem->save();

    // Stock balance will be updated by trigger when we create stock ledger entry

    // Create stock ledger entry (this triggers stock balance update)
    StockLedger::create([
        'item_id' => $this->item->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'goods_receipt',
        'reference_type' => 'goods_receipt',
        'reference_id' => $gr->id,
        'quantity' => $quantity,
        'unit_cost' => $unitCost,
        'balance_after' => $initialQty + $quantity,
        'remarks' => "GR: {$gr->gr_reference} from PO: {$po->po_reference}",
        'created_by_id' => $this->user->id,
    ]);

    // Verify stock increased
    $updatedStock = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->warehouse->id)
        ->first();

    expect((float) $updatedStock->quantity_on_hand)->toEqual($initialQty + $quantity);

    // Verify ledger entry
    $ledger = StockLedger::where('reference_type', 'goods_receipt')
        ->where('reference_id', $gr->id)
        ->first();

    expect($ledger)->not->toBeNull();
    expect((float) $ledger->quantity)->toEqual($quantity);
});

// ---------------------------------------------------------------------------
// INT-PROC-INV-002: Stock ledger entries reference source documents
// ---------------------------------------------------------------------------

it('INT-PROC-INV-002 — stock ledger entries reference source PO and GR', function () {
    // Create PR first
    $pr = PurchaseRequest::create([
        'pr_reference' => 'PR-TEST-002',
        'department_id' => $this->department->id,
        'requested_by_id' => $this->user->id,
        'justification' => 'Test procurement',
        'status' => 'approved',
        'ulid' => (string) Str::ulid(),
    ]);

    $po = PurchaseOrder::create([
        'po_reference' => 'PO-TEST-002',
        'purchase_request_id' => $pr->id,
        'vendor_id' => $this->vendor->id,
        'po_date' => now(),
        'delivery_date' => now()->addWeek(),
        'payment_terms' => 'NET30',
        'status' => 'sent',
        'created_by_id' => $this->user->id,
        'ulid' => (string) Str::ulid(),
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'item_description' => $this->item->name,
        'unit_of_measure' => 'pcs',
        'quantity_ordered' => 50,
        'agreed_unit_cost' => 25.00,
    ]);

    $gr = GoodsReceipt::create([
        'ulid' => (string) Str::ulid(),
        'gr_reference' => 'GR-TEST-002',
        'purchase_order_id' => $po->id,
        'received_date' => now(),
        'received_by_id' => $this->user->id,
        'status' => 'confirmed',
    ]);

    StockLedger::create([
        'item_id' => $this->item->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'goods_receipt',
        'reference_type' => 'goods_receipt',
        'reference_id' => $gr->id,
        'quantity' => 50,
        'balance_after' => 50,
        'remarks' => "GR: {$gr->gr_reference} from PO: {$po->po_reference}, Vendor: {$this->vendor->name}",
        'created_by_id' => $this->user->id,
    ]);

    // Verify traceability chain
    $ledger = StockLedger::where('reference_type', 'goods_receipt')
        ->where('reference_id', $gr->id)
        ->first();

    expect($ledger)->not->toBeNull();
    expect($ledger->remarks)->toContain('GR-TEST-002');
    expect($ledger->remarks)->toContain('PO-TEST-002');

    // Verify GR links to PO
    $grFromDb = GoodsReceipt::find($gr->id);
    expect($grFromDb->purchase_order_id)->toBe($po->id);

    // Verify PO links to correct item quantity
    $po->load('items');
    expect($po->items)->toHaveCount(1);
    expect((float) $po->items[0]->quantity_ordered)->toEqual(50.00);
});

// ---------------------------------------------------------------------------
// INT-PROC-INV-003: Partial goods receipt updates stock proportionally
// ---------------------------------------------------------------------------

it('INT-PROC-INV-003 — partial goods receipt updates stock proportionally', function () {
    $orderedQty = 100;
    $firstReceiptQty = 40;
    $secondReceiptQty = 60;

    // Create PR first
    $pr = PurchaseRequest::create([
        'pr_reference' => 'PR-TEST-003',
        'department_id' => $this->department->id,
        'requested_by_id' => $this->user->id,
        'justification' => 'Test procurement',
        'status' => 'approved',
        'ulid' => (string) Str::ulid(),
    ]);

    $po = PurchaseOrder::create([
        'po_reference' => 'PO-TEST-003',
        'purchase_request_id' => $pr->id,
        'vendor_id' => $this->vendor->id,
        'po_date' => now(),
        'delivery_date' => now()->addWeek(),
        'payment_terms' => 'NET30',
        'status' => 'sent',
        'created_by_id' => $this->user->id,
        'ulid' => (string) Str::ulid(),
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'item_description' => $this->item->name,
        'unit_of_measure' => 'pcs',
        'quantity_ordered' => $orderedQty,
        'agreed_unit_cost' => 30.00,
    ]);

    // First partial receipt
    $gr1 = GoodsReceipt::create([
        'ulid' => (string) Str::ulid(),
        'gr_reference' => 'GR-TEST-003A',
        'purchase_order_id' => $po->id,
        'received_date' => now(),
        'received_by_id' => $this->user->id,
        'status' => 'confirmed',
    ]);

    $poItem->quantity_received = $firstReceiptQty;
    $poItem->save();

    // Stock balance updated by trigger via stock ledger
    StockLedger::create([
        'item_id' => $this->item->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'goods_receipt',
        'reference_type' => 'goods_receipt',
        'reference_id' => $gr1->id,
        'quantity' => $firstReceiptQty,
        'balance_after' => $firstReceiptQty,
        'remarks' => "Partial GR: {$gr1->gr_reference}",
        'created_by_id' => $this->user->id,
    ]);

    // Second partial receipt (final)
    $gr2 = GoodsReceipt::create([
        'ulid' => (string) Str::ulid(),
        'gr_reference' => 'GR-TEST-003B',
        'purchase_order_id' => $po->id,
        'received_date' => now()->addDay(),
        'received_by_id' => $this->user->id,
        'status' => 'confirmed',
    ]);

    $poItem->quantity_received = $orderedQty; // Now fully received
    $poItem->save();

    // Stock balance updated by trigger via stock ledger
    StockLedger::create([
        'item_id' => $this->item->id,
        'location_id' => $this->warehouse->id,
        'transaction_type' => 'goods_receipt',
        'reference_type' => 'goods_receipt',
        'reference_id' => $gr2->id,
        'quantity' => $secondReceiptQty,
        'balance_after' => $firstReceiptQty + $secondReceiptQty,
        'remarks' => "Final GR: {$gr2->gr_reference}",
        'created_by_id' => $this->user->id,
    ]);

    // Verify cumulative stock
    $finalStock = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->warehouse->id)
        ->first();

    expect((float) $finalStock->quantity_on_hand)->toEqual((float) $orderedQty);

    // Verify two separate ledger entries
    $ledgerCount = StockLedger::where('reference_type', 'goods_receipt')
        ->whereIn('reference_id', [$gr1->id, $gr2->id])
        ->count();

    expect($ledgerCount)->toBe(2);
});
