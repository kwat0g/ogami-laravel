<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Services\ClientOrderService;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\HR\Models\Employee;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\QC\Models\Inspection;
use App\Events\Production\ProductionOrderCompleted;
use App\Events\QC\InspectionFailed;
use App\Events\QC\InspectionPassed;
use App\Listeners\Delivery\CreateDeliveryReceiptOnOqcPass;
use App\Listeners\Production\CreateReworkOrderOnOqcFail;
use App\Listeners\QC\CreateOqcInspectionOnProductionComplete;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);
uses()->group('integration', 'workflow', 'e2e');

beforeEach(function () {
    // Manually register listeners for testing because auto-discovery sometimes fails in Pest
    Event::listen(ProductionOrderCompleted::class, CreateOqcInspectionOnProductionComplete::class);
    Event::listen(InspectionPassed::class, CreateDeliveryReceiptOnOqcPass::class);
    Event::listen(InspectionFailed::class, CreateReworkOrderOnOqcFail::class);

    $this->seed(RolePermissionSeeder::class);

    // System users
    $this->systemUser = User::firstOrCreate(
        ['email' => 'admin@ogamierp.local'],
        ['name' => 'System Admin', 'password' => bcrypt('Admin@12345!')],
    );
    $this->systemUser->assignRole('admin');

    $this->vpUser = User::factory()->create();
    $this->vpUser->assignRole('vice_president');

    $this->salesUser = User::factory()->create();
    $this->salesUser->assignRole('manager');

    $this->employee = Employee::factory()->create();

    $this->customer = Customer::factory()->unlimitedCredit()->create();

    // Finished Goods Product
    $fgCat = ItemCategory::create(['code' => 'FG', 'name' => 'Finished Goods', 'is_active' => true]);
    $this->product = ItemMaster::create([
        'item_code' => 'FG-001',
        'name' => 'Premium Gadget',
        'unit_of_measure' => 'pcs',
        'category_id' => $fgCat->id,
        'item_type' => 'finished_good',
        'type' => 'finished_goods',
        'is_active' => true,
        'standard_price_centavos' => 5000_00,
    ]);

    // Active BOM
    $this->bom = BillOfMaterials::create([
        'product_item_id' => $this->product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 5,
    ]);

    $this->warehouse = WarehouseLocation::create([
        'name' => 'Main Warehouse', 'code' => 'WH-MAIN', 'is_active' => true,
    ]);

    $this->clientOrderService = app(ClientOrderService::class);
});

// Helper: Simulate production output
function completeProductionOrder(ProductionOrder $po, float $qtyProduced): void
{
    // Update PO qty and status manually because output logs are usually handled via UI
    DB::table('production_orders')
        ->where('id', $po->id)
        ->update([
            'qty_produced' => $qtyProduced,
            'status' => 'completed',
        ]);

    $po->refresh();
    $event = new ProductionOrderCompleted($po);
    event($event);

    // Force listener execution for tests (bypassing ShouldQueue)
    app(CreateOqcInspectionOnProductionComplete::class)->handle($event);
}

// ── Scenario A: Perfect Flow ──────────────────────────────────────────────────
it('executes perfect E2E flow: Client Order -> PO -> QC Pass -> Delivery', function () {
    // 1. Sales creates Client Order
    $order = $this->clientOrderService->submitOrder(
        $this->customer->id,
        [
            [
                'item_master_id' => $this->product->id,
                'quantity' => 100,
                'unit_price_centavos' => 5000_00,
            ],
        ],
        now()->addDays(14)->toDateString(),
        null,
        $this->salesUser->id
    );

    // 2. Submit and Approve Order
    // In Ogami, submitOrder creates it as pending, then approveOrder takes it to VP or approved
    $this->clientOrderService->approveOrder($order, $this->vpUser->id);

    // Order becomes open/processing -> triggers auto-creation of Delivery Schedule and PO
    // Verify Delivery Schedule was created
    $schedule = DeliverySchedule::where('product_item_id', $this->product->id)
        ->where('customer_id', $this->customer->id)
        ->first();
    expect($schedule)->not->toBeNull()
        ->and((float) $schedule->qty_ordered)->toBe(100.0);

    // Verify PO was created for 100 qty
    $po = ProductionOrder::where('client_order_id', $order->id)->first();
    expect($po)->not->toBeNull()
        ->and((float) $po->qty_required)->toBe(100.0)
        ->and($po->delivery_schedule_id)->toBe($schedule->id);

    // 3. Production Completes
    completeProductionOrder($po, 100.0);

    // 4. Verify OQC Inspection was auto-created
    $inspection = Inspection::where('production_order_id', $po->id)
        ->where('stage', 'oqc')
        ->first();
    expect($inspection)->not->toBeNull()
        ->and((float) $inspection->qty_inspected)->toBe(100.0)
        ->and($inspection->status)->toBe('open');

    // Verify NO Delivery Receipt exists yet (QC Gate)
    $drCount = DeliveryReceipt::where('delivery_schedule_id', $schedule->id)->count();
    expect($drCount)->toBe(0);

    // 5. QC Inspects and Passes
    $oqc = $inspection;
    $oqc->update([
        'status' => 'passed',
        'qty_passed' => 100,
        'qty_failed' => 0,
        'inspector_id' => $this->employee->id,
    ]);
    $event = new InspectionPassed($oqc);
    event($event);
    app(CreateDeliveryReceiptOnOqcPass::class)->handle($event);

    // 6. Verify Delivery Receipt was created
    $dr = DeliveryReceipt::where('delivery_schedule_id', $schedule->id)
        ->first();
    expect($dr)->not->toBeNull()
        ->and((float) $dr->items()->first()->quantity_expected)->toBe(100.0);

    // Validate DS status is ready
    $schedule->refresh();
    expect($schedule->status)->toBe('ready');
});

// ── Scenario B: QC Rejection and Rework ───────────────────────────────────────
it('executes rework E2E flow: Client Order -> PO -> QC Fail -> Rework PO -> QC Pass -> Delivery', function () {
    // 1. Order Creation & Approval
    $order = $this->clientOrderService->submitOrder(
        $this->customer->id,
        [
            ['item_master_id' => $this->product->id, 'quantity' => 50, 'unit_price_centavos' => 5000_00],
        ],
        now()->addDays(14)->toDateString(),
        null,
        $this->salesUser->id
    );

    $this->clientOrderService->approveOrder($order, $this->vpUser->id);

    $schedule = DeliverySchedule::where('product_item_id', $this->product->id)->first();
    $po1 = ProductionOrder::where('client_order_id', $order->id)->first();

    // 2. Production Completes 50
    completeProductionOrder($po1, 50.0);

    // OQC #1 created
    $oqc1 = Inspection::where('production_order_id', $po1->id)->first();
    expect($oqc1)->not->toBeNull();

    // 3. QC Inspects: 40 Pass, 10 Fail
    $oqc1->update([
        'status' => 'failed',
        'qty_passed' => 40,
        'qty_failed' => 10,
        'inspector_id' => $this->employee->id,
    ]);

    // Simulate events (InspectionService normally dispatches both passed and failed if there are passed and failed items respectively,
    // but in reality NcrService handles failed and InspectionService handles passed)
    // For test simulation, let's trigger both to test the handlers
    $passEvent = new InspectionPassed($oqc1);
    event($passEvent);
    app(CreateDeliveryReceiptOnOqcPass::class)->handle($passEvent);

    $failEvent = new InspectionFailed($oqc1);
    event($failEvent);
    app(CreateReworkOrderOnOqcFail::class)->handle($failEvent);

    // 4. Verify Delivery Receipt is created for the 40 passed units
    $dr1 = DeliveryReceipt::where('delivery_schedule_id', $schedule->id)->first();
    expect($dr1)->not->toBeNull()
        ->and((float) $dr1->items()->first()->quantity_expected)->toBe(40.0);

    // 5. Verify Rework PO is created for 10 units
    $reworkPo = ProductionOrder::where('client_order_id', $order->id)
        ->where('id', '!=', $po1->id)
        ->first();
    expect($reworkPo)->not->toBeNull()
        ->and((float) $reworkPo->qty_required)->toBe(10.0)
        ->and($reworkPo->notes)->toContain('Rework order');

    // 6. Rework Production Completes
    completeProductionOrder($reworkPo, 10.0);

    // 7. Verify OQC #2 is created
    $oqc2 = Inspection::where('production_order_id', $reworkPo->id)->first();
    expect($oqc2)->not->toBeNull()
        ->and((float) $oqc2->qty_inspected)->toBe(10.0);

    // 8. QC Inspects Rework: 10 Pass
    $oqc2->update([
        'status' => 'passed',
        'qty_passed' => 10,
        'qty_failed' => 0,
        'inspector_id' => $this->employee->id,
    ]);
    $passEvent2 = new InspectionPassed($oqc2);
    event($passEvent2);
    app(CreateDeliveryReceiptOnOqcPass::class)->handle($passEvent2);

    // 9. Verify second DR is created for the remaining 10 units
    $drCount = DeliveryReceipt::where('delivery_schedule_id', $schedule->id)->count();
    expect($drCount)->toBe(2);

    $dr2 = DeliveryReceipt::where('delivery_schedule_id', $schedule->id)
        ->orderBy('id', 'desc')
        ->first();
    expect((float) $dr2->items()->first()->quantity_expected)->toBe(10.0);
});

// ── Scenario C: Partial Stock Fulfillment + Production ─────────────────────────
it('executes partial stock flow: Client Order -> PO (deficit) -> QC Pass', function () {
    // Inject 30 units of stock beforehand
    StockBalance::create([
        'item_id' => $this->product->id,
        'location_id' => $this->warehouse->id,
        'quantity_on_hand' => 30,
    ]);

    // 1. Order requested for 100 units
    $order = $this->clientOrderService->submitOrder(
        $this->customer->id,
        [
            ['item_master_id' => $this->product->id, 'quantity' => 100, 'unit_price_centavos' => 5000_00],
        ],
        now()->addDays(14)->toDateString(),
        null,
        $this->salesUser->id
    );

    $this->clientOrderService->approveOrder($order, $this->vpUser->id);

    // 2. PO created for only 70 units (deficit)
    $po = ProductionOrder::where('client_order_id', $order->id)->first();
    expect($po)->not->toBeNull()
        ->and((float) $po->qty_required)->toBe(70.0);

    // Delivery Schedule reflects full 100 required
    $schedule = DeliverySchedule::where('product_item_id', $this->product->id)->first();
    expect((float) $schedule->qty_ordered)->toBe(100.0);

    // 3. Production Completes the 70 units
    completeProductionOrder($po, 70.0);

    $oqc = Inspection::where('production_order_id', $po->id)->first();
    expect($oqc)->not->toBeNull()
        ->and((float) $oqc->qty_inspected)->toBe(70.0);

    // 4. QC Passes
    $oqc->update([
        'status' => 'passed',
        'qty_passed' => 70,
        'qty_failed' => 0,
        'inspector_id' => $this->employee->id,
    ]);
    $passEvent3 = new InspectionPassed($oqc);
    event($passEvent3);
    app(CreateDeliveryReceiptOnOqcPass::class)->handle($passEvent3);

    // 5. DR is created for the 70 produced units
    // (The 30 units from stock would be handled by a separate warehouse fulfillment process,
    // but the PO outputs are correctly gated to 70)
    $dr = DeliveryReceipt::where('delivery_schedule_id', $schedule->id)->first();
    expect($dr)->not->toBeNull()
        ->and((float) $dr->items()->first()->quantity_expected)->toBe(70.0);
});
