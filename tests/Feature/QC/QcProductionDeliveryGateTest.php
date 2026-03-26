<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
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

uses(RefreshDatabase::class);
uses()->group('feature', 'qc', 'production-delivery-gate');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // System user for auto-created records
    $this->systemUser = User::firstOrCreate(
        ['email' => 'admin@ogamierp.local'],
        ['name' => 'System Admin', 'password' => bcrypt('Admin@12345!')],
    );
    $this->systemUser->assignRole('admin');

    // Production user
    $this->prodUser = User::factory()->create();
    $this->prodUser->assignRole('head');

    // Customer
    $this->customer = Customer::factory()->unlimitedCredit()->create();

    // Finished goods item
    $fgCat = ItemCategory::create(['code' => 'FG-QC', 'name' => 'FG QC Test', 'is_active' => true]);
    $this->item = ItemMaster::create([
        'item_code' => 'FG-QC-001',
        'name' => 'QC Test Product',
        'unit_of_measure' => 'pcs',
        'category_id' => $fgCat->id,
        'item_type' => 'finished_good',
        'type' => 'finished_goods',
        'is_active' => true,
        'standard_price_centavos' => 100_00,
    ]);

    // Active BOM
    $this->bom = BillOfMaterials::create([
        'product_item_id' => $this->item->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 5,
    ]);

    // Warehouse
    $this->warehouse = WarehouseLocation::create([
        'name' => 'QC Test WH', 'code' => 'WH-QC-TEST', 'is_active' => true,
    ]);
});

/**
 * Helper: create a production order in completed state with a delivery schedule.
 */
function createCompletedWoWithSchedule(object $test, float $qtyProduced = 100, float $qtyRejected = 0): ProductionOrder
{
    // Create a minimal delivery schedule
    $schedule = DeliverySchedule::create([
        'customer_id' => $test->customer->id,
        'product_item_id' => $test->item->id,
        'qty_ordered' => $qtyProduced,
        'target_delivery_date' => now()->addDays(14)->toDateString(),
        'status' => 'open',
    ]);

    $wo = ProductionOrder::create([
        'delivery_schedule_id' => $schedule->id,
        'product_item_id' => $test->item->id,
        'bom_id' => $test->bom->id,
        'qty_required' => $qtyProduced,
        'target_start_date' => now()->toDateString(),
        'target_end_date' => now()->addDays(5)->toDateString(),
        'status' => 'completed',
        'created_by_id' => $test->prodUser->id,
    ]);

    // qty_produced is trigger-managed (not fillable), so set it via raw update
    DB::table('production_orders')
        ->where('id', $wo->id)
        ->update([
            'qty_produced' => $qtyProduced - $qtyRejected,
        ]);

    return $wo->fresh();
}

// ── Gap A: Auto OQC inspection on WO completion ──────────────────────────────

it('auto-creates OQC inspection when WO completes', function () {
    $wo = createCompletedWoWithSchedule($this, 100);

    // Invoke the listener directly (simulating event dispatch)
    $listener = app(CreateOqcInspectionOnProductionComplete::class);
    $listener->handle(new ProductionOrderCompleted($wo));

    $inspection = Inspection::where('production_order_id', $wo->id)
        ->where('stage', 'oqc')
        ->first();

    expect($inspection)->not->toBeNull()
        ->and($inspection->stage)->toBe('oqc')
        ->and((float) $inspection->qty_inspected)->toBe(100.0)
        ->and($inspection->item_master_id)->toBe($this->item->id)
        ->and($inspection->remarks)->toContain('Auto-created OQC');
});

it('does not duplicate OQC inspection if one already exists', function () {
    $wo = createCompletedWoWithSchedule($this, 100);

    $listener = app(CreateOqcInspectionOnProductionComplete::class);
    $listener->handle(new ProductionOrderCompleted($wo));
    $listener->handle(new ProductionOrderCompleted($wo)); // Second call

    $count = Inspection::where('production_order_id', $wo->id)
        ->where('stage', 'oqc')
        ->count();

    expect($count)->toBe(1);
});

// ── Gap B: DR gated behind QC pass ───────────────────────────────────────────

it('creates DR when OQC inspection passes', function () {
    $wo = createCompletedWoWithSchedule($this, 100);

    // Create an OQC inspection in passed state
    $inspection = Inspection::create([
        'stage' => 'oqc',
        'production_order_id' => $wo->id,
        'item_master_id' => $this->item->id,
        'qty_inspected' => 100,
        'qty_passed' => 95,
        'qty_failed' => 5,
        'inspection_date' => now()->toDateString(),
        'status' => 'passed',
        'created_by_id' => $this->systemUser->id,
    ]);

    $listener = app(CreateDeliveryReceiptOnOqcPass::class);
    $listener->handle(new InspectionPassed($inspection));

    // DR should exist with qty = 95 (only QC-passed units)
    $dr = DeliveryReceipt::where('delivery_schedule_id', $wo->delivery_schedule_id)->first();

    expect($dr)->not->toBeNull()
        ->and($dr->remarks)->toContain('QC-approved');
});

it('does not create DR for non-OQC inspections', function () {
    $wo = createCompletedWoWithSchedule($this, 100);

    // Create an IQC inspection (incoming, not outgoing)
    $inspection = Inspection::create([
        'stage' => 'iqc',
        'production_order_id' => $wo->id,
        'item_master_id' => $this->item->id,
        'qty_inspected' => 100,
        'qty_passed' => 100,
        'qty_failed' => 0,
        'inspection_date' => now()->toDateString(),
        'status' => 'passed',
        'created_by_id' => $this->systemUser->id,
    ]);

    $listener = app(CreateDeliveryReceiptOnOqcPass::class);
    $listener->handle(new InspectionPassed($inspection));

    $dr = DeliveryReceipt::where('delivery_schedule_id', $wo->delivery_schedule_id)->first();
    expect($dr)->toBeNull();
});

// ── Gap C: Rework WO on QC failure ───────────────────────────────────────────

it('creates rework WO when OQC fails and deficit exists', function () {
    $wo = createCompletedWoWithSchedule($this, 100);

    $inspection = Inspection::create([
        'stage' => 'oqc',
        'production_order_id' => $wo->id,
        'item_master_id' => $this->item->id,
        'qty_inspected' => 100,
        'qty_passed' => 80,
        'qty_failed' => 20,
        'inspection_date' => now()->toDateString(),
        'status' => 'failed',
        'created_by_id' => $this->systemUser->id,
    ]);

    $listener = app(CreateReworkOrderOnOqcFail::class);
    $listener->handle(new InspectionFailed($inspection));

    // Rework WO should exist for 20 units (100 required - 80 passed)
    $reworkWo = ProductionOrder::where('delivery_schedule_id', $wo->delivery_schedule_id)
        ->where('id', '!=', $wo->id)
        ->first();

    expect($reworkWo)->not->toBeNull()
        ->and((float) $reworkWo->qty_required)->toBe(20.0)
        ->and($reworkWo->status)->toBe('draft')
        ->and($reworkWo->notes)->toContain('Rework order')
        ->and($reworkWo->bom_id)->toBe($this->bom->id);
});

it('does not create rework WO when all items pass QC', function () {
    $wo = createCompletedWoWithSchedule($this, 100);

    $inspection = Inspection::create([
        'stage' => 'oqc',
        'production_order_id' => $wo->id,
        'item_master_id' => $this->item->id,
        'qty_inspected' => 100,
        'qty_passed' => 100,
        'qty_failed' => 0,
        'inspection_date' => now()->toDateString(),
        'status' => 'failed', // Edge case: marked failed but all passed
        'created_by_id' => $this->systemUser->id,
    ]);

    $listener = app(CreateReworkOrderOnOqcFail::class);
    $listener->handle(new InspectionFailed($inspection));

    $reworkWo = ProductionOrder::where('delivery_schedule_id', $wo->delivery_schedule_id)
        ->where('id', '!=', $wo->id)
        ->first();

    expect($reworkWo)->toBeNull();
});
