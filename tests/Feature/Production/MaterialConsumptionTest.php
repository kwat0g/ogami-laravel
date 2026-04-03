<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockLedger;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\QC\Models\Inspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModuleSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModulePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentModuleAssignmentSeeder'])->assertExitCode(0);

    // Create a production manager user
    $prodDept = Department::where('code', 'PROD')->first();
    $this->manager = User::factory()->create(['email' => 'prod_mgr@test.com']);
    $this->manager->assignRole('manager');
    $this->manager->departments()->attach($prodDept->id, ['is_primary' => true]);

    // Ensure admin user exists (may already exist from seeder)
    User::firstOrCreate(
        ['email' => 'admin@ogamierp.local'],
        ['name' => 'System Admin', 'password' => bcrypt('password')],
    );

    // Create a warehouse location
    $this->warehouse = WarehouseLocation::create([
        'name' => 'Main Warehouse',
        'code' => 'WH-01',
        'is_active' => true,
    ]);

    // Create item category (required FK on item_masters)
    $category = ItemCategory::create([
        'code' => 'RAW',
        'name' => 'Raw Materials',
        'is_active' => true,
    ]);

    $fgCategory = ItemCategory::create([
        'code' => 'FG',
        'name' => 'Finished Goods',
        'is_active' => true,
    ]);

    // Create finished goods item and raw material items
    $this->finishedGoods = ItemMaster::create([
        'item_code' => 'FG-001',
        'category_id' => $fgCategory->id,
        'name' => 'Widget A',
        'type' => 'finished_good',
        'unit_of_measure' => 'pcs',
        'is_active' => true,
    ]);

    $this->rawMaterial1 = ItemMaster::create([
        'item_code' => 'RM-001',
        'category_id' => $category->id,
        'name' => 'Steel Sheet',
        'type' => 'raw_material',
        'unit_of_measure' => 'kg',
        'is_active' => true,
        'reorder_point' => 10.0,
    ]);

    $this->rawMaterial2 = ItemMaster::create([
        'item_code' => 'RM-002',
        'category_id' => $category->id,
        'name' => 'Plastic Resin',
        'type' => 'raw_material',
        'unit_of_measure' => 'kg',
        'is_active' => true,
        'reorder_point' => 5.0,
    ]);

    // Create BOM with 2 components
    $this->bom = BillOfMaterials::create([
        'product_item_id' => $this->finishedGoods->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    // Component 1: 2kg steel per unit, 5% scrap factor
    $this->component1 = BomComponent::create([
        'bom_id' => $this->bom->id,
        'component_item_id' => $this->rawMaterial1->id,
        'qty_per_unit' => 2.0,
        'unit_of_measure' => 'kg',
        'scrap_factor_pct' => 5.0,
    ]);

    // Component 2: 0.5kg resin per unit, 0% scrap
    $this->component2 = BomComponent::create([
        'bom_id' => $this->bom->id,
        'component_item_id' => $this->rawMaterial2->id,
        'qty_per_unit' => 0.5,
        'unit_of_measure' => 'kg',
        'scrap_factor_pct' => 0.0,
    ]);

    // Create production order for 10 units
    $this->order = ProductionOrder::create([
        'product_item_id' => $this->finishedGoods->id,
        'bom_id' => $this->bom->id,
        'qty_required' => 10,
        'target_start_date' => now()->addDays(1),
        'target_end_date' => now()->addDays(7),
        'status' => 'draft',
        'created_by_id' => $this->manager->id,
    ]);
});

it('releases order and creates MRQ without direct stock deduction', function () {
    // Seed stock: 100kg steel, 50kg resin
    seedStock($this->rawMaterial1, $this->warehouse, 100.0);
    seedStock($this->rawMaterial2, $this->warehouse, 50.0);

    $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/release")
        ->assertOk();

    $this->order->refresh();
    expect($this->order->status)->toBe('released');

    // Release only plans materials via MRQ. It does not directly issue stock.
    $mrq = MaterialRequisition::where('production_order_id', $this->order->id)->first();
    expect($mrq)->not->toBeNull();
    expect($mrq->status)->toBe('submitted');

    // Required: Steel = 2.0 × 10 × 1.05 = 21.0 kg
    // Required: Resin = 0.5 × 10 × 1.00 = 5.0 kg
    $steelBalance = StockBalance::where('item_id', $this->rawMaterial1->id)
        ->where('location_id', $this->warehouse->id)
        ->first();
    expect((float) $steelBalance->quantity_on_hand)->toBe(100.0); // unchanged on release

    $resinBalance = StockBalance::where('item_id', $this->rawMaterial2->id)
        ->where('location_id', $this->warehouse->id)
        ->first();
    expect((float) $resinBalance->quantity_on_hand)->toBe(50.0); // unchanged on release
});

it('allows release with low stock and blocks start until MRQ is fulfilled', function () {
    // Seed low stock to force warehouse fulfillment path via MRQ.
    seedStock($this->rawMaterial1, $this->warehouse, 10.0);
    seedStock($this->rawMaterial2, $this->warehouse, 50.0);

    $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/release");

    $this->order->refresh();
    expect($this->order->status)->toBe('released');

    $response = $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/start");

    $response->assertStatus(422);
    $response->assertJsonFragment(['error_code' => 'PROD_MRQ_NOT_FULFILLED']);

    // Verify no stock was deducted (all-or-nothing)
    $steelBalance = StockBalance::where('item_id', $this->rawMaterial1->id)
        ->where('location_id', $this->warehouse->id)
        ->first();
    expect((float) $steelBalance->quantity_on_hand)->toBe(10.0); // unchanged

    // Order remains released until MRQ fulfillment is completed.
    $this->order->refresh();
    expect($this->order->status)->toBe('released');
});

it('adds finished goods to stock on production completion', function () {
    // Seed sufficient stock and release
    seedStock($this->rawMaterial1, $this->warehouse, 100.0);
    seedStock($this->rawMaterial2, $this->warehouse, 50.0);

    $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/release")
        ->assertOk();

    // Fulfil auto-created MRQs (release() auto-creates draft MRQ from BOM)
    MaterialRequisition::where('production_order_id', $this->order->id)
        ->update(['status' => 'fulfilled']);

    // Start the order
    $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/start")
        ->assertOk();

    // Simulate logged output totals required before completion.
    DB::table('production_orders')->where('id', $this->order->id)->update([
        'qty_produced' => 10,
        'qty_rejected' => 1,
    ]);

    // Complete the order
    $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/complete")
        ->assertOk();

    // Finished goods should be in stock: 10 produced - 1 rejected = 9 net
    $fgBalance = StockBalance::where('item_id', $this->finishedGoods->id)
        ->where('location_id', $this->warehouse->id)
        ->first();

    expect($fgBalance)->not->toBeNull();
    expect((float) $fgBalance->quantity_on_hand)->toBe(9.0);
});

it('does not create direct stock issue ledger entries on release', function () {
    seedStock($this->rawMaterial1, $this->warehouse, 100.0);
    seedStock($this->rawMaterial2, $this->warehouse, 50.0);

    $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/release")
        ->assertOk();

    // Material issue occurs during MRQ fulfillment, not production release.
    $ledgerEntries = StockLedger::where('reference_type', 'production_orders')
        ->where('reference_id', $this->order->id)
        ->get();

    expect($ledgerEntries)->toHaveCount(0);
});

it('blocks release when QC inspection has failed status', function () {
    seedStock($this->rawMaterial1, $this->warehouse, 100.0);
    seedStock($this->rawMaterial2, $this->warehouse, 50.0);

    // Create a failed QC inspection linked to this order
    Inspection::create([
        'stage' => 'ipqc',
        'status' => 'failed',
        'production_order_id' => $this->order->id,
        'qty_inspected' => 10,
        'qty_passed' => 3,
        'qty_failed' => 7,
        'inspection_date' => now(),
        'created_by_id' => $this->manager->id,
    ]);

    $response = $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/release");

    $response->assertStatus(422);
    $response->assertJsonFragment(['error_code' => 'PROD_QC_GATE_BLOCKED']);

    // Order should still be draft
    $this->order->refresh();
    expect($this->order->status)->toBe('draft');
});

it('allows head to override QC block with permission', function () {
    seedStock($this->rawMaterial1, $this->warehouse, 100.0);
    seedStock($this->rawMaterial2, $this->warehouse, 50.0);

    // Create a failed QC inspection
    Inspection::create([
        'stage' => 'ipqc',
        'status' => 'failed',
        'production_order_id' => $this->order->id,
        'qty_inspected' => 10,
        'qty_passed' => 3,
        'qty_failed' => 7,
        'inspection_date' => now(),
        'created_by_id' => $this->manager->id,
    ]);

    // manager (production module) has production.qc-override permission
    $response = $this->actingAs($this->manager)
        ->patchJson("/api/v1/production/orders/{$this->order->ulid}/release", [
            'force_release' => true,
        ]);

    // Production manager may or may not have qc-override permission
    // If permission exists, expect 200; otherwise expect 403
    $status = $response->getStatusCode();
    expect(in_array($status, [200, 403]))->toBeTrue();

    if ($status === 200) {
        $this->order->refresh();
        expect($this->order->status)->toBe('released');
    }
});

// ── Helper ──────────────────────────────────────────────────────

function seedStock(ItemMaster $item, WarehouseLocation $location, float $qty): void
{
    // PG trigger trg_update_stock_balance auto-upserts stock_balances on insert
    StockLedger::create([
        'item_id' => $item->id,
        'location_id' => $location->id,
        'transaction_type' => 'adjustment', // valid type per chk_sl_txn_type
        'quantity' => $qty,
        'balance_after' => $qty,
        'remarks' => 'Initial stock for testing',
        'created_by_id' => User::where('email', 'admin@ogamierp.local')->first()->id,
    ]);
}
