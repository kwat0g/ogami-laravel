<?php

declare(strict_types=1);

use App\Domains\AP\Models\Vendor;
use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
uses()->group('feature', 'procurement', 'workflow');

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Seed RBAC v2 infrastructure.
 */
function seedRbac(): void
{
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'ModuleSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'ModulePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DepartmentPositionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DepartmentModuleAssignmentSeeder', '--force' => true]);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

/**
 * Create a vendor + one vendor item for PR creation.
 * Returns [$vendor, $vendorItemId].
 */
function makeVendorFixture(): array
{
    $admin = User::where('email', 'admin@ogamierp.local')->first()
        ?? User::factory()->create(['password_changed_at' => now()]);

    $vendor = Vendor::factory()->create([
        'accreditation_status' => 'accredited',
        'is_active'            => true,
        'payment_terms'        => 'Net 30',
        'created_by'           => $admin->id,
    ]);

    $vendorItemId = DB::table('vendor_items')->insertGetId([
        'ulid'          => Str::ulid()->toBase32(),
        'vendor_id'     => $vendor->id,
        'item_code'     => 'VI-' . uniqid(),
        'item_name'     => 'PP Resin Natural',
        'unit_of_measure' => 'KG',
        'unit_price'    => 15000, // ₱150.00 in centavos
        'is_active'     => true,
        'created_by_id' => $admin->id,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    return [$vendor, $vendorItemId];
}

/**
 * Create a user in a given department with a given role.
 * Bypasses RBAC v2 module checks by using direct permission grants
 * for cross-department operations.
 */
function makeUser(string $role, string $deptCode): User
{
    $dept = Department::where('code', $deptCode)->firstOrFail();
    $user = User::factory()->create(['password_changed_at' => now()]);
    $user->assignRole($role);
    $user->departments()->attach($dept->id, ['is_primary' => true]);
    $user->update(['department_id' => $dept->id]);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    return $user;
}

/**
 * Build a standard PR payload for the HTTP API.
 */
function prPayload(int $deptId, int $vendorId, int $vendorItemId, float $unitCost = 1500.00): array
{
    return [
        'department_id' => $deptId,
        'vendor_id'     => $vendorId,
        'urgency'       => 'normal',
        'justification' => 'We urgently require raw materials for production run Q1-2026. This batch is scheduled per the approved production plan.',
        'notes'         => 'Please expedite delivery.',
        'items'         => [
            [
                'vendor_item_id'      => $vendorItemId,
                'item_description'    => 'PP Resin Natural',
                'unit_of_measure'     => 'KG',
                'quantity'            => 100,
                'estimated_unit_cost' => $unitCost,
                'specifications'      => 'Standard grade',
            ],
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 1 — Normal PR flow (Officer creates → Manager reviews → budget check → VP approves)
//
// RBAC v2 note:
//   - PURCH officer: can create & submit PRs (purchasing module officer perms)
//   - PURCH manager: can review PRs AND do budget check (purchasing module manager perms)
//   - SoD: reviewer ≠ budget verifier — need 2 different PURCH managers
//   - VP: no department, falls back to Spatie, has 'approvals.vp.approve'
// ─────────────────────────────────────────────────────────────────────────────

describe('Scenario 1: Normal PR workflow (Officer → Manager review → budget check → VP → PO → GR)', function () {

    beforeEach(function () {
        seedRbac();

        $this->purchDept = Department::where('code', 'PURCH')->firstOrFail();
        $this->purchDept->update(['annual_budget_centavos' => 10_000_000_00]); // ₱10 million

        [$this->vendor, $this->vendorItemId] = makeVendorFixture();

        // Purchasing Officer — can create & submit PRs
        $this->purchasingOfficer = makeUser('officer', 'PURCH');

        // Purchasing Manager A — will review PR (SoD: cannot also be budget verifier)
        $this->reviewerManager = makeUser('manager', 'PURCH');

        // Purchasing Manager B — will do budget check (SoD: different from reviewer)
        $this->budgetManager = makeUser('manager', 'PURCH');

        // VP — no department; Spatie role has 'approvals.vp.approve'
        $this->vp = User::factory()->create(['password_changed_at' => now()]);
        $this->vp->assignRole('vice_president');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    });

    it('creates a PR draft as Purchasing Officer', function () {
        $response = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.requested_by.id', $this->purchasingOfficer->id);
    });

    it('runs the full normal PR workflow end-to-end', function () {
        // ── Step 1: Officer creates PR draft ────────────────────────────────
        $createResp = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ));
        $createResp->assertStatus(201);
        $prUlid = $createResp->json('data.ulid');
        expect($createResp->json('data.status'))->toBe('draft');

        // ── Step 2: Officer submits PR → goes to pending_review ──────────────
        $submitResp = $this->actingAs($this->purchasingOfficer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit");
        $submitResp->assertOk();
        expect($submitResp->json('data.status'))->toBe('pending_review');

        // ── Step 3: Manager A reviews PR (SoD: ≠ creator) ────────────────────
        $reviewResp = $this->actingAs($this->reviewerManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/review", [
                'comments' => 'Technical review passed. Items verified against spec.',
            ]);
        $reviewResp->assertOk();
        expect($reviewResp->json('data.status'))->toBe('reviewed');
        expect($reviewResp->json('data.reviewed_by.id'))->toBe($this->reviewerManager->id);

        // ── Step 4: Manager B does budget check (SoD: ≠ reviewer Manager A) ──
        $budgetResp = $this->actingAs($this->budgetManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/budget-check", [
                'comments' => 'Budget verified. Sufficient funds available in Q1 allocation.',
            ]);
        $budgetResp->assertOk();
        expect($budgetResp->json('data.status'))->toBe('budget_verified');
        expect($budgetResp->json('data.budget_checked_by.id'))->toBe($this->budgetManager->id);

        // ── Step 5: VP approves PR (SoD: ≠ budget verifier Manager B) ────────
        $vpApproveResp = $this->actingAs($this->vp)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/vp-approve", [
                'comments' => 'Approved. Proceed with procurement.',
            ]);
        $vpApproveResp->assertOk();
        // After VP approval → PR moves to 'approved', then auto-creates PO → PR becomes 'converted_to_po'
        expect($vpApproveResp->json('data.status'))->toBeIn(['approved', 'converted_to_po']);
        expect($vpApproveResp->json('data.vp_approved_by.id'))->toBe($this->vp->id);

        // ── Step 6: Verify PO was auto-created ──────────────────────────────
        $pr = PurchaseRequest::where('ulid', $prUlid)->first();
        expect($pr->converted_to_po_id)->not->toBeNull();

        $po = PurchaseOrder::find($pr->converted_to_po_id);
        expect($po)->not->toBeNull();
        expect($po->status)->toBe('draft');
        expect($po->purchase_request_id)->toBe($pr->id);

        // ── Step 7: Update PO with delivery date + payment terms ─────────────
        $this->actingAs($this->reviewerManager)
            ->patchJson("/api/v1/procurement/purchase-orders/{$po->ulid}", [
                'delivery_date' => now()->addDays(14)->toDateString(),
                'payment_terms' => 'Net 30',
                'vendor_id'     => $this->vendor->id,
            ])
            ->assertOk();

        // ── Step 8: Send PO to vendor (must pass delivery_date in request) ───
        $deliveryDate = now()->addDays(14)->toDateString();
        $sendResp = $this->actingAs($this->reviewerManager)
            ->postJson("/api/v1/procurement/purchase-orders/{$po->ulid}/send", [
                'delivery_date' => $deliveryDate,
            ]);
        $sendResp->assertOk();
        expect($sendResp->json('data.status'))->toBe('sent');

        $po->refresh();

        // ── Step 9: Create Goods Receipt (draft) ─────────────────────────────
        $poItem = $po->items()->first();
        expect($poItem)->not->toBeNull();

        $grResp = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/goods-receipts', [
                'purchase_order_id'    => $po->id,
                'received_date'        => now()->toDateString(),
                'delivery_note_number' => 'DN-2026-001',
                'condition_notes'      => 'All items received in good condition.',
                'items'                => [
                    [
                        'po_item_id'        => $poItem->id,
                        'quantity_received' => 100,
                        'unit_of_measure'   => 'KG',
                        'condition'         => 'good',
                    ],
                ],
            ]);
        $grResp->assertStatus(201);
        $grUlid = $grResp->json('data.ulid');
        expect($grResp->json('data.status'))->toBe('draft');

        // ── Step 10: Confirm GR → triggers 3-way match ───────────────────────
        $confirmResp = $this->actingAs($this->reviewerManager)
            ->postJson("/api/v1/procurement/goods-receipts/{$grUlid}/confirm");
        $confirmResp->assertOk();
        expect($confirmResp->json('data.status'))->toBe('confirmed');
        expect($confirmResp->json('data.three_way_match_passed'))->toBe(true);

        // ── Step 11: Verify PO is now fully_received ──────────────────────────
        $po->refresh();
        expect($po->status)->toBe('fully_received');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 2 — Purchasing Manager creates PR (auto-skips review stage)
// ─────────────────────────────────────────────────────────────────────────────

describe('Scenario 2: Purchasing Manager creates PR (auto-skips review → directly reviewed)', function () {

    beforeEach(function () {
        seedRbac();

        $this->purchDept = Department::where('code', 'PURCH')->firstOrFail();
        $this->purchDept->update(['annual_budget_centavos' => 10_000_000_00]);

        [$this->vendor, $this->vendorItemId] = makeVendorFixture();

        $this->purchasingManager = makeUser('manager', 'PURCH');
        $this->budgetManager     = makeUser('manager', 'PURCH'); // different from creator for SoD

        $this->vp = User::factory()->create(['password_changed_at' => now()]);
        $this->vp->assignRole('vice_president');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    });

    it('auto-advances to reviewed when Purchasing Manager submits their own PR', function () {
        // Manager creates PR
        $createResp = $this->actingAs($this->purchasingManager)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ));
        $createResp->assertStatus(201);
        $prUlid = $createResp->json('data.ulid');
        expect($createResp->json('data.status'))->toBe('draft');

        // Manager submits their own PR → auto-jumps to 'reviewed' (Purchasing Manager privilege)
        $submitResp = $this->actingAs($this->purchasingManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit");
        $submitResp->assertOk();
        expect($submitResp->json('data.status'))->toBe('reviewed');
        expect($submitResp->json('data.reviewed_by.id'))->toBe($this->purchasingManager->id);

        // Different manager does budget check (SoD: ≠ reviewer/creator purchasingManager)
        $budgetResp = $this->actingAs($this->budgetManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/budget-check", [
                'comments' => 'Budget committed.',
            ]);
        $budgetResp->assertOk();
        expect($budgetResp->json('data.status'))->toBe('budget_verified');

        // VP approves
        $vpResp = $this->actingAs($this->vp)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/vp-approve", [
                'comments' => 'Approved.',
            ]);
        $vpResp->assertOk();
        expect($vpResp->json('data.status'))->toBeIn(['approved', 'converted_to_po']);

        // PO was auto-created
        $pr = PurchaseRequest::where('ulid', $prUlid)->first();
        expect($pr->converted_to_po_id)->not->toBeNull();

        $po = PurchaseOrder::find($pr->converted_to_po_id);
        expect($po)->not->toBeNull();
        expect($po->status)->toBe('draft');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 3 — PR returned and re-edited
// ─────────────────────────────────────────────────────────────────────────────

describe('Scenario 3: PR returned by manager → officer re-edits → resubmits → continues', function () {

    beforeEach(function () {
        seedRbac();

        $this->purchDept = Department::where('code', 'PURCH')->firstOrFail();
        $this->purchDept->update(['annual_budget_centavos' => 10_000_000_00]);

        [$this->vendor, $this->vendorItemId] = makeVendorFixture();

        $this->purchasingOfficer = makeUser('officer', 'PURCH');
        $this->reviewerManager   = makeUser('manager', 'PURCH');
        $this->budgetManager     = makeUser('manager', 'PURCH');

        $this->vp = User::factory()->create(['password_changed_at' => now()]);
        $this->vp->assignRole('vice_president');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    });

    it('handles the return → re-edit → resubmit cycle correctly', function () {
        // Step 1: Officer creates and submits PR
        $createResp = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ));
        $createResp->assertStatus(201);
        $prUlid = $createResp->json('data.ulid');

        $this->actingAs($this->purchasingOfficer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review');

        // Step 2: Manager returns PR with comments
        $returnResp = $this->actingAs($this->reviewerManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/return", [
                'reason' => 'Justification is insufficient. Please provide more detail on the intended production use case.',
            ]);
        $returnResp->assertOk();
        expect($returnResp->json('data.status'))->toBe('returned');
        // The PR resource doesn't expose returned_by_id — verify via DB
        $pr = PurchaseRequest::where('ulid', $prUlid)->first();
        expect($pr->returned_by_id)->toBe($this->reviewerManager->id);

        // Step 3: Officer edits the returned PR
        $editResp = $this->actingAs($this->purchasingOfficer)
            ->patchJson("/api/v1/procurement/purchase-requests/{$prUlid}", [
                'department_id' => $this->purchDept->id,
                'justification' => 'We require PP Resin Natural for Q1-2026 Production Run per approved schedule dated 2026-01-15. Production batch number PROD-2026-001. Required by Feb 1.',
                'items'         => [
                    [
                        'vendor_item_id'      => $this->vendorItemId,
                        'item_description'    => 'PP Resin Natural — Grade A',
                        'unit_of_measure'     => 'KG',
                        'quantity'            => 100,
                        'estimated_unit_cost' => 1500.00,
                    ],
                ],
            ]);
        $editResp->assertOk();
        expect($editResp->json('data.status'))->toBe('returned'); // Still returned after edit

        // Step 4: Officer resubmits
        $resubmitResp = $this->actingAs($this->purchasingOfficer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit");
        $resubmitResp->assertOk();
        expect($resubmitResp->json('data.status'))->toBe('pending_review');

        // Step 5: Manager reviews (same manager is ok since PR was returned)
        $reviewResp = $this->actingAs($this->reviewerManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/review", [
                'comments' => 'Updated justification accepted. Technical review passed.',
            ]);
        $reviewResp->assertOk();
        expect($reviewResp->json('data.status'))->toBe('reviewed');

        // Step 6: Different manager does budget check
        $budgetResp = $this->actingAs($this->budgetManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/budget-check");
        $budgetResp->assertOk();
        expect($budgetResp->json('data.status'))->toBe('budget_verified');

        // Step 7: VP approves
        $vpResp = $this->actingAs($this->vp)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/vp-approve");
        $vpResp->assertOk();
        expect($vpResp->json('data.status'))->toBeIn(['approved', 'converted_to_po']);
    });

    it('prevents editing a PR that is in pending_review status', function () {
        // Create and submit to pending_review
        $createResp = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ));
        $prUlid = $createResp->json('data.ulid');

        $this->actingAs($this->purchasingOfficer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit");

        // PR is now pending_review — cannot edit
        $editResp = $this->actingAs($this->purchasingOfficer)
            ->patchJson("/api/v1/procurement/purchase-requests/{$prUlid}", [
                'department_id' => $this->purchDept->id,
                'justification' => 'Updated justification that is long enough to pass validation requirements.',
                'items'         => [
                    [
                        'vendor_item_id'      => $this->vendorItemId,
                        'item_description'    => 'PP Resin Natural',
                        'unit_of_measure'     => 'KG',
                        'quantity'            => 50,
                        'estimated_unit_cost' => 1500.00,
                    ],
                ],
            ]);

        // Should be forbidden since PR is no longer in draft/returned
        $editResp->assertStatus(403);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 4 — Budget exceeded
// ─────────────────────────────────────────────────────────────────────────────

describe('Scenario 4: Budget exceeded — PR creation rejected when dept budget insufficient', function () {

    beforeEach(function () {
        seedRbac();

        $this->purchDept = Department::where('code', 'PURCH')->firstOrFail();
        [$this->vendor, $this->vendorItemId] = makeVendorFixture();
        $this->purchasingOfficer = makeUser('officer', 'PURCH');
    });

    it('rejects PR creation when department budget is exceeded', function () {
        // Set a very small budget: ₱1,000 = 100,000 centavos
        $this->purchDept->update(['annual_budget_centavos' => 100_000]);

        // Try to create a PR for ₱150,000 (100 KG × ₱1,500) — exceeds ₱1,000 budget
        $response = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
                1500.00,
            ));

        // Service throws DomainException PR_BUDGET_EXCEEDED → 422
        $response->assertStatus(422);
        $body = $response->json();
        expect(
            ($body['error_code'] ?? '') === 'PR_BUDGET_EXCEEDED'
            || str_contains(strtolower($body['message'] ?? ''), 'budget')
        )->toBeTrue();
    });

    it('allows PR creation when department has no budget limit (zero = unlimited)', function () {
        $this->purchDept->update(['annual_budget_centavos' => 0]);

        $response = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
                1500.00,
            ));

        $response->assertStatus(201);
        expect($response->json('data.status'))->toBe('draft');
    });

    it('blocks second PR when existing budget-verified PRs consume all available budget', function () {
        // Set budget to exactly ₱150,000 (15,000,000 centavos)
        // First PR of ₱150,000 will consume it all; second PR should fail
        $this->purchDept->update(['annual_budget_centavos' => 15_000_000]);

        // First PR: ₱150,000 (100 KG × ₱1,500 × 100 centavos/peso = 15,000,000 centavos)
        $resp1 = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
                1500.00,
            ));
        $resp1->assertStatus(201);

        // Mark the first PR as budget_verified so it counts in YTD spend
        PurchaseRequest::where('ulid', $resp1->json('data.ulid'))
            ->update(['status' => 'budget_verified']);

        // Second PR: also ₱150,000 — budget now exhausted (0 remaining)
        $resp2 = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
                1500.00,
            ));

        $resp2->assertStatus(422);
        $body = $resp2->json();
        expect(
            ($body['error_code'] ?? '') === 'PR_BUDGET_EXCEEDED'
            || str_contains(strtolower($body['message'] ?? ''), 'budget')
        )->toBeTrue();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 5 — SoD enforcement via HTTP API
// ─────────────────────────────────────────────────────────────────────────────

describe('Scenario 5: SoD rules enforced via HTTP API', function () {

    beforeEach(function () {
        seedRbac();

        $this->purchDept = Department::where('code', 'PURCH')->firstOrFail();
        $this->purchDept->update(['annual_budget_centavos' => 10_000_000_00]);
        [$this->vendor, $this->vendorItemId] = makeVendorFixture();

        $this->purchasingOfficer = makeUser('officer', 'PURCH');
        $this->reviewerManager   = makeUser('manager', 'PURCH');
        $this->budgetManager     = makeUser('manager', 'PURCH');

        $this->vp = User::factory()->create(['password_changed_at' => now()]);
        $this->vp->assignRole('vice_president');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    });

    it('blocks the creator from reviewing their own PR (SoD: reviewer ≠ creator)', function () {
        // Officer creates and submits PR
        $createResp = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ));
        $prUlid = $createResp->json('data.ulid');

        $this->actingAs($this->purchasingOfficer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review');

        // Officer (creator) tries to review their own PR — policy denies at 403
        // because purchasingOfficer role (PURCH module officer) doesn't have procurement.purchase-request.review
        // The service layer would also throw SodViolationException if the policy allowed it
        $reviewResp = $this->actingAs($this->purchasingOfficer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/review", [
                'comments' => 'Self-reviewing my own PR (should fail).',
            ]);

        $reviewResp->assertStatus(403);
    });

    it('blocks reviewer from also doing budget check (SoD: budget verifier ≠ reviewer)', function () {
        // Officer submits a PR
        $createResp = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ));
        $prUlid = $createResp->json('data.ulid');

        $this->actingAs($this->purchasingOfficer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit");

        // reviewerManager reviews the PR
        $this->actingAs($this->reviewerManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/review", [
                'comments' => 'Reviewed.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');

        // SAME reviewerManager tries to also do the budget check — SoD violation
        $budgetResp = $this->actingAs($this->reviewerManager)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/budget-check", [
                'comments' => 'Also verifying budget (should fail - SoD).',
            ]);

        // Service throws DomainException SOD-BC-01 → 403
        $budgetResp->assertStatus(403);
    });

    it('blocks VP from approving a PR that the VP also budget-verified (SoD: VP ≠ budget verifier)', function () {
        // Give VP the budget-check permission directly via Spatie (VP has no dept, uses Spatie)
        $this->vp->givePermissionTo('procurement.purchase-request.budget-check');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create a PR manually in reviewed status (with a different reviewer)
        $pr = PurchaseRequest::create([
            'pr_reference'   => 'PR-' . now()->year . '-SOD-VP01',
            'department_id'  => $this->purchDept->id,
            'requested_by_id' => $this->purchasingOfficer->id,
            'status'         => 'reviewed',
            'total_estimated_cost' => 100000,
            'justification'  => 'SoD VP test',
            'urgency'        => 'normal',
            'reviewed_by_id' => $this->reviewerManager->id,
            'reviewed_at'    => now(),
        ]);

        // VP does budget check
        $budgetResp = $this->actingAs($this->vp)
            ->postJson("/api/v1/procurement/purchase-requests/{$pr->ulid}/budget-check");
        $budgetResp->assertOk();
        expect($budgetResp->json('data.status'))->toBe('budget_verified');

        // VP tries to ALSO approve — SoD violation: VP cannot be same as budget verifier
        $vpApproveResp = $this->actingAs($this->vp)
            ->postJson("/api/v1/procurement/purchase-requests/{$pr->ulid}/vp-approve");
        $vpApproveResp->assertStatus(403);
    });

    it('blocks a user without VP role from calling the vp-approve endpoint', function () {
        // Create a PR in budget_verified status
        $pr = PurchaseRequest::create([
            'pr_reference'          => 'PR-' . now()->year . '-SOD-NOVP',
            'department_id'         => $this->purchDept->id,
            'requested_by_id'       => $this->purchasingOfficer->id,
            'status'                => 'budget_verified',
            'total_estimated_cost'  => 100000,
            'justification'         => 'No-VP SoD test',
            'urgency'               => 'normal',
            'reviewed_by_id'        => $this->reviewerManager->id,
            'budget_checked_by_id'  => $this->budgetManager->id,
        ]);

        // Purchasing Officer (not VP) tries to approve
        $approveResp = $this->actingAs($this->purchasingOfficer)
            ->postJson("/api/v1/procurement/purchase-requests/{$pr->ulid}/vp-approve");
        $approveResp->assertStatus(403);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 6 — PR listing, filtering, and PO/GR list endpoints
// ─────────────────────────────────────────────────────────────────────────────

describe('Scenario 6: PR listing, filtering, and status transitions visible via API', function () {

    beforeEach(function () {
        seedRbac();

        $this->purchDept = Department::where('code', 'PURCH')->firstOrFail();
        $this->purchDept->update(['annual_budget_centavos' => 10_000_000_00]);
        [$this->vendor, $this->vendorItemId] = makeVendorFixture();

        $this->purchasingOfficer = makeUser('officer', 'PURCH');
        $this->purchasingManager = makeUser('manager', 'PURCH');
    });

    it('lists purchase requests with correct API response pagination structure', function () {
        $response = $this->actingAs($this->purchasingManager)
            ->getJson('/api/v1/procurement/purchase-requests');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('filters purchase requests by status=draft', function () {
        // Create a draft PR
        $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ))
            ->assertStatus(201);

        $response = $this->actingAs($this->purchasingManager)
            ->getJson('/api/v1/procurement/purchase-requests?status=draft');

        $response->assertOk();
        $data = $response->json('data');
        expect($data)->not->toBeEmpty();
        foreach ($data as $pr) {
            expect($pr['status'])->toBe('draft');
        }
    });

    it('shows full PR detail via GET by ULID', function () {
        $createResp = $this->actingAs($this->purchasingOfficer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $this->purchDept->id,
                $this->vendor->id,
                $this->vendorItemId,
            ));
        $prUlid = $createResp->json('data.ulid');

        $showResp = $this->actingAs($this->purchasingManager)
            ->getJson("/api/v1/procurement/purchase-requests/{$prUlid}");

        $showResp->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'ulid', 'pr_reference', 'status',
                    'requested_by', 'items',
                ],
            ]);
    });

    it('lists purchase orders with correct pagination structure', function () {
        $response = $this->actingAs($this->purchasingManager)
            ->getJson('/api/v1/procurement/purchase-orders');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('lists goods receipts with correct pagination structure', function () {
        $response = $this->actingAs($this->purchasingManager)
            ->getJson('/api/v1/procurement/goods-receipts');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 7 — Procurement → Inventory: GR confirmation updates stock balance
//
// This verifies the full chain ends in the inventory layer:
//   PR → reviewed → budget_verified → VP approved → PO → GR confirmed
//   → UpdateStockOnThreeWayMatch listener fires
//   → StockBalance row created / incremented
//   → ItemMaster auto-created from vendor catalog if not pre-existing
// ─────────────────────────────────────────────────────────────────────────────

describe('Scenario 7: Procurement → Inventory stock update', function () {

    beforeEach(function () {
        seedRbac();

        $this->purchDept = Department::where('code', 'PURCH')->firstOrFail();
        $this->purchDept->update(['annual_budget_centavos' => 50_000_000_00]); // ₱5M

        [$this->vendor, $this->vendorItemId] = makeVendorFixture();

        // Warehouse receiving location — required for listener to write stock
        $this->location = WarehouseLocation::create([
            'code'      => 'WH-RECV-001',
            'name'      => 'Receiving Area',
            'zone'      => 'A',
            'bin'       => 'R1',
            'is_active' => true,
        ]);

        $this->officer  = makeUser('officer',  'PURCH');
        $this->reviewer = makeUser('manager',  'PURCH');
        $this->budgetMgr = makeUser('manager', 'PURCH');

        $this->vp = User::factory()->create(['password_changed_at' => now()]);
        $this->vp->assignRole('vice_president');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    });

    it('creates a StockBalance entry after GR is confirmed (full PR → GR → inventory chain)', function () {
        $purchDeptId = $this->purchDept->id;

        // Stock starts at zero
        expect(StockBalance::count())->toBe(0);

        // Step 1 — Officer creates + submits PR
        $pr = $this->actingAs($this->officer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $purchDeptId, $this->vendor->id, $this->vendorItemId,
            ));
        $pr->assertStatus(201);
        $prUlid = $pr->json('data.ulid');

        $this->actingAs($this->officer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit")
            ->assertOk();

        // Step 2 — Manager reviews
        $this->actingAs($this->reviewer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/review", [
                'comments' => 'Items verified against production BOM.',
            ])
            ->assertOk();

        // Step 3 — Different manager does budget check
        $this->actingAs($this->budgetMgr)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/budget-check", [
                'comments' => 'Budget available — Q1 allocation confirmed.',
            ])
            ->assertOk();

        // Step 4 — VP approves → PO auto-created
        $vpResp = $this->actingAs($this->vp)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/vp-approve", [
                'comments' => 'Approved.',
            ])
            ->assertOk();

        $prModel = PurchaseRequest::where('ulid', $prUlid)->firstOrFail();
        $po = PurchaseOrder::findOrFail($prModel->converted_to_po_id);

        // Step 5 — Send PO to vendor
        $this->actingAs($this->reviewer)
            ->postJson("/api/v1/procurement/purchase-orders/{$po->ulid}/send", [
                'delivery_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertOk();

        $po->refresh();
        $poItem = $po->items()->firstOrFail();

        // Step 6 — Create + confirm GR (full quantity received: 100 KG)
        $grResp = $this->actingAs($this->officer)
            ->postJson('/api/v1/procurement/goods-receipts', [
                'purchase_order_id'    => $po->id,
                'received_date'        => now()->toDateString(),
                'delivery_note_number' => 'DN-INVENTORY-001',
                'condition_notes'      => 'Received in good condition, no damage.',
                'items'                => [[
                    'po_item_id'        => $poItem->id,
                    'quantity_received' => 100,
                    'unit_of_measure'   => 'KG',
                    'condition'         => 'good',
                ]],
            ])
            ->assertStatus(201);

        $grUlid = $grResp->json('data.ulid');

        $confirmResp = $this->actingAs($this->reviewer)
            ->postJson("/api/v1/procurement/goods-receipts/{$grUlid}/confirm")
            ->assertOk();

        // ── Assert inventory was updated ────────────────────────────────────
        expect($confirmResp->json('data.three_way_match_passed'))->toBe(true);

        // An ItemMaster must exist (auto-created from vendor catalog)
        $itemMaster = ItemMaster::where('name', 'PP Resin Natural')->first();
        expect($itemMaster)->not->toBeNull('ItemMaster should be auto-created from vendor catalog on GR confirmation');

        // A StockBalance row must exist for that item at the receiving location
        $stock = StockBalance::where('item_id', $itemMaster->id)
            ->where('location_id', $this->location->id)
            ->first();

        expect($stock)->not->toBeNull('StockBalance should be created after GR confirmation');
        expect((float) $stock->quantity_on_hand)->toBe(100.0, 'Stock should show exactly 100 KG received');
    });

    it('accumulates stock when two GRs are received for the same item', function () {
        $purchDeptId = $this->purchDept->id;

        // Helper: run a full PR → GR confirmation cycle and return the GR
        $runCycle = function (float $qty) use ($purchDeptId): void {
            $pr = $this->actingAs($this->officer)
                ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                    $purchDeptId, $this->vendor->id, $this->vendorItemId, 1500.00,
                ))
                ->assertStatus(201);
            $prUlid = $pr->json('data.ulid');

            $this->actingAs($this->officer)
                ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit")->assertOk();
            $this->actingAs($this->reviewer)
                ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/review", ['comments' => 'OK'])->assertOk();
            $this->actingAs($this->budgetMgr)
                ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/budget-check", ['comments' => 'OK'])->assertOk();
            $this->actingAs($this->vp)
                ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/vp-approve", ['comments' => 'OK'])->assertOk();

            $prModel = PurchaseRequest::where('ulid', $prUlid)->firstOrFail();
            $po = PurchaseOrder::findOrFail($prModel->converted_to_po_id);

            $this->actingAs($this->reviewer)
                ->postJson("/api/v1/procurement/purchase-orders/{$po->ulid}/send", [
                    'delivery_date' => now()->addDays(7)->toDateString(),
                ])->assertOk();

            $po->refresh();
            $poItem = $po->items()->firstOrFail();

            $grResp = $this->actingAs($this->officer)
                ->postJson('/api/v1/procurement/goods-receipts', [
                    'purchase_order_id'    => $po->id,
                    'received_date'        => now()->toDateString(),
                    'delivery_note_number' => 'DN-' . uniqid(),
                    'condition_notes'      => 'Good condition.',
                    'items'                => [[
                        'po_item_id'        => $poItem->id,
                        'quantity_received' => $qty,
                        'unit_of_measure'   => 'KG',
                        'condition'         => 'good',
                    ]],
                ])
                ->assertStatus(201);

            $this->actingAs($this->reviewer)
                ->postJson("/api/v1/procurement/goods-receipts/{$grResp->json('data.ulid')}/confirm")
                ->assertOk();
        };

        // First delivery: 60 KG
        $runCycle(60);
        $itemMaster = ItemMaster::where('name', 'PP Resin Natural')->firstOrFail();
        $stock = StockBalance::where('item_id', $itemMaster->id)
            ->where('location_id', $this->location->id)
            ->firstOrFail();
        expect((float) $stock->quantity_on_hand)->toBe(60.0, 'After first GR: 60 KG');

        // Second delivery: 40 KG → cumulative should be 100 KG
        $runCycle(40);
        $stock->refresh();
        expect((float) $stock->quantity_on_hand)->toBe(100.0, 'After second GR: cumulative 100 KG');
    });

    it('does NOT update stock if no warehouse location exists', function () {
        // Delete the receiving location to simulate missing warehouse setup
        $this->location->delete();
        expect(WarehouseLocation::count())->toBe(0);

        $purchDeptId = $this->purchDept->id;

        $pr = $this->actingAs($this->officer)
            ->postJson('/api/v1/procurement/purchase-requests', prPayload(
                $purchDeptId, $this->vendor->id, $this->vendorItemId,
            ))->assertStatus(201);
        $prUlid = $pr->json('data.ulid');

        $this->actingAs($this->officer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/submit")->assertOk();
        $this->actingAs($this->reviewer)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/review", ['comments' => 'OK'])->assertOk();
        $this->actingAs($this->budgetMgr)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/budget-check", ['comments' => 'OK'])->assertOk();
        $this->actingAs($this->vp)
            ->postJson("/api/v1/procurement/purchase-requests/{$prUlid}/vp-approve", ['comments' => 'OK'])->assertOk();

        $prModel = PurchaseRequest::where('ulid', $prUlid)->firstOrFail();
        $po = PurchaseOrder::findOrFail($prModel->converted_to_po_id);

        $this->actingAs($this->reviewer)
            ->postJson("/api/v1/procurement/purchase-orders/{$po->ulid}/send", [
                'delivery_date' => now()->addDays(7)->toDateString(),
            ])->assertOk();

        $po->refresh();
        $poItem = $po->items()->firstOrFail();

        $grResp = $this->actingAs($this->officer)
            ->postJson('/api/v1/procurement/goods-receipts', [
                'purchase_order_id'    => $po->id,
                'received_date'        => now()->toDateString(),
                'delivery_note_number' => 'DN-NO-WH-001',
                'condition_notes'      => 'Good.',
                'items'                => [[
                    'po_item_id'        => $poItem->id,
                    'quantity_received' => 100,
                    'unit_of_measure'   => 'KG',
                    'condition'         => 'good',
                ]],
            ])->assertStatus(201);

        // GR still confirms successfully (3-way match passes)
        $this->actingAs($this->reviewer)
            ->postJson("/api/v1/procurement/goods-receipts/{$grResp->json('data.ulid')}/confirm")
            ->assertOk();

        // But stock is NOT written (no warehouse location)
        expect(StockBalance::count())->toBe(0, 'No stock balance should exist without a warehouse location');
    });
});
