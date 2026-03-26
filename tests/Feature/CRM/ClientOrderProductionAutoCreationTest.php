<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Models\ClientOrderActivity;
use App\Domains\CRM\Services\ClientOrderService;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\ProductionOrder;
use App\Events\Production\ProductionOrderAutoCreated;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);
uses()->group('feature', 'crm', 'client-orders', 'production-auto');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Sales agent (approver) — different from submitter for SoD
    $this->salesUser = User::factory()->create();
    $this->salesUser->assignRole('officer');
    $this->salesUser->givePermissionTo([
        'sales.order_review',
        'sales.order_approve',
        'sales.order_reject',
        'sales.order_negotiate',
    ]);

    // Customer with unlimited credit + linked client portal user (submitter)
    $this->customer = Customer::factory()->unlimitedCredit()->create();
    $this->clientUser = User::factory()->create(['client_id' => $this->customer->id]);
    $this->clientUser->assignRole('client');

    // Item category
    $fgCat = ItemCategory::create(['code' => 'FG-AUTO', 'name' => 'Finished Goods Auto', 'is_active' => true]);

    // Finished goods item (use direct ::create matching codebase pattern)
    $this->item = ItemMaster::create([
        'item_code' => 'FG-AUTO-001',
        'name' => 'Auto Test Product',
        'unit_of_measure' => 'pcs',
        'category_id' => $fgCat->id,
        'item_type' => 'finished_good',
        'type' => 'finished_goods',
        'is_active' => true,
        'standard_price_centavos' => 100_00,
    ]);

    // Active BOM with 5 production days
    $this->bom = BillOfMaterials::create([
        'product_item_id' => $this->item->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 5,
    ]);

    // Warehouse location for stock balances
    $this->location = WarehouseLocation::create([
        'name' => 'Auto Test Warehouse',
        'code' => 'WH-AUTO-TEST',
        'is_active' => true,
    ]);

    $this->service = app(ClientOrderService::class);
});

/**
 * Helper: submit and approve a client order for the test item.
 */
function submitAndApproveAutoTest(object $test, float $quantity = 10): ClientOrder
{
    $order = $test->service->submitOrder(
        customerId: $test->customer->id,
        items: [[
            'item_master_id' => $test->item->id,
            'quantity' => $quantity,
            'unit_price_centavos' => 100_00,
        ]],
        requestedDate: now()->addDays(14)->toDateString(),
        submittedByUserId: $test->clientUser->id,
    );

    return $test->service->approveOrder($order, $test->salesUser->id);
}

// ── Gap #4: Target dates ─────────────────────────────────────────────────────

it('auto-creates production order with target dates when stock is insufficient', function () {
    // No stock at all → should create PO with dates
    Event::fake([ProductionOrderAutoCreated::class]);

    submitAndApproveAutoTest($this);

    $po = ProductionOrder::where('product_item_id', $this->item->id)->first();

    expect($po)->not->toBeNull()
        ->and($po->status)->toBe('draft')
        ->and($po->target_start_date)->not->toBeNull()
        ->and($po->target_end_date)->not->toBeNull()
        ->and($po->bom_id)->toBe($this->bom->id)
        ->and((float) $po->qty_required)->toBe(10.0)
        ->and($po->notes)->toContain('Auto-created from Client Order');
});

// ── Gap #1: Auto-fulfill from stock ──────────────────────────────────────────

it('auto-fulfills from stock and creates reservation when stock is sufficient', function () {
    // Seed stock: 100 units on hand → more than enough for 10-unit order
    StockBalance::create([
        'item_id' => $this->item->id,
        'location_id' => $this->location->id,
        'quantity_on_hand' => 100,
        'quantity_reserved' => 0,
    ]);

    Event::fake([ProductionOrderAutoCreated::class]);

    submitAndApproveAutoTest($this);

    // No production order should be created
    $po = ProductionOrder::where('product_item_id', $this->item->id)->first();
    expect($po)->toBeNull();

    // Stock reservation should exist for the delivery schedule
    $reservation = StockReservation::where('item_id', $this->item->id)
        ->where('reservation_type', 'delivery_schedule')
        ->where('status', 'active')
        ->first();

    expect($reservation)->not->toBeNull()
        ->and((float) $reservation->quantity_reserved)->toBe(10.0);

    // No ProductionOrderAutoCreated event should be dispatched
    Event::assertNotDispatched(ProductionOrderAutoCreated::class);
});

// ── Gap #6: Partial stock ────────────────────────────────────────────────────

it('creates partial reservation and deficit PO when stock is partial', function () {
    // Seed stock: 4 units on hand → 6-unit deficit for 10-unit order
    StockBalance::create([
        'item_id' => $this->item->id,
        'location_id' => $this->location->id,
        'quantity_on_hand' => 4,
        'quantity_reserved' => 0,
    ]);

    Event::fake([ProductionOrderAutoCreated::class]);

    submitAndApproveAutoTest($this);

    // Stock reservation should exist for the 4 available units
    $reservation = StockReservation::where('item_id', $this->item->id)
        ->where('reservation_type', 'delivery_schedule')
        ->where('status', 'active')
        ->first();

    expect($reservation)->not->toBeNull()
        ->and((float) $reservation->quantity_reserved)->toBe(4.0);

    // Production order should be created for the deficit (6 units)
    $po = ProductionOrder::where('product_item_id', $this->item->id)->first();

    expect($po)->not->toBeNull()
        ->and((float) $po->qty_required)->toBe(6.0)
        ->and($po->notes)->toContain('partial stock');
});

// ── Gap #2: BOM missing alert ────────────────────────────────────────────────

it('logs activity when BOM is missing for an item', function () {
    // Delete the BOM so there's no active one
    $this->bom->update(['is_active' => false]);

    submitAndApproveAutoTest($this);

    // Check that a bom_missing activity was logged
    $activity = ClientOrderActivity::where('action', 'bom_missing')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->user_type)->toBe('system')
        ->and($activity->metadata)->toHaveKey('item_id', $this->item->id)
        ->and($activity->metadata['message'])->toContain('No active BOM');

    // No production order should exist (no BOM to plan with)
    $po = ProductionOrder::where('product_item_id', $this->item->id)->first();
    expect($po)->toBeNull();
});

// ── Gap #5: Event dispatch ───────────────────────────────────────────────────

it('dispatches ProductionOrderAutoCreated event', function () {
    Event::fake([ProductionOrderAutoCreated::class]);

    submitAndApproveAutoTest($this);

    Event::assertDispatched(ProductionOrderAutoCreated::class, function ($event) {
        return $event->productionOrder->product_item_id === $this->item->id
            && $event->clientOrder->customer_id === $this->customer->id;
    });
});

// ── Gap #7: client_order_id traceability ─────────────────────────────────────

it('sets client_order_id on auto-created production order', function () {
    Event::fake([ProductionOrderAutoCreated::class]);

    $approvedOrder = submitAndApproveAutoTest($this);

    $po = ProductionOrder::where('product_item_id', $this->item->id)->first();

    expect($po)->not->toBeNull()
        ->and($po->client_order_id)->toBe($approvedOrder->id);
});
