# 🧪 Ogami ERP - Comprehensive Test Plan for Fixes

**Test Plan Version:** 1.0  
**Created:** 2026-03-15  
**Scope:** All critical and high-priority fixes

---

## 📋 TABLE OF CONTENTS

1. [Test Strategy](#1-test-strategy)
2. [Critical Fixes Test Cases](#2-critical-fixes-test-cases)
3. [High Priority Fixes Test Cases](#3-high-priority-fixes-test-cases)
4. [Medium Priority Fixes Test Cases](#4-medium-priority-fixes-test-cases)
5. [Regression Test Suite](#5-regression-test-suite)
6. [Test Execution Checklist](#6-test-execution-checklist)
7. [Test Data Requirements](#7-test-data-requirements)

---

## 1. TEST STRATEGY

### 1.1 Testing Levels

| Level | Scope | Tools |
|-------|-------|-------|
| **Unit Tests** | Individual methods, value objects | Pest PHP |
| **Feature Tests** | API endpoints, controllers | Pest PHP |
| **Integration Tests** | Cross-module workflows | Pest PHP |
| **E2E Tests** | Full user workflows | Playwright |
| **Manual Tests** | UI/UX, complex scenarios | Manual |

### 1.2 Test Environment

```bash
# Required for testing
DB_CONNECTION=pgsql
DB_DATABASE=ogami_erp_test
CACHE_DRIVER=redis
QUEUE_CONNECTION=sync  # For predictable test execution

# Test accounts needed
- admin@ogamierp.local
- hr.manager@ogamierp.local
- accounting.manager@ogamierp.local
- vp@ogamierp.local
- staff@ogamierp.local
- department.head@ogamierp.local
- ga.officer@ogamierp.local
```

### 1.3 Test Data Setup

```php
// Required seeders for each test run
beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder']);
    $this->artisan('db:seed', ['--class' => 'ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder']);
});
```

---

## 2. CRITICAL FIXES TEST CASES

### 🔴 CRIT-001: SoD Middleware on Approval Routes

#### Test Suite: SoD-Leave-001
**Description:** Verify SoD enforcement on Leave approval endpoints

```php
describe('CRIT-001: SoD Enforcement - Leave Requests', function () {
    
    beforeEach(function () {
        // Setup: Create users with different roles
        $this->submitter = User::factory()->create(); // Staff
        $this->submitter->assignRole('staff');
        
        $this->head = User::factory()->create();
        $this->head->assignRole('head');
        
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        
        $this->gaOfficer = User::factory()->create();
        $this->gaOfficer->assignRole('ga_officer');
        
        // Create leave request
        $this->leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $this->submitter->employee_id,
            'submitted_by' => $this->submitter->id,
            'status' => 'submitted'
        ]);
    });

    it('TC-SOD-LEAVE-001: Submitter cannot approve their own leave request (Head approval)', function () {
        // Arrange: Submit leave as staff
        $this->actingAs($this->submitter, 'sanctum');
        
        // Act: Try to approve as head
        $response = $this->postJson("/api/v1/leave/requests/{$this->leaveRequest->ulid}/head-approve");
        
        // Assert: Should be blocked by SoD
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SOD_VIOLATION'
            ]);
        
        // Verify status unchanged
        $this->assertDatabaseHas('leave_requests', [
            'id' => $this->leaveRequest->id,
            'status' => 'submitted'
        ]);
    });

    it('TC-SOD-LEAVE-002: Different head can approve leave request', function () {
        // Arrange: Login as different head
        $this->actingAs($this->head, 'sanctum');
        
        // Act: Approve
        $response = $this->postJson("/api/v1/leave/requests/{$this->leaveRequest->ulid}/head-approve");
        
        // Assert: Should succeed
        $response->assertStatus(200)
            ->assertJson([
                'data' => ['status' => 'head_approved']
            ]);
    });

    it('TC-SOD-LEAVE-003: Same user cannot approve at multiple levels', function () {
        // Arrange: User is both head and manager
        $combinedUser = User::factory()->create();
        $combinedUser->assignRole(['head', 'manager']);
        
        // First approval as head
        $this->actingAs($combinedUser, 'sanctum');
        $this->postJson("/api/v1/leave/requests/{$this->leaveRequest->ulid}/head-approve")
            ->assertStatus(200);
        
        // Act: Try to approve again as manager
        $response = $this->postJson("/api/v1/leave/requests/{$this->leaveRequest->ulid}/manager-check");
        
        // Assert: Should be blocked by SoD
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SOD_VIOLATION'
            ]);
    });

    it('TC-SOD-LEAVE-004: Audit log records SoD violation attempt', function () {
        // Arrange
        $this->actingAs($this->submitter, 'sanctum');
        
        // Act: Attempt self-approval
        $this->postJson("/api/v1/leave/requests/{$this->leaveRequest->ulid}/head-approve");
        
        // Assert: Audit log created
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->submitter->id,
            'event' => 'sod_violation',
            'auditable_type' => 'sod_check'
        ]);
    });
});
```

#### Test Suite: SoD-Loan-001
**Description:** Verify SoD enforcement on Loan approval endpoints

```php
describe('CRIT-001: SoD Enforcement - Loans', function () {
    
    beforeEach(function () {
        $this->applicant = User::factory()->create();
        $this->applicant->assignRole('staff');
        
        $this->head = User::factory()->create();
        $this->head->assignRole('head');
        
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        
        $this->officer = User::factory()->create();
        $this->officer->assignRole('officer');
        
        $this->vp = User::factory()->create();
        $this->vp->assignRole('vice_president');
        
        // Create loan application
        $this->loan = Loan::factory()->create([
            'employee_id' => $this->applicant->employee_id,
            'requested_by' => $this->applicant->id,
            'status' => 'pending',
            'workflow_version' => 2
        ]);
    });

    it('TC-SOD-LOAN-001: Applicant cannot approve their own loan (Head level)', function () {
        $this->actingAs($this->applicant, 'sanctum');
        
        $response = $this->postJson("/api/v1/loans/{$this->loan->ulid}/head-note");
        
        $response->assertStatus(403)
            ->assertJson(['error_code' => 'SOD_VIOLATION']);
    });

    it('TC-SOD-LOAN-002: Applicant cannot approve at VP level', function () {
        // Progress loan to VP approval stage
        $this->loan->update([
            'status' => 'officer_reviewed',
            'head_noted_by' => $this->head->id,
            'manager_checked_by' => $this->manager->id,
            'officer_reviewed_by' => $this->officer->id
        ]);
        
        $this->actingAs($this->applicant, 'sanctum');
        
        $response = $this->postJson("/api/v1/loans/{$this->loan->ulid}/vp-approve");
        
        $response->assertStatus(403)
            ->assertJson(['error_code' => 'SOD_VIOLATION']);
    });

    it('TC-SOD-LOAN-003: Admin bypasses SoD check', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $this->actingAs($admin, 'sanctum');
        
        $response = $this->postJson("/api/v1/loans/{$this->loan->ulid}/head-note");
        
        $response->assertStatus(200); // Admin can approve
    });
});
```

#### Test Suite: SoD-Procurement-001
**Description:** Verify SoD enforcement on Purchase Request approval

```php
describe('CRIT-001: SoD Enforcement - Purchase Requests', function () {
    
    beforeEach(function () {
        $this->requestor = User::factory()->create();
        $this->requestor->assignRole('staff');
        
        $this->head = User::factory()->create();
        $this->head->assignRole('head');
        
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        
        $this->officer = User::factory()->create();
        $this->officer->assignRole('officer');
        
        $this->pr = PurchaseRequest::factory()->create([
            'requested_by' => $this->requestor->id,
            'status' => 'submitted'
        ]);
    });

    it('TC-SOD-PR-001: Requestor cannot note their own PR', function () {
        $this->actingAs($this->requestor, 'sanctum');
        
        $response = $this->postJson("/api/v1/procurement/purchase-requests/{$this->pr->ulid}/note");
        
        $response->assertStatus(403)
            ->assertJson(['error_code' => 'SOD_VIOLATION']);
    });

    it('TC-SOD-PR-002: Different head can note PR', function () {
        $this->actingAs($this->head, 'sanctum');
        
        $response = $this->postJson("/api/v1/procurement/purchase-requests/{$this->pr->ulid}/note");
        
        $response->assertStatus(200)
            ->assertJson(['data' => ['status' => 'noted']]);
    });

    it('TC-SOD-PR-003: Same user cannot check after noting', function () {
        // First, head notes the PR
        $this->actingAs($this->head, 'sanctum');
        $this->postJson("/api/v1/procurement/purchase-requests/{$this->pr->ulid}/note")
            ->assertStatus(200);
        
        // Give head manager permission too
        $this->head->assignRole('manager');
        
        // Try to check as same user
        $response = $this->postJson("/api/v1/procurement/purchase-requests/{$this->pr->ulid}/check");
        
        $response->assertStatus(403)
            ->assertJson(['error_code' => 'SOD_VIOLATION']);
    });
});
```

---

### 🔴 CRIT-002: Notification System Wiring

#### Test Suite: Notification-AP-001
**Description:** Verify AP notifications are sent

```php
describe('CRIT-002: Notification Wiring - AP', function () {
    
    use Illuminate\Support\Facades\Notification;

    beforeEach(function () {
        Notification::fake();
        
        $this->accountingManager = User::factory()->create();
        $this->accountingManager->assignRole('accounting_manager');
        
        $this->vendor = Vendor::factory()->create();
        
        $this->overdueInvoice = VendorInvoice::factory()->create([
            'vendor_id' => $this->vendor->id,
            'due_date' => now()->subDays(5),
            'status' => 'approved',
            'balance_due' => 50000
        ]);
    });

    it('TC-NOTIF-AP-001: Overdue invoice triggers notification to accounting manager', function () {
        // Arrange
        $job = new SendApDueDateAlertJob();
        
        // Act
        $job->handle();
        
        // Assert
        Notification::assertSentTo(
            $this->accountingManager,
            ApInvoiceOverdueNotification::class,
            function ($notification) {
                return $notification->invoice->id === $this->overdueInvoice->id;
            }
        );
    });

    it('TC-NOTIF-AP-002: Notification contains correct data', function () {
        // Arrange
        $notification = new ApInvoiceOverdueNotification($this->overdueInvoice, 5);
        
        // Act
        $array = $notification->toArray($this->accountingManager);
        
        // Assert
        expect($array)->toHaveKeys(['type', 'title', 'message', 'action_url']);
        expect($array['type'])->toBe('ap.invoice_overdue');
        expect($array['message'])->toContain($this->vendor->name);
        expect($array['message'])->toContain('5 days overdue');
    });

    it('TC-NOTIF-AP-003: Due soon invoices trigger notification', function () {
        // Arrange
        $dueSoonInvoice = VendorInvoice::factory()->create([
            'vendor_id' => $this->vendor->id,
            'due_date' => now()->addDays(3),
            'status' => 'approved'
        ]);
        
        $job = new SendApDueDateAlertJob();
        
        // Act
        $job->handle();
        
        // Assert
        Notification::assertSentTo(
            $this->accountingManager,
            ApInvoiceDueSoonNotification::class
        );
    });

    it('TC-NOTIF-AP-004: Daily digest is sent to accounting managers', function () {
        // Arrange
        $job = new SendApDailyDigestJob();
        
        // Act
        $job->handle();
        
        // Assert
        Notification::assertSentTo(
            $this->accountingManager,
            ApDailyDigestNotification::class
        );
    });
});
```

#### Test Suite: Notification-Accounting-001
**Description:** Verify Accounting notifications are sent

```php
describe('CRIT-002: Notification Wiring - Accounting', function () {
    
    use Illuminate\Support\Facades\Notification;

    beforeEach(function () {
        Notification::fake();
        
        $this->drafter = User::factory()->create();
        $this->drafter->assignRole('accounting_officer');
        
        $this->staleJE = JournalEntry::factory()->create([
            'created_by' => $this->drafter->id,
            'status' => 'draft',
            'created_at' => now()->subDays(35) // Older than stale threshold
        ]);
        
        // Set system setting for stale days
        SystemSetting::set('je_stale_draft_days', 30);
    });

    it('TC-NOTIF-ACC-001: Stale JE triggers notification to drafter', function () {
        // Arrange
        $job = new FlagStaleJournalEntriesJob();
        
        // Act
        $job->handle();
        
        // Assert
        Notification::assertSentTo(
            $this->drafter,
            StaleJournalEntryNotification::class,
            function ($notification) {
                return $notification->journalEntry->id === $this->staleJE->id;
            }
        );
    });

    it('TC-NOTIF-ACC-002: Stale JE status is updated to flagged', function () {
        // Arrange
        $job = new FlagStaleJournalEntriesJob();
        
        // Act
        $job->handle();
        
        // Assert
        $this->assertDatabaseHas('journal_entries', [
            'id' => $this->staleJE->id,
            'status' => 'stale'
        ]);
    });
});
```

---

### 🔴 CRIT-003: Frontend Permission Guards

#### Test Suite: Frontend-Auth-001
**Description:** Verify frontend route guards

```typescript
// tests/e2e/frontend-auth.spec.ts
import { test, expect } from '@playwright/test';

test.describe('CRIT-003: Frontend Permission Guards', () => {
  
  test('TC-FE-AUTH-001: Self-service routes require authentication', async ({ page }) => {
    // Arrange: Not logged in
    await page.context().clearCookies();
    
    // Act: Try to access self-service
    await page.goto('/me/leaves');
    
    // Assert: Redirected to login
    await expect(page).toHaveURL('/login');
  });

  test('TC-FE-AUTH-002: Payslips page requires payslips.view permission', async ({ page }) => {
    // Arrange: Login as user without payslips.view permission
    await loginAs(page, 'admin@ogamierp.local', 'password');
    // Admin doesn't have payslips.view by default
    
    // Act: Try to access payslips
    await page.goto('/self-service/payslips');
    
    // Assert: Redirected to 403
    await expect(page).toHaveURL('/403');
  });

  test('TC-FE-AUTH-003: HR routes require hr.full_access', async ({ page }) => {
    // Arrange: Login as staff
    await loginAs(page, 'staff@ogamierp.local', 'password');
    
    // Act: Try to access HR
    await page.goto('/hr/employees');
    
    // Assert: Redirected to 403
    await expect(page).toHaveURL('/403');
  });

  test('TC-FE-AUTH-004: Action buttons hidden for unauthorized users', async ({ page }) => {
    // Arrange: Login as view-only user
    await loginAs(page, 'executive@ogamierp.local', 'password');
    
    // Act: Go to vendor list
    await page.goto('/accounting/vendors');
    
    // Assert: Edit/Delete buttons not visible
    await expect(page.locator('button:has-text("Edit")')).not.toBeVisible();
    await expect(page.locator('button:has-text("Delete")')).not.toBeVisible();
    // View button should be visible
    await expect(page.locator('button:has-text("View")')).toBeVisible();
  });

  test('TC-FE-AUTH-005: Approval buttons hidden for non-approvers', async ({ page }) => {
    // Arrange: Login as staff
    await loginAs(page, 'staff@ogamierp.local', 'password');
    
    // Create a leave request first
    await createLeaveRequest(page);
    
    // Act: Go to leave list
    await page.goto('/me/leaves');
    
    // Assert: Approve/Reject buttons not visible for own request
    await expect(page.locator('button:has-text("Approve")')).not.toBeVisible();
    await expect(page.locator('button:has-text("Reject")')).not.toBeVisible();
  });
});
```

---

## 3. HIGH PRIORITY FIXES TEST CASES

### 🟠 HIGH-001: Cross-Module Validation

#### Test Suite: CrossModule-PR-Budget-001
**Description:** Verify PR budget enforcement

```php
describe('HIGH-001: Cross-Module Validation - PR Budget', function () {
    
    beforeEach(function () {
        $this->vp = User::factory()->create();
        $this->vp->assignRole('vice_president');
        
        $this->department = Department::factory()->create();
        
        $this->costCenter = CostCenter::factory()->create([
            'department_id' => $this->department->id
        ]);
        
        $this->budget = AnnualBudget::factory()->create([
            'cost_center_id' => $this->costCenter->id,
            'fiscal_year' => now()->year,
            'total_budget' => 100000,
            'utilized_amount' => 80000 // 80% used
        ]);
        
        // PR that would exceed budget
        $this->pr = PurchaseRequest::factory()->create([
            'department_id' => $this->department->id,
            'cost_center_id' => $this->costCenter->id,
            'total_amount' => 30000, // Would exceed budget
            'status' => 'reviewed'
        ]);
    });

    it('TC-CROSS-PR-001: VP cannot approve PR that exceeds budget', function () {
        // Arrange
        $this->actingAs($this->vp, 'sanctum');
        
        // Act: Try to approve
        $response = $this->postJson("/api/v1/procurement/purchase-requests/{$this->pr->ulid}/vp-approve");
        
        // Assert: Blocked
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PR_BUDGET_EXCEEDED'
            ]);
        
        // Status unchanged
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $this->pr->id,
            'status' => 'reviewed'
        ]);
    });

    it('TC-CROSS-PR-002: VP can approve PR within budget', function () {
        // Arrange: Update PR to be within budget
        $this->pr->update(['total_amount' => 15000]);
        
        $this->actingAs($this->vp, 'sanctum');
        
        // Act
        $response = $this->postJson("/api/v1/procurement/purchase-requests/{$this->pr->ulid}/vp-approve");
        
        // Assert: Approved
        $response->assertStatus(200)
            ->assertJson(['data' => ['status' => 'vp_approved']]);
    });

    it('TC-CROSS-PR-003: Budget utilization updated on approval', function () {
        // Arrange
        $this->pr->update(['total_amount' => 10000]);
        $this->actingAs($this->vp, 'sanctum');
        
        // Act
        $this->postJson("/api/v1/procurement/purchase-requests/{$this->pr->ulid}/vp-approve");
        
        // Assert: Budget utilization increased
        $this->assertDatabaseHas('annual_budgets', [
            'id' => $this->budget->id,
            'utilized_amount' => 90000 // 80000 + 10000
        ]);
    });
});
```

#### Test Suite: CrossModule-Production-Stock-001
**Description:** Verify production stock check enforcement

```php
describe('HIGH-001: Cross-Module Validation - Production Stock', function () {
    
    beforeEach(function () {
        $this->productionManager = User::factory()->create();
        $this->productionManager->assignRole('production_manager');
        
        // Create item with insufficient stock
        $this->rawMaterial = ItemMaster::factory()->create([
            'item_code' => 'RAW-001',
            'stock_balance' => 100
        ]);
        
        // Create BOM requiring more stock than available
        $this->bom = BillOfMaterials::factory()->create([
            'bomComponents' => [
                ['item_id' => $this->rawMaterial->id, 'quantity' => 500] // Need 500, have 100
            ]
        ]);
        
        $this->productionOrder = ProductionOrder::factory()->create([
            'bom_id' => $this->bom->id,
            'status' => 'draft',
            'qty_required' => 10
        ]);
    });

    it('TC-CROSS-PROD-001: Cannot release PO with insufficient stock', function () {
        // Arrange
        $this->actingAs($this->productionManager, 'sanctum');
        
        // Act: Try to release
        $response = $this->patchJson("/api/v1/production/orders/{$this->productionOrder->ulid}/release");
        
        // Assert: Blocked
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PROD_INSUFFICIENT_STOCK',
                'message' => fn ($msg) => str_contains($msg, 'RAW-001')
            ]);
        
        // Status unchanged
        $this->assertDatabaseHas('production_orders', [
            'id' => $this->productionOrder->id,
            'status' => 'draft'
        ]);
    });

    it('TC-CROSS-PROD-002: PO releases when stock is sufficient', function () {
        // Arrange: Increase stock
        $this->rawMaterial->update(['stock_balance' => 1000]);
        
        $this->actingAs($this->productionManager, 'sanctum');
        
        // Act
        $response = $this->patchJson("/api/v1/production/orders/{$this->productionOrder->ulid}/release");
        
        // Assert: Released
        $response->assertStatus(200)
            ->assertJson(['data' => ['status' => 'released']]);
    });

    it('TC-CROSS-PROD-003: Stock is reserved on release', function () {
        // Arrange: Sufficient stock
        $this->rawMaterial->update(['stock_balance' => 1000]);
        $this->actingAs($this->productionManager, 'sanctum');
        
        // Act
        $this->patchJson("/api/v1/production/orders/{$this->productionOrder->ulid}/release");
        
        // Assert: Stock reserved
        $this->assertDatabaseHas('stock_reservations', [
            'reference_type' => 'production_orders',
            'reference_id' => $this->productionOrder->id,
            'item_id' => $this->rawMaterial->id,
            'quantity_reserved' => 500
        ]);
    });
});
```

---

### 🟠 HIGH-002: Audit Trail

#### Test Suite: AuditTrail-001
**Description:** Verify audit logging on critical models

```php
describe('HIGH-002: Audit Trail Coverage', function () {
    
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    it('TC-AUDIT-001: Stock adjustment is audited', function () {
        // Arrange
        $item = ItemMaster::factory()->create();
        
        // Act: Create stock adjustment
        $response = $this->postJson('/api/v1/inventory/adjustments', [
            'item_id' => $item->id,
            'quantity' => 100,
            'reason' => 'Initial stock'
        ]);
        
        // Assert: Audit record created
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->user->id,
            'auditable_type' => 'App\Domains\Inventory\Models\StockLedger',
            'event' => 'created'
        ]);
    });

    it('TC-AUDIT-002: Production output is audited', function () {
        // Arrange
        $po = ProductionOrder::factory()->create(['status' => 'released']);
        
        // Act: Log output
        $response = $this->postJson("/api/v1/production/orders/{$po->ulid}/output", [
            'quantity' => 50,
            'output_date' => now()->toDateString()
        ]);
        
        // Assert: Audit record created
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->user->id,
            'auditable_type' => 'App\Domains\Production\Models\ProductionOutputLog',
            'event' => 'created'
        ]);
    });

    it('TC-AUDIT-003: QC inspection result is audited', function () {
        // Arrange
        $inspection = Inspection::factory()->create();
        
        // Act: Submit results
        $response = $this->postJson("/api/v1/qc/inspections/{$inspection->ulid}/results", [
            'results' => [
                ['item_id' => 1, 'measured_value' => 10.5, 'conforms' => true]
            ]
        ]);
        
        // Assert: Audit record created
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->user->id,
            'auditable_type' => 'App\Domains\QC\Models\InspectionResult',
            'event' => 'created'
        ]);
    });

    it('TC-AUDIT-004: Mold shot log is audited', function () {
        // Arrange
        $mold = MoldMaster::factory()->create();
        
        // Act: Log shots
        $response = $this->postJson("/api/v1/mold/masters/{$mold->ulid}/shots", [
            'shots_count' => 1000,
            'production_date' => now()->toDateString()
        ]);
        
        // Assert: Audit record created
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->user->id,
            'auditable_type' => 'App\Domains\Mold\Models\MoldShotLog',
            'event' => 'created'
        ]);
    });
});
```

---

## 4. MEDIUM PRIORITY FIXES TEST CASES

### 🟡 MED-001: API Response Standardization

```php
describe('MED-001: API Response Standardization', function () {
    
    it('TC-API-001: All list endpoints return wrapped response with meta', function () {
        $endpoints = [
            '/api/v1/inventory/items',
            '/api/v1/qc/inspections',
            '/api/v1/maintenance/equipment',
            '/api/v1/mold/masters',
            '/api/v1/delivery/receipts',
        ];
        
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user, 'sanctum');
        
        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            
            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [],
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total'
                    ]
                ]);
        }
    });

    it('TC-API-002: Error responses follow standard format', function () {
        // Trigger a validation error
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        
        $response = $this->postJson('/api/v1/hr/employees', []);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error_code',
                'message',
                'errors' => []
            ])
            ->assertJson([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR'
            ]);
    });
});
```

### 🟡 MED-002: Validation Rules

```php
describe('MED-002: Validation Rules', function () {
    
    it('TC-VAL-001: TIN format is validated', function () {
        $user = User::factory()->create();
        $user->assignRole('hr_manager');
        $this->actingAs($user, 'sanctum');
        
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'tin' => 'invalid-tin',
            // ... other required fields
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tin']);
    });

    it('TC-VAL-002: Invoice due date must be after invoice date', function () {
        $user = User::factory()->create();
        $user->assignRole('accounting_officer');
        $this->actingAs($user, 'sanctum');
        
        $response = $this->postJson('/api/v1/finance/ap/invoices', [
            'vendor_id' => 1,
            'invoice_number' => 'INV-001',
            'invoice_date' => '2026-03-15',
            'due_date' => '2026-03-10', // Before invoice date
            // ... other fields
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    });
});
```

---

## 5. REGRESSION TEST SUITE

### Full Workflow Tests

```php
describe('REGRESSION: End-to-End Workflows', function () {
    
    it('REG-001: Complete hire-to-payslip workflow', function () {
        // This test runs the entire workflow from employee creation to payslip
        // It's a comprehensive regression test for HR + Payroll integration
    });

    it('REG-002: Complete procure-to-pay workflow', function () {
        // PR → PO → GR → AP Invoice → Payment
    });

    it('REG-003: Complete produce-to-ship workflow', function () {
        // Production Order → MRQ → Stock Issue → Production → GR → Delivery → AR Invoice
    });

    it('REG-004: Complete QC failure workflow', function () {
        // Inspection → NCR → CAPA → Close
    });
});
```

---

## 6. TEST EXECUTION CHECKLIST

### Pre-Test Setup
- [ ] Database migrated: `php artisan migrate:fresh --seed`
- [ ] Redis cleared: `redis-cli FLUSHDB`
- [ ] Test environment configured
- [ ] All seeders run successfully

### Critical Fixes Testing
- [ ] TC-SOD-LEAVE-001 to TC-SOD-LEAVE-004
- [ ] TC-SOD-LOAN-001 to TC-SOD-LOAN-003
- [ ] TC-SOD-PR-001 to TC-SOD-PR-003
- [ ] TC-NOTIF-AP-001 to TC-NOTIF-AP-004
- [ ] TC-NOTIF-ACC-001 to TC-NOTIF-ACC-002
- [ ] TC-FE-AUTH-001 to TC-FE-AUTH-005

### High Priority Testing
- [ ] TC-CROSS-PR-001 to TC-CROSS-PR-003
- [ ] TC-CROSS-PROD-001 to TC-CROSS-PROD-003
- [ ] TC-AUDIT-001 to TC-AUDIT-004

### Medium Priority Testing
- [ ] TC-API-001 to TC-API-002
- [ ] TC-VAL-001 to TC-VAL-002

### Regression Testing
- [ ] REG-001: Hire-to-payslip
- [ ] REG-002: Procure-to-pay
- [ ] REG-003: Produce-to-ship
- [ ] REG-004: QC failure workflow

### Post-Test Verification
- [ ] All tests pass
- [ ] No new PHPStan errors
- [ ] Frontend typecheck passes
- [ ] No console errors in browser

---

## 7. TEST DATA REQUIREMENTS

### Required Users
```php
// Create these users for testing
$users = [
    ['email' => 'admin@ogamierp.local', 'roles' => ['admin']],
    ['email' => 'hr.manager@ogamierp.local', 'roles' => ['manager']],
    ['email' => 'accounting.manager@ogamierp.local', 'roles' => ['manager']],
    ['email' => 'vp@ogamierp.local', 'roles' => ['vice_president']],
    ['email' => 'staff@ogamierp.local', 'roles' => ['staff']],
    ['email' => 'dept.head@ogamierp.local', 'roles' => ['head']],
    ['email' => 'ga.officer@ogamierp.local', 'roles' => ['ga_officer']],
    ['email' => 'production.manager@ogamierp.local', 'roles' => ['production_manager']],
    ['email' => 'qc.manager@ogamierp.local', 'roles' => ['qc_manager']],
    ['email' => 'plant.manager@ogamierp.local', 'roles' => ['plant_manager']],
];
```

### Required Reference Data
- [ ] Departments (HR, Accounting, Production, etc.)
- [ ] Positions
- [ ] Salary Grades
- [ ] Chart of Accounts
- [ ] Leave Types
- [ ] Loan Types
- [ ] Item Categories
- [ ] Warehouse Locations

---

## 📝 TEST EXECUTION COMMANDS

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suites
./vendor/bin/pest --filter="CRIT-001"
./vendor/bin/pest --filter="SoD"
./vendor/bin/pest --filter="Notification"

# Run with coverage
./vendor/bin/pest --coverage

# Frontend E2E tests
cd frontend && pnpm e2e

# Frontend typecheck
cd frontend && pnpm typecheck

# Static analysis
./vendor/bin/phpstan analyse
```

---

*End of Test Plan*
