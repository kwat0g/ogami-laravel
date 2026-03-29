<?php

declare(strict_types=1);

/**
 * Full Procurement Workflow Test — All Scenarios
 *
 * Coverage:
 *   PR-001  Happy path: draft → pending_review → reviewed → budget_verified → approved → converted_to_po
 *   PR-002  VP submit shortcut → auto-advance to budget_verified
 *   PR-003  Return for revision at pending_review stage
 *   PR-004  Return for revision at reviewed stage
 *   PR-005  Reject at each stage (3 sub-tests)
 *   PR-006  Cancel a draft PR / cannot cancel past pending_review
 *   PR-007  Cannot submit empty PR
 *   PR-008  Cannot edit non-draft PR
 *   SOD-001 Reviewer cannot be creator
 *   SOD-002 Budget verifier cannot be creator
 *   SOD-003 Budget verifier cannot be reviewer
 *   SOD-004 VP cannot be creator
 *   SOD-005 VP cannot be budget verifier
 *   PO-001  Auto-create PO from approved PR
 *   PO-002  Cannot create duplicate PO from same PR
 *   PO-003  Cannot send PO without delivery date
 *   PO-004  Cannot send PO without payment terms
 *   PO-005  Cannot send PO without vendor
 *   GR-001  Create GR for sent PO
 *   GR-002  Cannot create GR if PO not sent
 *   GR-003  Cannot receive more than pending quantity
 *   GR-004  Rejected items need remarks
 *   GR-005  Confirm GR → stock updated
 *   GR-006  Partial receipt → PO becomes partially_received
 *   GR-007  Full second receipt → PO becomes fully_received
 *   TWM-001 3-way match fails if PR not approved
 *   TWM-002 3-way match fails if PO not receivable
 *   TWM-003 3-way match fails if qty would overflow
 *   STOCK-001 Stock accumulates across multiple GRs
 *   STOCK-002 Auto-creates ItemMaster when PO item has no item_master_id
 *   STOCK-003 Graceful skip when no active warehouse location
 *   HTTP    Endpoint smoke tests
 */

use App\Domains\AP\Models\Vendor;
use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Services\GoodsReceiptService;
use App\Domains\Procurement\Services\PurchaseOrderService;
use App\Domains\Procurement\Services\PurchaseRequestService;
use App\Models\User;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
uses()->group('feature', 'procurement', 'workflow');

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeUser(string $role, array $permissions = []): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    foreach ($permissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user;
}

function prItems(int $qty = 10, float $unitCost = 1000.00): array
{
    return [[
        'item_description' => 'Steel Rod 10mm',
        'unit_of_measure' => 'pcs',
        'quantity' => $qty,
        'estimated_unit_cost' => $unitCost,
        'remarks' => null,
    ]];
}

// ─────────────────────────────────────────────────────────────────────────────
// beforeEach
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ChartOfAccountsSeeder'])->assertExitCode(0);

    // Departments
    $this->dept = Department::factory()->create([
        'code' => 'PROD-TEST-'.Str::random(4),
        'name' => 'Production Test',
        'annual_budget_centavos' => 100_000_000,
        'fiscal_year_start_month' => 1,
        'is_active' => true,
    ]);

    $this->purchDept = Department::firstOrCreate(
        ['code' => 'PURCH'],
        ['name' => 'Purchasing', 'annual_budget_centavos' => 0, 'is_active' => true],
    );

    $this->acctgDept = Department::firstOrCreate(
        ['code' => 'ACCTG'],
        ['name' => 'Accounting', 'annual_budget_centavos' => 0, 'is_active' => true],
    );

    // Actors
    $this->requester = makeUser('head', ['procurement.purchase-request.create-dept']);
    $this->requester->departments()->attach($this->dept->id, ['is_primary' => true]);

    $this->reviewer = makeUser('officer', ['procurement.purchase-request.review']);
    $this->reviewer->departments()->attach($this->purchDept->id, ['is_primary' => true]);

    $this->accountant = makeUser('officer', ['procurement.purchase-request.budget-check']);
    $this->accountant->departments()->attach($this->acctgDept->id, ['is_primary' => true]);

    $this->vp = makeUser('vice_president', ['approvals.vp.approve']);

    $this->purchOfficer = makeUser('officer', [
        'procurement.purchase-request.create',
        'procurement.purchase-order.create',
        'procurement.goods-receipt.create',
    ]);
    $this->purchOfficer->departments()->attach($this->purchDept->id, ['is_primary' => true]);

    // Vendor (accredited)
    $this->vendor = Vendor::factory()->create([
        'created_by' => $this->purchOfficer->id,
    ]);

    // Warehouse receiving location
    $this->location = WarehouseLocation::firstOrCreate(
        ['code' => 'RECV-01'],
        ['name' => 'Receiving Area', 'is_active' => true],
    );

    // Item with category
    $this->category = ItemCategory::factory()->create();
    $this->item = ItemMaster::factory()->create(['category_id' => $this->category->id]);

    // Services
    $this->prService = app(PurchaseRequestService::class);
    $this->poService = app(PurchaseOrderService::class);
    $this->grService = app(GoodsReceiptService::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Shared workflow helper — builds a fully sent PO through the service layer
// ─────────────────────────────────────────────────────────────────────────────

function buildSentPo(
    Vendor $vendor,
    User $purchOfficer,
    Department $dept,
    PurchaseRequestService $prSvc,
    PurchaseOrderService $poSvc,
    User $reviewer,
    User $accountant,
    User $vp,
): PurchaseOrder {
    $requester = makeUser('officer', ['procurement.purchase-request.create']);
    $requester->departments()->attach($dept->id, ['is_primary' => true]);

    $pr = $prSvc->store(
        ['department_id' => $dept->id, 'urgency' => 'normal', 'justification' => 'GR workflow test'],
        prItems(50),
        $requester,
    );
    $pr = $prSvc->submit($pr, $requester);
    $pr = $prSvc->review($pr, $reviewer);
    $pr = $prSvc->budgetCheck($pr, $accountant);
    $pr = $prSvc->vpApprove($pr, $vp);

    $po = PurchaseOrder::where('purchase_request_id', $pr->id)->firstOrFail();
    $po->update([
        'vendor_id' => $vendor->id,
        'delivery_date' => now()->addDays(7)->toDateString(),
        'payment_terms' => 'Net 30',
    ]);

    return $poSvc->send($po);
}

// ═════════════════════════════════════════════════════════════════════════════
// PR WORKFLOW TESTS
// ═════════════════════════════════════════════════════════════════════════════

it('PR-001 full approval chain: draft → pending_review → reviewed → budget_verified → approved → converted_to_po', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need steel rods'],
        prItems(),
        $this->requester,
    );
    expect($pr->status)->toBe('draft');
    expect($pr->requested_by_id)->toBe($this->requester->id);

    $pr = $this->prService->submit($pr, $this->requester);
    expect($pr->status)->toBe('pending_review');

    $pr = $this->prService->review($pr, $this->reviewer, 'Technically valid');
    expect($pr->status)->toBe('reviewed');
    expect($pr->reviewed_by_id)->toBe($this->reviewer->id);

    $pr = $this->prService->budgetCheck($pr, $this->accountant, 'Budget committed');
    expect($pr->status)->toBe('budget_verified');
    expect($pr->budget_checked_by_id)->toBe($this->accountant->id);

    $pr = $this->prService->vpApprove($pr, $this->vp, 'Approved');
    // vpApprove auto-creates the PO and transitions PR to converted_to_po
    expect($pr->status)->toBe('converted_to_po');
    expect($pr->vp_approved_by_id)->toBe($this->vp->id);

    $po = PurchaseOrder::where('purchase_request_id', $pr->id)->first();
    expect($po)->not->toBeNull();
    expect($po->status)->toBe('draft');

    $pr->refresh();
    expect($pr->status)->toBe('converted_to_po');
    expect($pr->converted_to_po_id)->toBe($po->id);
});

it('PR-002 VP submitting own PR auto-advances to budget_verified and cannot self-approve', function () {
    $this->vp->givePermissionTo('procurement.purchase-request.create');

    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'urgent', 'justification' => 'VP emergency purchase'],
        prItems(),
        $this->vp,
    );

    $pr = $this->prService->submit($pr, $this->vp);
    expect($pr->status)->toBe('budget_verified');
    expect($pr->reviewed_by_id)->toBe($this->vp->id);

    // Same VP cannot self-approve (is creator)
    expect(fn () => $this->prService->vpApprove($pr, $this->vp))
        ->toThrow(SodViolationException::class);
});

it('PR-003 purchasing can return PR at pending_review and requester can resubmit', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);

    $pr = $this->prService->returnForRevision($pr, $this->reviewer, 'Missing supplier quotes');
    expect($pr->status)->toBe('returned');
    expect($pr->return_reason)->toBe('Missing supplier quotes');
    expect($pr->returned_by_id)->toBe($this->reviewer->id);

    // Requester updates and resubmits
    $pr = $this->prService->update($pr, ['justification' => 'Updated with quotes'], prItems());
    $pr = $this->prService->submit($pr, $this->requester);
    expect($pr->status)->toBe('pending_review');
});

it('PR-004 accounting can return PR at reviewed stage', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);

    $pr = $this->prService->returnForRevision($pr, $this->accountant, 'Budget code missing');
    expect($pr->status)->toBe('returned');
    expect($pr->return_reason)->toBe('Budget code missing');
});

it('PR-005a reviewer can reject PR at pending_review', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);

    $pr = $this->prService->reject($pr, $this->reviewer, 'Not in approved vendor list', 'pending_review');
    expect($pr->status)->toBe('rejected');
    expect($pr->rejection_reason)->toBe('Not in approved vendor list');
    expect($pr->rejection_stage)->toBe('pending_review');
});

it('PR-005b accounting can reject PR at reviewed stage', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);

    $pr = $this->prService->reject($pr, $this->accountant, 'Over budget', 'reviewed');
    expect($pr->status)->toBe('rejected');
    expect($pr->rejection_stage)->toBe('reviewed');
});

it('PR-005c VP can reject PR at budget_verified', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);
    $pr = $this->prService->budgetCheck($pr, $this->accountant);

    $pr = $this->prService->reject($pr, $this->vp, 'Deferred to next quarter', 'budget_verified');
    expect($pr->status)->toBe('rejected');
    expect($pr->rejection_stage)->toBe('budget_verified');
});

it('PR-006a requester can cancel their own draft PR', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );

    $pr = $this->prService->cancel($pr, $this->requester, 'No longer needed');
    expect($pr->status)->toBe('cancelled');
    expect($pr->cancelled_by_id)->toBe($this->requester->id);
    expect($pr->cancelled_at)->not->toBeNull();
});

it('PR-006b cannot cancel a PR that has been reviewed or further', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);

    expect(fn () => $this->prService->cancel($pr, $this->requester))
        ->toThrow(DomainException::class);
});

it('PR-007 cannot submit PR with no line items', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );
    $pr->items()->delete();

    expect(fn () => $this->prService->submit($pr, $this->requester))
        ->toThrow(DomainException::class);
});

it('PR-008 cannot update a PR that is past draft or returned status', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Need items'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);

    expect(fn () => $this->prService->update($pr, ['justification' => 'changed'], prItems()))
        ->toThrow(DomainException::class);
});

// ═════════════════════════════════════════════════════════════════════════════
// SoD TESTS
// ═════════════════════════════════════════════════════════════════════════════

it('SOD-001 reviewer cannot be the PR creator', function () {
    $this->requester->givePermissionTo('procurement.purchase-request.review');

    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Test'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);

    expect(fn () => $this->prService->review($pr, $this->requester))
        ->toThrow(SodViolationException::class);
});

it('SOD-002 budget verifier cannot be the PR creator', function () {
    $this->requester->givePermissionTo('procurement.purchase-request.budget-check');

    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Test'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);

    expect(fn () => $this->prService->budgetCheck($pr, $this->requester))
        ->toThrow(SodViolationException::class);
});

it('SOD-003 budget verifier cannot be the reviewer', function () {
    $this->reviewer->givePermissionTo('procurement.purchase-request.budget-check');

    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Test'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);

    expect(fn () => $this->prService->budgetCheck($pr, $this->reviewer))
        ->toThrow(DomainException::class);
});

it('SOD-004 VP cannot be the PR creator', function () {
    $this->vp->givePermissionTo('procurement.purchase-request.create');

    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Test'],
        prItems(), $this->vp,
    );
    // Force to budget_verified as if others approved it
    $pr->update([
        'status' => 'budget_verified',
        'reviewed_by_id' => $this->reviewer->id,
        'budget_checked_by_id' => $this->accountant->id,
    ]);

    expect(fn () => $this->prService->vpApprove($pr, $this->vp))
        ->toThrow(SodViolationException::class);
});

it('SOD-005 VP cannot be the budget verifier', function () {
    $this->vp->givePermissionTo('procurement.purchase-request.budget-check');

    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Test'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);
    $pr = $this->prService->budgetCheck($pr, $this->vp);

    expect(fn () => $this->prService->vpApprove($pr, $this->vp))
        ->toThrow(DomainException::class);
});

// ═════════════════════════════════════════════════════════════════════════════
// PURCHASE ORDER TESTS
// ═════════════════════════════════════════════════════════════════════════════

it('PO-001 approved PR auto-creates PO with matching line items', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Test'],
        prItems(20, 500.00), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);
    $pr = $this->prService->budgetCheck($pr, $this->accountant);
    $pr = $this->prService->vpApprove($pr, $this->vp);

    $po = PurchaseOrder::where('purchase_request_id', $pr->id)->firstOrFail();
    expect($po->status)->toBe('draft');
    expect($po->items)->toHaveCount(1);

    $line = $po->items->first();
    expect((float) $line->quantity_ordered)->toBe(20.0);
    expect((float) $line->agreed_unit_cost)->toBe(500.00);
    expect($line->item_description)->toBe('Steel Rod 10mm');
});

it('PO-002 cannot auto-create a second PO from a PR already converted', function () {
    $pr = $this->prService->store(
        ['department_id' => $this->dept->id, 'urgency' => 'normal', 'justification' => 'Test'],
        prItems(), $this->requester,
    );
    $pr = $this->prService->submit($pr, $this->requester);
    $pr = $this->prService->review($pr, $this->reviewer);
    $pr = $this->prService->budgetCheck($pr, $this->accountant);
    $pr = $this->prService->vpApprove($pr, $this->vp);

    $pr->refresh();
    // PR is now converted_to_po — service throws PO_PR_NOT_APPROVED since status is no longer 'approved'
    expect(fn () => $this->poService->createFromApprovedPr($pr))
        ->toThrow(DomainException::class, 'can only be auto-created from an approved');
});

it('PO-003 cannot send PO without a delivery date', function () {
    $pr = PurchaseRequest::create([
        'ulid' => (string) Str::ulid(), 'pr_reference' => 'PR-PO-003',
        'department_id' => $this->dept->id, 'requested_by_id' => $this->requester->id,
        'status' => 'approved', 'urgency' => 'normal', 'justification' => 'Test',
    ]);
    $po = PurchaseOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'PO-TEST-003',
        'purchase_request_id' => $pr->id,
        'vendor_id' => $this->vendor->id,
        'status' => 'draft',
        'po_date' => now()->toDateString(),
        'delivery_date' => null,
        'payment_terms' => 'Net 30',
        'total_po_amount' => 0,
        'created_by_id' => $this->purchOfficer->id,
    ]);

    expect(fn () => $this->poService->send($po))
        ->toThrow(DomainException::class, 'Delivery Date is required');
});

it('PO-004 cannot send PO without payment terms', function () {
    $pr = PurchaseRequest::create([
        'ulid' => (string) Str::ulid(), 'pr_reference' => 'PR-PO-004',
        'department_id' => $this->dept->id, 'requested_by_id' => $this->requester->id,
        'status' => 'approved', 'urgency' => 'normal', 'justification' => 'Test',
    ]);
    $po = PurchaseOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'PO-TEST-004',
        'purchase_request_id' => $pr->id,
        'vendor_id' => $this->vendor->id,
        'status' => 'draft',
        'po_date' => now()->toDateString(),
        'delivery_date' => now()->addWeek()->toDateString(),
        'payment_terms' => null,
        'total_po_amount' => 0,
        'created_by_id' => $this->purchOfficer->id,
    ]);

    expect(fn () => $this->poService->send($po))
        ->toThrow(DomainException::class, 'Payment Terms are required');
});

it('PO-005 cannot send PO without a vendor', function () {
    $pr = PurchaseRequest::create([
        'ulid' => (string) Str::ulid(), 'pr_reference' => 'PR-PO-005',
        'department_id' => $this->dept->id, 'requested_by_id' => $this->requester->id,
        'status' => 'approved', 'urgency' => 'normal', 'justification' => 'Test',
    ]);
    $po = PurchaseOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'PO-TEST-005',
        'purchase_request_id' => $pr->id,
        'vendor_id' => null,
        'status' => 'draft',
        'po_date' => now()->toDateString(),
        'delivery_date' => now()->addWeek()->toDateString(),
        'payment_terms' => 'Net 30',
        'total_po_amount' => 0,
        'created_by_id' => $this->purchOfficer->id,
    ]);

    expect(fn () => $this->poService->send($po))
        ->toThrow(DomainException::class, 'Vendor is required');
});

// ═════════════════════════════════════════════════════════════════════════════
// GOODS RECEIPT TESTS
// ═════════════════════════════════════════════════════════════════════════════

it('GR-001 warehouse staff can create a GR draft for a sent PO', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();

    $gr = $this->grService->store(
        $po,
        ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 30, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );

    expect($gr->status)->toBe('draft');
    expect($gr->purchase_order_id)->toBe($po->id);
    expect($gr->items)->toHaveCount(1);
    expect((float) $gr->items->first()->quantity_received)->toBe(30.0);
});

it('GR-002 cannot create GR when PO is still in draft status', function () {
    $pr = PurchaseRequest::create([
        'ulid' => (string) Str::ulid(), 'pr_reference' => 'PR-GR-002',
        'department_id' => $this->dept->id, 'requested_by_id' => $this->requester->id,
        'status' => 'approved', 'urgency' => 'normal', 'justification' => 'Test',
    ]);
    $po = PurchaseOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'PO-GR-002',
        'purchase_request_id' => $pr->id,
        'vendor_id' => $this->vendor->id,
        'status' => 'draft',
        'po_date' => now()->toDateString(),
        'delivery_date' => now()->addWeek()->toDateString(),
        'payment_terms' => 'Net 30',
        'total_po_amount' => 0,
        'created_by_id' => $this->purchOfficer->id,
    ]);
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'item_description' => 'Test Item',
        'unit_of_measure' => 'pcs',
        'quantity_ordered' => 50,
        'quantity_received' => 0,
        'agreed_unit_cost' => 100,
        'line_order' => 1,
    ]);

    expect(fn () => $this->grService->store(
        $po,
        ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 10, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    ))->toThrow(DomainException::class, "PO is in status 'draft'");
});

it('GR-003 cannot receive more than the pending quantity', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();

    expect(fn () => $this->grService->store(
        $po,
        ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => (float) $poItem->quantity_ordered + 1, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    ))->toThrow(DomainException::class, 'exceeds pending quantity');
});

it('GR-004 confirming GR with rejected items and no remarks throws error', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();

    $gr = $this->grService->store(
        $po,
        ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 5, 'unit_of_measure' => 'pcs', 'condition' => 'rejected', 'remarks' => null]],
        $this->purchOfficer,
    );

    // QC flow: submit for QC then mark passed before attempting confirm
    $gr = $this->grService->submitForQc($gr->load('items'), $this->purchOfficer);
    $gr = $this->grService->markQcPassed($gr, $this->purchOfficer);

    expect(fn () => $this->grService->confirm($gr->load('items'), $this->purchOfficer))
        ->toThrow(DomainException::class, 'remarks explanation');
});

it('GR-005 confirming GR triggers 3-way match and increments stock balance', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();
    $poItem->update(['item_master_id' => $this->item->id]);

    $gr = $this->grService->store(
        $po,
        ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 50, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );
    $gr = \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr, $this->purchOfficer, $this->grService);

    expect($gr->status)->toBe('confirmed');
    expect($gr->three_way_match_passed)->toBeTrue();

    $po->refresh();
    expect($po->status)->toBe('fully_received');

    $stock = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->location->id)
        ->first();

    expect($stock)->not->toBeNull();
    expect((float) $stock->quantity_on_hand)->toBe(50.0);
});

it('GR-006 partial GR confirmation sets PO to partially_received', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();
    $poItem->update(['item_master_id' => $this->item->id]);

    $gr = $this->grService->store(
        $po, ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 20, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );
    \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr, $this->purchOfficer, $this->grService);

    $po->refresh();
    expect($po->status)->toBe('partially_received');

    $stock = StockBalance::where('item_id', $this->item->id)->first();
    expect((float) $stock->quantity_on_hand)->toBe(20.0);
});

it('GR-007 second GR completing remaining qty sets PO to fully_received', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();
    $poItem->update(['item_master_id' => $this->item->id]);

    // GR 1: 20 of 50
    $gr1 = $this->grService->store(
        $po, ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 20, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );
    \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr1, $this->purchOfficer, $this->grService);

    // GR 2: remaining 30
    $gr2 = $this->grService->store(
        $po, ['received_date' => now()->addDay()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 30, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );
    \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr2, $this->purchOfficer, $this->grService);

    $po->refresh();
    expect($po->status)->toBe('fully_received');

    $stock = StockBalance::where('item_id', $this->item->id)->first();
    expect((float) $stock->quantity_on_hand)->toBe(50.0);
});

// ═════════════════════════════════════════════════════════════════════════════
// THREE-WAY MATCH EDGE CASES
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Build a raw GR + GR item directly (bypassing service) so we can set
 * states that the service would prevent.
 */
function rawGrWithItem(PurchaseOrder $po, PurchaseOrderItem $poItem, int $qty = 10): GoodsReceipt
{
    $gr = GoodsReceipt::create([
        'gr_reference' => 'GR-RAW-'.Str::random(6),
        'purchase_order_id' => $po->id,
        'received_by_id' => $po->created_by_id,
        'received_date' => now()->toDateString(),
        'status' => 'draft',
        'three_way_match_passed' => false,
        'ap_invoice_created' => false,
        'ulid' => (string) Str::ulid(),
    ]);

    GoodsReceiptItem::create([
        'goods_receipt_id' => $gr->id,
        'po_item_id' => $poItem->id,
        'item_master_id' => $poItem->item_master_id,
        'quantity_received' => $qty,
        'unit_of_measure' => 'pcs',
        'condition' => 'good',
    ]);

    return $gr;
}

it('TWM-001 three-way match fails when linked PR is not approved', function () {
    $pr = PurchaseRequest::create([
        'ulid' => (string) Str::ulid(),
        'pr_reference' => 'PR-TWM-001',
        'department_id' => $this->dept->id,
        'requested_by_id' => $this->requester->id,
        'status' => 'draft',
        'urgency' => 'normal',
        'justification' => 'Test',
    ]);
    $po = PurchaseOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'PO-TWM-001',
        'purchase_request_id' => $pr->id,
        'vendor_id' => $this->vendor->id,
        'status' => 'sent',
        'po_date' => now()->toDateString(),
        'delivery_date' => now()->addWeek()->toDateString(),
        'payment_terms' => 'Net 30',
        'total_po_amount' => 0,
        'created_by_id' => $this->purchOfficer->id,
    ]);
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'item_master_id' => $this->item->id,
        'item_description' => $this->item->name,
        'unit_of_measure' => 'pcs',
        'quantity_ordered' => 10,
        'quantity_received' => 0,
        'agreed_unit_cost' => 100,
        'line_order' => 1,
    ]);

    $gr = rawGrWithItem($po, $poItem, 10);

    expect(fn () => \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr, $this->purchOfficer, $this->grService))
        ->toThrow(DomainException::class, 'not in an approved status');
});

it('TWM-002 three-way match fails when PO is not in a receivable status', function () {
    $pr = PurchaseRequest::create([
        'ulid' => (string) Str::ulid(),
        'pr_reference' => 'PR-TWM-002',
        'department_id' => $this->dept->id,
        'requested_by_id' => $this->requester->id,
        'status' => 'approved',
        'urgency' => 'normal',
        'justification' => 'Test',
    ]);
    $po = PurchaseOrder::create([
        'ulid' => (string) Str::ulid(),
        'po_reference' => 'PO-TWM-002',
        'purchase_request_id' => $pr->id,
        'vendor_id' => $this->vendor->id,
        'status' => 'draft',
        'po_date' => now()->toDateString(),
        'delivery_date' => now()->addWeek()->toDateString(),
        'payment_terms' => 'Net 30',
        'total_po_amount' => 0,
        'created_by_id' => $this->purchOfficer->id,
    ]);
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'item_master_id' => $this->item->id,
        'item_description' => $this->item->name,
        'unit_of_measure' => 'pcs',
        'quantity_ordered' => 10,
        'quantity_received' => 0,
        'agreed_unit_cost' => 100,
        'line_order' => 1,
    ]);

    $gr = rawGrWithItem($po, $poItem, 10);

    expect(fn () => \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr, $this->purchOfficer, $this->grService))
        ->toThrow(DomainException::class, 'not in a receivable status');
});

it('TWM-003 three-way match fails when GR quantity would overflow ordered quantity', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();
    $poItem->update(['item_master_id' => $this->item->id]);

    // Create GR with qty=999 bypassing service guard
    $gr = rawGrWithItem($po, $poItem, 999);

    expect(fn () => \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr, $this->purchOfficer, $this->grService))
        ->toThrow(DomainException::class, 'would exceed ordered quantity');
});

// ═════════════════════════════════════════════════════════════════════════════
// STOCK UPDATE TESTS
// ═════════════════════════════════════════════════════════════════════════════

it('STOCK-001 stock balance accumulates correctly across multiple GR confirmations', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();
    $poItem->update(['item_master_id' => $this->item->id]);

    $gr1 = $this->grService->store(
        $po, ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 15, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );
    \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr1, $this->purchOfficer, $this->grService);

    $stock = StockBalance::where('item_id', $this->item->id)->firstOrFail();
    expect((float) $stock->quantity_on_hand)->toBe(15.0);

    $gr2 = $this->grService->store(
        $po, ['received_date' => now()->addDay()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 35, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );
    \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr2, $this->purchOfficer, $this->grService);

    $stock->refresh();
    expect((float) $stock->quantity_on_hand)->toBe(50.0);
});

it('STOCK-002 auto-creates ItemMaster and updates stock when PO item has no item_master_id', function () {
    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();
    $poItem->update(['item_master_id' => null, 'item_description' => 'Unique Uncatalogued Widget XYZ-'.Str::random(6)]);

    $initialCount = ItemMaster::count();

    $gr = $this->grService->store(
        $po, ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 10, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );
    \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr, $this->purchOfficer, $this->grService);

    expect(ItemMaster::count())->toBe($initialCount + 1);

    $autoItem = ItemMaster::where('name', $poItem->item_description)->first();
    expect($autoItem)->not->toBeNull();
    expect($autoItem->category_id)->not->toBeNull();

    $stock = StockBalance::where('item_id', $autoItem->id)->first();
    expect($stock)->not->toBeNull();
    expect((float) $stock->quantity_on_hand)->toBe(10.0);

    $poItem->refresh();
    expect($poItem->item_master_id)->toBe($autoItem->id);
});

it('STOCK-003 stock update is skipped gracefully when no active warehouse location exists', function () {
    WarehouseLocation::where('is_active', true)->update(['is_active' => false]);

    $po = buildSentPo($this->vendor, $this->purchOfficer, $this->dept, $this->prService, $this->poService, $this->reviewer, $this->accountant, $this->vp);
    $poItem = $po->items()->first();
    $poItem->update(['item_master_id' => $this->item->id]);

    $gr = $this->grService->store(
        $po, ['received_date' => now()->toDateString()],
        [['po_item_id' => $poItem->id, 'quantity_received' => 10, 'unit_of_measure' => 'pcs']],
        $this->purchOfficer,
    );

    // Must not throw — listener warns and skips
    $gr = \Tests\Helpers\GrQcTestHelper::submitQcAndConfirm($gr, $this->purchOfficer, $this->grService);

    expect($gr->status)->toBe('confirmed');
    expect($gr->three_way_match_passed)->toBeTrue();
    expect(StockBalance::where('item_id', $this->item->id)->exists())->toBeFalse();
});

// ═════════════════════════════════════════════════════════════════════════════
// HTTP ENDPOINT SMOKE TESTS
// ═════════════════════════════════════════════════════════════════════════════

it('HTTP unauthenticated users get 401 on all procurement endpoints', function () {
    $this->getJson('/api/v1/procurement/purchase-requests')->assertUnauthorized();
    $this->getJson('/api/v1/procurement/purchase-orders')->assertUnauthorized();
    $this->getJson('/api/v1/procurement/goods-receipts')->assertUnauthorized();
});

it('HTTP admin can list purchase-requests', function () {
    // admin role is in ModuleAccessMiddleware::BYPASS_ROLES and has no department,
    // so Gate::after passes through Spatie's result (true) — no module seeding needed.
    $user = makeUser('admin');

    $this->actingAs($user)
        ->getJson('/api/v1/procurement/purchase-requests')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('HTTP admin can list purchase-orders', function () {
    $user = makeUser('admin');

    $this->actingAs($user)
        ->getJson('/api/v1/procurement/purchase-orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('HTTP admin can list goods-receipts', function () {
    $user = makeUser('admin');

    $this->actingAs($user)
        ->getJson('/api/v1/procurement/goods-receipts')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
