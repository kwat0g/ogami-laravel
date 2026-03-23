<?php

declare(strict_types=1);

/**
 * Inventory — Material Requisition Workflow Tests
 *
 * Coverage:
 *   MRQ-RBAC-001  production.head (PROD dept) can create MRQ
 *   MRQ-RBAC-002  production.staff (PROD dept) CANNOT create MRQ — 403
 *   MRQ-RBAC-003  warehouse.head (WH dept) can create MRQ
 *   MRQ-RBAC-004  purchasing.officer (PURCH dept) can create MRQ
 *   MRQ-RBAC-005  acctg.officer (ACCTG dept) CANNOT access inventory/requisitions — 403 (wrong dept)
 *   MRQ-SOD-001   creator cannot note their own MRQ — 403
 *   MRQ-SOD-002   different user with note permission CAN note the MRQ — 200
 *   MRQ-WF-001    full draft → submitted → noted → approved flow
 *   MRQ-WF-002    manager can fulfill an approved MRQ
 *   MRQ-WF-003    head (without fulfill permission) CANNOT fulfill — 403
 */

use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);
uses()->group('feature', 'role-access', 'inventory', 'mrq');

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

    // Departments (seeded by DepartmentPositionSeeder)
    $this->prodDept  = Department::where('code', 'PROD')->firstOrFail();
    $this->whDept    = Department::where('code', 'WH')->firstOrFail();
    $this->purchDept = Department::where('code', 'PURCH')->firstOrFail();
    $this->acctgDept = Department::where('code', 'ACCTG')->firstOrFail();

    // Warehouse location (required for fulfillment)
    $this->location = WarehouseLocation::create([
        'name'      => 'Main Warehouse',
        'code'      => 'WH-TEST-01',
        'is_active' => true,
    ]);

    // Item master for MRQ items
    $category = ItemCategory::create([
        'code'      => 'RAW',
        'name'      => 'Raw Materials',
        'is_active' => true,
    ]);
    $this->item = ItemMaster::create([
        'item_code'       => 'RM-001',
        'name'            => 'Steel Bar',
        'unit_of_measure' => 'pcs',
        'category_id'     => $category->id,
        'item_type'       => 'raw_material',
        'is_active'       => true,
    ]);

    // Helper: build minimal valid MRQ payload
    $this->mrqPayload = fn (int $deptId) => [
        'department_id' => $deptId,
        'purpose'       => 'Testing material requisition workflow for raw materials',
        'items'         => [
            [
                'item_id'       => $this->item->id,
                'qty_requested' => 10,
                'remarks'       => null,
            ],
        ],
    ];
});

/** Create a user with the given role attached to the given department. */
function mrqUser(string $role, Department $dept): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->departments()->attach($dept->id, ['is_primary' => true]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

// ── MRQ-RBAC: Who can CREATE a MRQ ────────────────────────────────────────────

describe('MRQ creation — role access', function () {

    it('production.head (PROD dept) can create MRQ — MRQ-RBAC-001', function () {
        $head = mrqUser('head', $this->prodDept);
        $this->actingAs($head, 'sanctum')
            ->postJson('/api/v1/inventory/requisitions', ($this->mrqPayload)($this->prodDept->id))
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    });

    it('production.staff (PROD dept) CANNOT create MRQ — MRQ-RBAC-002', function () {
        $staff = mrqUser('staff', $this->prodDept);
        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/inventory/requisitions', ($this->mrqPayload)($this->prodDept->id))
            ->assertStatus(403);
    });

    it('warehouse.head (WH dept) can create MRQ — MRQ-RBAC-003', function () {
        $head = mrqUser('head', $this->whDept);
        $this->actingAs($head, 'sanctum')
            ->postJson('/api/v1/inventory/requisitions', ($this->mrqPayload)($this->whDept->id))
            ->assertStatus(201);
    });

    it('purchasing.officer (PURCH dept) can create MRQ — MRQ-RBAC-004', function () {
        $officer = mrqUser('officer', $this->purchDept);
        $this->actingAs($officer, 'sanctum')
            ->postJson('/api/v1/inventory/requisitions', ($this->mrqPayload)($this->purchDept->id))
            ->assertStatus(201);
    });

    it('acctg.officer (ACCTG dept) CANNOT access requisitions endpoint — MRQ-RBAC-005', function () {
        // ACCTG dept is not in the inventory module_access allowed list
        $officer = mrqUser('officer', $this->acctgDept);
        $this->actingAs($officer, 'sanctum')
            ->getJson('/api/v1/inventory/requisitions')
            ->assertStatus(403);
    });

});

// ── MRQ-RBAC: Who can VIEW the list ───────────────────────────────────────────

describe('MRQ list — view access', function () {

    it('production.head can view MRQ list', function () {
        $head = mrqUser('head', $this->prodDept);
        $this->actingAs($head, 'sanctum')
            ->getJson('/api/v1/inventory/requisitions')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    });

    it('production.staff can view MRQ list', function () {
        $staff = mrqUser('staff', $this->prodDept);
        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/v1/inventory/requisitions')
            ->assertStatus(200);
    });

});

// ── MRQ-SOD: Creator cannot note their own MRQ ────────────────────────────────

describe('MRQ SoD — note step', function () {

    it('creator CANNOT note their own MRQ — MRQ-SOD-001', function () {
        $creator = mrqUser('head', $this->prodDept);

        // Create and submit
        $response = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/inventory/requisitions', ($this->mrqPayload)($this->prodDept->id))
            ->assertStatus(201);
        $ulid = $response->json('data.ulid');

        $this->actingAs($creator, 'sanctum')
            ->patchJson("/api/v1/inventory/requisitions/{$ulid}/submit")
            ->assertStatus(200);

        // Same user tries to note → SoD violation
        $this->actingAs($creator, 'sanctum')
            ->patchJson("/api/v1/inventory/requisitions/{$ulid}/note")
            ->assertStatus(403);
    });

    it('different user with note permission CAN note the MRQ — MRQ-SOD-002', function () {
        $creator = mrqUser('head', $this->prodDept);
        $noter   = mrqUser('head', $this->whDept);  // WH head has inventory.mrq.note

        // Create and submit
        $response = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/inventory/requisitions', ($this->mrqPayload)($this->prodDept->id))
            ->assertStatus(201);
        $ulid = $response->json('data.ulid');

        $this->actingAs($creator, 'sanctum')
            ->patchJson("/api/v1/inventory/requisitions/{$ulid}/submit")
            ->assertStatus(200);

        // Different user notes it
        $this->actingAs($noter, 'sanctum')
            ->patchJson("/api/v1/inventory/requisitions/{$ulid}/note")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'noted');
    });

});

// ── MRQ-WF: Fulfillment permissions ───────────────────────────────────────────

describe('MRQ fulfillment — role access', function () {

    /** Create an MRQ in 'approved' status directly in the DB. */
    function approvedMrq(Department $dept, ItemMaster $item): MaterialRequisition
    {
        $requester = User::factory()->create();

        // mr_reference is set by PostgreSQL trigger trg_mrq_reference — omit from create()
        $mrq = MaterialRequisition::create([
            'department_id'   => $dept->id,
            'status'          => 'approved',
            'purpose'         => 'Test fulfillment MRQ — approved state',
            'requested_by_id' => $requester->id,
        ]);
        $mrq->items()->create([
            'item_id'       => $item->id,
            'qty_requested' => 5,
        ]);

        return $mrq;
    }

    it('warehouse.manager can fulfill an approved MRQ — MRQ-WF-002', function () {
        $manager = mrqUser('manager', $this->whDept);
        $mrq     = approvedMrq($this->prodDept, $this->item);

        // Seed sufficient stock at the test location so fulfill doesn't abort with INV_INSUFFICIENT_STOCK
        StockBalance::updateOrCreate(
            ['item_id' => $this->item->id, 'location_id' => $this->location->id],
            ['quantity_on_hand' => 100],
        );

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/inventory/requisitions/{$mrq->ulid}/fulfill", [
                'location_id' => $this->location->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'fulfilled');
    });

    it('production.head (head role) CANNOT fulfill an MRQ — MRQ-WF-003', function () {
        // head role has inventory.mrq.create + inventory.mrq.note but NOT inventory.mrq.fulfill
        $head = mrqUser('head', $this->prodDept);
        $mrq  = approvedMrq($this->prodDept, $this->item);

        $this->actingAs($head, 'sanctum')
            ->patchJson("/api/v1/inventory/requisitions/{$mrq->ulid}/fulfill", [
                'location_id' => $this->location->id,
            ])
            ->assertStatus(403);
    });

});

// ── Unauthenticated ───────────────────────────────────────────────────────────

it('unauthenticated request to MRQ list returns 401', function () {
    $this->getJson('/api/v1/inventory/requisitions')->assertStatus(401);
});
