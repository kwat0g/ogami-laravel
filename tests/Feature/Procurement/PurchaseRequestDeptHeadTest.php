<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'procurement');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentPositionSeeder::class);
    
    // Use existing departments from DepartmentPositionSeeder
    $this->purchDept = Department::where('code', 'PURCH')->first();
    $this->purchDept->update(['annual_budget_centavos' => 5_000_000_00]); // ₱5M
    
    $this->prodDept = Department::where('code', 'PROD')->first();
    $this->prodDept->update(['annual_budget_centavos' => 1_000_000_00]); // ₱1M
    
    $this->whDept = Department::where('code', 'WH')->first();
    $this->whDept->update(['annual_budget_centavos' => 500_000_00]); // ₱500K
    
    // Purchasing Officer (can create PRs for any department)
    $this->purchasingOfficer = User::factory()->create([
        'department_id' => $this->purchDept->id,
    ]);
    $this->purchasingOfficer->assignRole('officer');
    $this->purchasingOfficer->givePermissionTo('procurement.purchase-request.create');
    
    // Production Department Head (can create PRs for own department only)
    $this->prodHead = User::factory()->create([
        'department_id' => $this->prodDept->id,
    ]);
    $this->prodHead->assignRole('head');
    $this->prodHead->givePermissionTo('procurement.purchase-request.create-dept');
    
    // Warehouse Department Head
    $this->whHead = User::factory()->create([
        'department_id' => $this->whDept->id,
    ]);
    $this->whHead->assignRole('head');
    $this->whHead->givePermissionTo('procurement.purchase-request.create-dept');
    
    // VP (can create PRs for any department)
    $this->vp = User::factory()->create();
    $this->vp->assignRole('vice_president');
    
    // Get a valid user ID for created_by references
    $firstUserId = $this->purchasingOfficer->id;
    
    // Create a vendor for testing
    $vendorId = \DB::table('vendors')->insertGetId([
        'name' => 'Test Vendor',
        'tin' => '123-456-789-000',
        'is_active' => true,
        'accreditation_status' => 'accredited',
        'created_by' => $firstUserId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->vendor = (object) ['id' => $vendorId];
    
    // Create a vendor item for testing
    $vendorItemId = \DB::table('vendor_items')->insertGetId([
        'vendor_id' => $vendorId,
        'item_name' => 'Test Vendor Item',
        'item_code' => 'TVI-001',
        'unit_price' => 50000, // ₱500 in centavos
        'unit_of_measure' => 'pcs',
        'is_active' => true,
        'ulid' => (string) \Illuminate\Support\Str::ulid(),
        'created_by_id' => $firstUserId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->vendorItem = (object) ['id' => $vendorItemId];
});

// ─────────────────────────────────────────────────────────────────────────────
// Department Head PR Creation Tests
// ─────────────────────────────────────────────────────────────────────────────

it('allows department head to create PR for their own department', function () {
    $response = $this->actingAs($this->prodHead)
        ->postJson('/api/v1/procurement/purchase-requests', [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->prodDept->id,
            'urgency' => 'normal',
            'justification' => 'Test justification for production materials',
            'notes' => 'Test notes',
            'items' => [
                [
                    'vendor_item_id' => null,
                    'item_description' => 'Test Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 10,
                    'estimated_unit_cost' => 100, // ₱100
                ],
            ],
        ]);
    
    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.department_id', $this->prodDept->id);
});

it('prevents department head from creating PR for other departments', function () {
    $response = $this->actingAs($this->prodHead)
        ->postJson('/api/v1/procurement/purchase-requests', [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->whDept->id, // Different department
            'urgency' => 'normal',
            'justification' => 'Test justification for warehouse materials',
            'notes' => 'Test notes',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Test Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 10,
                    'estimated_unit_cost' => 100,
                ],
            ],
        ]);
    
    $response->assertForbidden();
});

it('allows purchasing officer to create PR for any department', function () {
    $response = $this->actingAs($this->purchasingOfficer)
        ->postJson('/api/v1/procurement/purchase-requests', [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->prodDept->id, // Any department
            'urgency' => 'normal',
            'justification' => 'Test justification',
            'notes' => 'Test notes',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Test Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 10,
                    'estimated_unit_cost' => 100,
                ],
            ],
        ]);
    
    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.department_id', $this->prodDept->id);
});

it('allows VP to create PR for any department', function () {
    $response = $this->actingAs($this->vp)
        ->postJson('/api/v1/procurement/purchase-requests', [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->whDept->id, // Any department
            'urgency' => 'normal',
            'justification' => 'Test justification',
            'notes' => 'Test notes',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Test Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 10,
                    'estimated_unit_cost' => 100,
                ],
            ],
        ]);
    
    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft');
});

// ─────────────────────────────────────────────────────────────────────────────
// Budget Validation Tests
// ─────────────────────────────────────────────────────────────────────────────

it('blocks PR creation when department budget would be exceeded', function () {
    // Production budget is ₱1M, try to create PR for ₱1.5M
    $response = $this->actingAs($this->prodHead)
        ->postJson('/api/v1/procurement/purchase-requests', [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->prodDept->id,
            'urgency' => 'normal',
            'justification' => 'Expensive purchase exceeding budget',
            'notes' => 'Test notes',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Expensive Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 1000,
                    'estimated_unit_cost' => 1500, // ₱1,500 per unit = ₱1.5M total
                ],
            ],
        ]);
    
    $response->assertUnprocessable()
        ->assertJsonPath('error_code', 'PR_BUDGET_EXCEEDED');
});

it('allows PR creation when within department budget', function () {
    // Production budget is ₱1M, create PR for ₱100K
    $response = $this->actingAs($this->prodHead)
        ->postJson('/api/v1/procurement/purchase-requests', [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->prodDept->id,
            'urgency' => 'normal',
            'justification' => 'Purchase within budget',
            'notes' => 'Test notes',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Test Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 100,
                    'estimated_unit_cost' => 100, // ₱100 per unit = ₱10K total
                ],
            ],
        ]);
    
    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft');
});

it('accumulates PR amounts against department budget', function () {
    // First PR: ₱500K
    $this->actingAs($this->prodHead)
        ->postJson('/api/v1/procurement/purchase-requests', [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->prodDept->id,
            'urgency' => 'normal',
            'justification' => 'First purchase',
            'notes' => 'Test notes',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Test Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 5000,
                    'estimated_unit_cost' => 100, // ₱100 per unit = ₱500K total
                ],
            ],
        ])
        ->assertCreated();
    
    // Approve the first PR so it counts against budget
    $pr = PurchaseRequest::first();
    $pr->update([
        'status' => 'approved',
        'total_estimated_cost' => 500_000_00, // ₱500K in centavos
    ]);
    
    // Second PR: ₱600K (should fail - would exceed ₱1M budget)
    $response = $this->actingAs($this->prodHead)
        ->postJson('/api/v1/procurement/purchase-requests', [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->prodDept->id,
            'urgency' => 'normal',
            'justification' => 'Second purchase',
            'notes' => 'Test notes',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Test Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 6000,
                    'estimated_unit_cost' => 100, // ₱100 per unit = ₱600K total
                ],
            ],
        ]);
    
    $response->assertUnprocessable()
        ->assertJsonPath('error_code', 'PR_BUDGET_EXCEEDED');
});

// ─────────────────────────────────────────────────────────────────────────────
// Budget Check API Tests
// ─────────────────────────────────────────────────────────────────────────────

it('returns budget status via budget-check endpoint', function () {
    $response = $this->actingAs($this->prodHead)
        ->postJson('/api/v1/procurement/budget-check', [
            'department_id' => $this->prodDept->id,
            'items' => [
                [
                    'quantity' => 100,
                    'estimated_unit_cost' => 100, // ₱10K total
                ],
            ],
        ]);
    
    $response->assertOk()
        ->assertJsonStructure([
            'available',
            'budget',
            'ytd_spend',
            'this_pr',
            'remaining',
            'formatted',
        ])
        ->assertJsonPath('available', true)
        ->assertJsonPath('budget', 1_000_000_00); // ₱1M in centavos
});

it('returns budget exceeded via budget-check endpoint', function () {
    $response = $this->actingAs($this->prodHead)
        ->postJson('/api/v1/procurement/budget-check', [
            'department_id' => $this->prodDept->id,
            'items' => [
                [
                    'quantity' => 1000,
                    'estimated_unit_cost' => 1500, // ₱1.5M total
                ],
            ],
        ]);
    
    $response->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('this_pr', 150_000_000); // ₱1.5M in centavos
});

// ─────────────────────────────────────────────────────────────────────────────
// Department Head Update/Cancel Tests
// ─────────────────────────────────────────────────────────────────────────────

it('allows department head to update their own PR', function () {
    $pr = PurchaseRequest::create([
        'pr_reference' => 'PR-2024-01-00001',
        'department_id' => $this->prodDept->id,
        'requested_by_id' => $this->prodHead->id,
        'vendor_id' => $this->vendor->id,
        'urgency' => 'normal',
        'justification' => 'Original justification',
        'status' => 'draft',
        'total_estimated_cost' => 0,
    ]);
    
    $response = $this->actingAs($this->prodHead)
        ->patchJson("/api/v1/procurement/purchase-requests/{$pr->ulid}", [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->prodDept->id,
            'urgency' => 'urgent',
            'justification' => 'Updated justification',
            'notes' => 'Updated notes',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Updated Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 50,
                    'estimated_unit_cost' => 100,
                ],
            ],
        ]);
    
    $response->assertOk()
        ->assertJsonPath('data.urgency', 'urgent');
});

it('prevents department head from updating PR for other departments', function () {
    $pr = PurchaseRequest::create([
        'pr_reference' => 'PR-2024-01-00002',
        'department_id' => $this->whDept->id, // Warehouse department
        'requested_by_id' => $this->whHead->id,
        'vendor_id' => $this->vendor->id,
        'urgency' => 'normal',
        'justification' => 'Original justification',
        'status' => 'draft',
        'total_estimated_cost' => 0,
    ]);
    
    // Production head tries to update warehouse PR
    $response = $this->actingAs($this->prodHead)
        ->patchJson("/api/v1/procurement/purchase-requests/{$pr->ulid}", [
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->whDept->id,
            'urgency' => 'urgent',
            'justification' => 'Updated justification',
            'items' => [
                [
                    'vendor_item_id' => $this->vendorItem->id,
                    'item_description' => 'Updated Item',
                    'unit_of_measure' => 'pcs',
                    'quantity' => 50,
                    'estimated_unit_cost' => 100,
                ],
            ],
        ]);
    
    $response->assertForbidden();
});
