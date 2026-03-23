<?php

declare(strict_types=1);

/**
 * Production — Work Order RBAC & MRQ-Blocking Workflow Tests
 *
 * Coverage:
 *   PROD-RBAC-001  production.head (PROD dept) can create a WO
 *   PROD-RBAC-002  production.staff (PROD dept) CANNOT create a WO — 403
 *   PROD-RBAC-003  production.staff can view WOs (view-only access)
 *   PROD-RBAC-004  ACCTG officer CANNOT access production orders — 403 (wrong dept)
 *   PROD-WF-001    head can release a draft WO
 *   PROD-WF-002    WO release auto-creates a linked MRQ when BOM has components
 *   PROD-WF-003    WO start BLOCKED by unfulfilled linked MRQ (422 PROD_MRQ_NOT_FULFILLED)
 *   PROD-WF-004    WO start succeeds when all linked MRQs are fulfilled or cancelled
 *   PROD-DS-001    production.head can view delivery schedules
 */

use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
uses()->group('feature', 'role-access', 'production');

// ── Setup ─────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModuleSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModulePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentModuleAssignmentSeeder'])->assertExitCode(0);

    // Clear both Spatie permission cache and DepartmentModuleService's module-permission cache
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Cache::flush();

    $this->prodDept  = Department::where('code', 'PROD')->firstOrFail();
    $this->acctgDept = Department::where('code', 'ACCTG')->firstOrFail();

    // Items: raw material + finished good
    $rawCat = ItemCategory::create(['code' => 'RAW', 'name' => 'Raw Materials', 'is_active' => true]);
    $fgCat  = ItemCategory::create(['code' => 'FG',  'name' => 'Finished Goods', 'is_active' => true]);

    $this->rawMaterial = ItemMaster::create([
        'item_code'       => 'RM-WF-001',
        'name'            => 'Steel Sheet',
        'unit_of_measure' => 'kg',
        'category_id'     => $rawCat->id,
        'item_type'       => 'raw_material',
        'is_active'       => true,
    ]);
    $this->finishedGood = ItemMaster::create([
        'item_code'       => 'FG-WF-001',
        'name'            => 'Widget A',
        'unit_of_measure' => 'pcs',
        'category_id'     => $fgCat->id,
        'item_type'       => 'finished_good',
        'is_active'       => true,
    ]);

    // BOM: 2 kg raw per 1 unit finished good
    $this->bom = BillOfMaterials::create([
        'product_item_id' => $this->finishedGood->id,
        'version'         => '1.0',
        'is_active'       => true,
    ]);
    BomComponent::create([
        'bom_id'            => $this->bom->id,
        'component_item_id' => $this->rawMaterial->id,
        'qty_per_unit'      => 2.0,
        'unit_of_measure'   => 'kg',
        'scrap_factor_pct'  => 0.0,
    ]);

    // Active warehouse location — required by deductBomComponents() on WO release
    $this->warehouse = WarehouseLocation::create([
        'name'      => 'Test Warehouse',
        'code'      => 'WH-PROD-TEST',
        'is_active' => true,
    ]);

    // Minimal valid WO payload
    $this->woPayload = [
        'product_item_id'   => $this->finishedGood->id,
        'bom_id'            => $this->bom->id,
        'qty_required'      => 5,
        'target_start_date' => now()->addDays(1)->toDateString(),
        'target_end_date'   => now()->addDays(7)->toDateString(),
    ];
});

/** Create a user with the given role attached to the given department. */
function prodUser(string $role, Department $dept): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->departments()->attach($dept->id, ['is_primary' => true]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

// ── PROD-RBAC: Who can CREATE a WO ────────────────────────────────────────────

describe('WO creation — role access', function () {

    it('production.head can create a WO — PROD-RBAC-001', function () {
        $head = prodUser('head', $this->prodDept);
        $this->actingAs($head, 'sanctum')
            ->postJson('/api/v1/production/orders', $this->woPayload)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    });

    it('production.staff CANNOT create a WO — PROD-RBAC-002', function () {
        $staff = prodUser('staff', $this->prodDept);
        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/production/orders', $this->woPayload)
            ->assertStatus(403);
    });

    it('production.staff can view WOs — PROD-RBAC-003', function () {
        $staff = prodUser('staff', $this->prodDept);
        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/v1/production/orders')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    });

    it('acctg.officer CANNOT access production orders — PROD-RBAC-004', function () {
        // ACCTG dept is not in the production module_access allowed depts
        $officer = prodUser('officer', $this->acctgDept);
        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/production/orders')
            ->assertStatus(403);
    });

});

// ── PROD-DS: Delivery Schedule access ─────────────────────────────────────────

describe('Delivery schedules — access control', function () {

    it('production.head can view delivery schedules — PROD-DS-001', function () {
        $head = prodUser('head', $this->prodDept);
        $this->actingAs($head, 'sanctum')
            ->getJson('/api/v1/production/delivery-schedules')
            ->assertStatus(200);
    });

});

// ── PROD-WF: WO Release → auto-MRQ ────────────────────────────────────────────

describe('WO release workflow', function () {

    it('production.head can release a draft WO — PROD-WF-001', function () {
        $head = prodUser('head', $this->prodDept);

        // Seed sufficient stock so deductBomComponents() does not block release
        StockBalance::updateOrCreate(
            ['item_id' => $this->rawMaterial->id, 'location_id' => $this->warehouse->id],
            ['quantity_on_hand' => 1000],
        );

        $created = $this->actingAs($head, 'sanctum')
            ->postJson('/api/v1/production/orders', $this->woPayload)
            ->assertStatus(201);
        $ulid = $created->json('data.ulid');

        // Ensure admin system user exists for auto-MRQ requested_by_id
        User::firstOrCreate(
            ['email' => 'admin@ogamierp.local'],
            ['name' => 'System Admin', 'password' => bcrypt('password')],
        );

        $this->actingAs($head, 'sanctum')
            ->patchJson("/api/v1/production/orders/{$ulid}/release")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'released');
    });

    it('WO release auto-creates a linked MRQ from BOM components — PROD-WF-002', function () {
        $head = prodUser('head', $this->prodDept);

        // Seed sufficient stock so deductBomComponents() does not block release
        StockBalance::updateOrCreate(
            ['item_id' => $this->rawMaterial->id, 'location_id' => $this->warehouse->id],
            ['quantity_on_hand' => 1000],
        );

        // Ensure admin user exists for auto-MRQ actor
        User::firstOrCreate(
            ['email' => 'admin@ogamierp.local'],
            ['name' => 'System Admin', 'password' => bcrypt('password')],
        );

        $created = $this->actingAs($head, 'sanctum')
            ->postJson('/api/v1/production/orders', $this->woPayload)
            ->assertStatus(201);
        $ulid    = $created->json('data.ulid');
        $orderId = $created->json('data.id');

        $this->actingAs($head, 'sanctum')
            ->patchJson("/api/v1/production/orders/{$ulid}/release")
            ->assertStatus(200);

        // Auto-MRQ should have been created and linked to this production order
        $mrqExists = DB::table('material_requisitions')
            ->where('production_order_id', $orderId)
            ->exists();

        expect($mrqExists)->toBeTrue();
    });

});

// ── PROD-WF: WO Start blocked by unfulfilled MRQ ─────────────────────────────

describe('WO start — MRQ fulfillment gate', function () {

    it('WO start BLOCKED when a linked MRQ is unfulfilled — PROD-WF-003', function () {
        $manager = prodUser('manager', $this->prodDept);

        // Create WO directly in 'released' state
        $order = ProductionOrder::create([
            'product_item_id'   => $this->finishedGood->id,
            'bom_id'            => $this->bom->id,
            'qty_required'      => 5,
            'target_start_date' => now()->addDays(1),
            'target_end_date'   => now()->addDays(7),
            'status'            => 'released',
            'created_by_id'     => $manager->id,
        ]);

        // Link an unfulfilled MRQ to this WO (status = 'submitted' ≠ fulfilled/cancelled/rejected)
        MaterialRequisition::create([
            'production_order_id' => $order->id,
            'department_id'       => $this->prodDept->id,
            'status'              => 'submitted',
            'purpose'             => 'Unfulfilled MRQ blocking WO start',
            'requested_by_id'     => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/production/orders/{$order->ulid}/start")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PROD_MRQ_NOT_FULFILLED');
    });

    it('WO start succeeds when all linked MRQs are fulfilled — PROD-WF-004', function () {
        $manager = prodUser('manager', $this->prodDept);

        $order = ProductionOrder::create([
            'product_item_id'   => $this->finishedGood->id,
            'bom_id'            => $this->bom->id,
            'qty_required'      => 5,
            'target_start_date' => now()->addDays(1),
            'target_end_date'   => now()->addDays(7),
            'status'            => 'released',
            'created_by_id'     => $manager->id,
        ]);

        // All MRQs for this WO are fulfilled
        MaterialRequisition::create([
            'production_order_id' => $order->id,
            'department_id'       => $this->prodDept->id,
            'status'              => 'fulfilled',
            'purpose'             => 'Fulfilled MRQ — should not block start',
            'requested_by_id'     => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/production/orders/{$order->ulid}/start")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');
    });

    it('WO start succeeds when all linked MRQs are cancelled — PROD-WF-004b', function () {
        $manager = prodUser('manager', $this->prodDept);

        $order = ProductionOrder::create([
            'product_item_id'   => $this->finishedGood->id,
            'bom_id'            => $this->bom->id,
            'qty_required'      => 5,
            'target_start_date' => now()->addDays(1),
            'target_end_date'   => now()->addDays(7),
            'status'            => 'released',
            'created_by_id'     => $manager->id,
        ]);

        MaterialRequisition::create([
            'production_order_id' => $order->id,
            'department_id'       => $this->prodDept->id,
            'status'              => 'cancelled',
            'purpose'             => 'Cancelled MRQ — should not block start',
            'requested_by_id'     => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/production/orders/{$order->ulid}/start")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');
    });

});

// ── Unauthenticated ───────────────────────────────────────────────────────────

it('unauthenticated request to production orders returns 401', function () {
    $this->getJson('/api/v1/production/orders')->assertStatus(401);
});
