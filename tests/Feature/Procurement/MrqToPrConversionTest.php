<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Models\MaterialRequisitionItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);
uses()->group('feature', 'procurement', 'mrq-conversion');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Create Purchasing department
    $this->purchasingDept = Department::create([
        'code' => 'PURCH',
        'name' => 'Procurement & Purchasing',
    ]);

    // Create Production department (for MRQ requesting)
    $this->productionDept = Department::create([
        'code' => 'PROD',
        'name' => 'Production',
    ]);

    // Create Purchasing Officer who can convert MRQ to PR
    $this->purchasingOfficer = User::factory()->create([
        'department_id' => $this->purchasingDept->id,
    ]);
    $this->purchasingOfficer->assignRole('officer');
    $this->purchasingOfficer->givePermissionTo('procurement.purchase-request.create');
    $this->purchasingOfficer->departments()->attach($this->purchasingDept->id, ['is_primary' => true]);

    // Clear permission cache and reload user with roles and permissions
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->purchasingOfficer->load(['roles', 'roles.permissions']);

    // Create Production Head who can create/approve MRQs
    $this->productionHead = User::factory()->create([
        'department_id' => $this->productionDept->id,
    ]);
    $this->productionHead->assignRole('head');
    $this->productionHead->departments()->attach($this->productionDept->id, ['is_primary' => true]);

    // Create item category and item
    $category = ItemCategory::create([
        'code' => 'RM',
        'name' => 'Raw Materials',
        'is_active' => true,
    ]);

    $this->item = ItemMaster::create([
        'item_code' => 'RM-TEST-001',
        'name' => 'Test Raw Material',
        'category_id' => $category->id,
        'type' => 'raw_material',
        'unit_of_measure' => 'kg',
        'standard_price_centavos' => 12_500,
        'is_active' => true,
    ]);
});

it('converts approved material requisition to purchase request', function () {
    // Create an approved MRQ
    $mrq = MaterialRequisition::create([
        'requested_by_id' => $this->productionHead->id,
        'department_id' => $this->productionDept->id,
        'purpose' => 'Test production run',
        'status' => 'approved',
    ]);

    MaterialRequisitionItem::create([
        'material_requisition_id' => $mrq->id,
        'item_id' => $this->item->id,
        'qty_requested' => 100,
        'line_order' => 0,
    ]);

    expect($this->purchasingOfficer->hasAnyRole(['super_admin', 'executive', 'vice_president']))->toBeFalse();
    expect($this->purchasingOfficer->departments()->where('code', 'PURCH')->exists())->toBeTrue();

    // Convert MRQ to PR as Purchasing Officer
    $response = $this->actingAs($this->purchasingOfficer)
        ->postJson("/api/v1/procurement/purchase-requests/from-mrq/{$mrq->ulid}", [
            'justification' => 'Stock insufficient - need to purchase from vendor',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.material_requisition_id', $mrq->id)
        ->assertJsonPath('data.source_mrq.id', $mrq->id);

    // Verify MRQ is marked as converted
    $mrq->refresh();
    expect($mrq->converted_to_pr)->toBeTrue();
    expect($mrq->converted_pr_id)->not->toBeNull();
    expect($mrq->status)->toBe('converted_to_pr');
});

it('prevents converting non-approved mrq', function () {
    // Create a submitted (not approved) MRQ
    $mrq = MaterialRequisition::create([
        'requested_by_id' => $this->productionHead->id,
        'department_id' => $this->productionDept->id,
        'purpose' => 'Test production run',
        'status' => 'submitted',
    ]);

    MaterialRequisitionItem::create([
        'material_requisition_id' => $mrq->id,
        'item_id' => $this->item->id,
        'qty_requested' => 100,
        'line_order' => 0,
    ]);

    $response = $this->actingAs($this->purchasingOfficer)
        ->postJson("/api/v1/procurement/purchase-requests/from-mrq/{$mrq->ulid}", []);

    $response->assertForbidden();
});

it('prevents converting already converted mrq', function () {
    // Create an already converted MRQ
    $mrq = MaterialRequisition::create([
        'requested_by_id' => $this->productionHead->id,
        'department_id' => $this->productionDept->id,
        'purpose' => 'Test production run',
        'status' => 'converted_to_pr',
        'converted_to_pr' => true,
        'converted_pr_id' => null, // No PR yet
    ]);

    MaterialRequisitionItem::create([
        'material_requisition_id' => $mrq->id,
        'item_id' => $this->item->id,
        'qty_requested' => 100,
        'line_order' => 0,
    ]);

    $response = $this->actingAs($this->purchasingOfficer)
        ->postJson("/api/v1/procurement/purchase-requests/from-mrq/{$mrq->ulid}", []);

    $response->assertForbidden();
});

it('prevents non-purchasing user from converting mrq', function () {
    // Create user in Production department (not Purchasing)
    $productionUser = User::factory()->create([
        'department_id' => $this->productionDept->id,
    ]);
    $productionUser->assignRole('officer');
    $productionUser->departments()->attach($this->productionDept->id, ['is_primary' => true]);

    // Create an approved MRQ
    $mrq = MaterialRequisition::create([
        'requested_by_id' => $this->productionHead->id,
        'department_id' => $this->productionDept->id,
        'purpose' => 'Test production run',
        'status' => 'approved',
    ]);

    MaterialRequisitionItem::create([
        'material_requisition_id' => $mrq->id,
        'item_id' => $this->item->id,
        'qty_requested' => 100,
        'line_order' => 0,
    ]);

    $response = $this->actingAs($productionUser)
        ->postJson("/api/v1/procurement/purchase-requests/from-mrq/{$mrq->ulid}", []);

    $response->assertForbidden();
});

it('allows vp to convert mrq from any department', function () {
    // Create VP in Executive department (not Purchasing)
    $executiveDept = Department::create([
        'code' => 'EXEC',
        'name' => 'Executive',
    ]);

    $vp = User::factory()->create([
        'department_id' => $executiveDept->id,
    ]);
    $vp->assignRole('vice_president');
    $vp->departments()->attach($executiveDept->id, ['is_primary' => true]);

    // Create an approved MRQ
    $mrq = MaterialRequisition::create([
        'requested_by_id' => $this->productionHead->id,
        'department_id' => $this->productionDept->id,
        'purpose' => 'Test production run',
        'status' => 'approved',
    ]);

    MaterialRequisitionItem::create([
        'material_requisition_id' => $mrq->id,
        'item_id' => $this->item->id,
        'qty_requested' => 100,
        'line_order' => 0,
    ]);

    $response = $this->actingAs($vp)
        ->postJson("/api/v1/procurement/purchase-requests/from-mrq/{$mrq->ulid}", []);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft');
});
