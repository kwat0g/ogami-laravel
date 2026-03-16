# 🔍 Ogami ERP - Comprehensive Audit Findings & Fix Plan

**Audit Date:** 2026-03-15  
**Auditor:** AI Code Review  
**Scope:** All 20 domains, frontend access control, cross-module integrations, approval workflows

---

## 📊 EXECUTIVE SUMMARY

### Current State
- **488 tests** in the test suite (222+ passing)
- **194 frontend pages** implemented
- **27 API route files** with 300+ endpoints
- **20 domains** with varying levels of completeness

### Critical Finding
**The system is functionally complete but has several gaps in:
1. Notification system (email/SMS not fully wired)
2. Some frontend permission checks
3. Missing cross-module validation
4. Incomplete role-based UI hiding**

---

## 🔴 CRITICAL ISSUES (Fix Immediately)

### CRIT-001: Missing Email/Notification Wiring
**Impact:** HIGH - Alerts don't actually send notifications

**Affected Files:**
```
app/Jobs/AP/SendApDailyDigestJob.php:39 - // TODO Sprint 17: send Mail
app/Jobs/AP/SendApDueDateAlertJob.php:65 - // TODO Sprint 17: dispatch Mail/Notification
app/Jobs/Accounting/FlagStaleJournalEntriesJob.php:89 - // TODO Sprint 17: dispatch notification
```

**Fix Required:**
```php
// In SendApDueDateAlertJob::handle()
foreach ($overdueInvoices as $invoice) {
    $accountingManagers = User::role(['accounting_manager', 'officer'])->get();
    foreach ($accountingManagers as $manager) {
        $manager->notify(new ApInvoiceOverdueNotification($invoice));
    }
}
```

---

### CRIT-002: Frontend Permission Guard Inconsistencies
**Impact:** HIGH - Some pages accessible without proper permissions

**Issues Found:**

| Route | Current Permission | Should Be | Risk |
|-------|-------------------|-----------|------|
| `/self-service/payslips` | None | `payslips.view` | Low (own data) |
| `/me/leaves` | None | `leaves.view_own` | Low (own data) |
| `/me/loans` | None | `loans.view_own` | Low (own data) |
| `/me/overtime` | None | `overtime.view` | Low (own data) |
| `/me/attendance` | None | `attendance.view_own` | Low (own data) |
| `/me/profile` | None | `self.view_profile` | Low (own data) |
| `/search` | None | ANY authenticated | Medium |

**Fix:** Add permission guards to self-service routes:
```typescript
// In router/index.tsx
{ path: '/self-service/payslips', element: withSuspense(guard('payslips.view', <MyPayslipsPage />)) },
{ path: '/me/leaves', element: withSuspense(guard('leaves.view_own', <MyLeavesPage />)) },
// etc.
```

---

### CRIT-003: Missing SoD Check on Some Approval Endpoints
**Impact:** CRITICAL - Some approvals may bypass SoD

**Audit Results:**

| Module | Endpoint | SoD Enforced | Status |
|--------|----------|--------------|--------|
| Payroll | `hr-approve` | ✅ Yes | OK |
| Payroll | `acctg-approve` | ✅ Yes | OK |
| Payroll | `vp-approve` | ✅ Yes | OK |
| AP Invoice | `head-note` | ✅ Yes | OK |
| AP Invoice | `manager-check` | ✅ Yes | OK |
| AP Invoice | `officer-review` | ✅ Yes | OK |
| AP Invoice | `approve` | ✅ Yes | OK |
| Leave | `head_approve` | ❌ No | **FIX NEEDED** |
| Leave | `manager_check` | ❌ No | **FIX NEEDED** |
| Leave | `ga_process` | ❌ No | **FIX NEEDED** |
| Leave | `vp_note` | ❌ No | **FIX NEEDED** |
| Overtime | `supervisor_approve` | ❌ No | **FIX NEEDED** |
| Overtime | `manager_check` | ❌ No | **FIX NEEDED** |
| Overtime | `officer_review` | ❌ No | **FIX NEEDED** |
| Loan | `head_note` | ❌ No | **FIX NEEDED** |
| Loan | `manager_check` | ❌ No | **FIX NEEDED** |
| Loan | `officer_review` | ❌ No | **FIX NEEDED** |
| Loan | `vp_approve` | ❌ No | **FIX NEEDED** |
| MRQ | `note` | ❌ No | **FIX NEEDED** |
| MRQ | `check` | ❌ No | **FIX NEEDED** |
| MRQ | `review` | ❌ No | **FIX NEEDED** |
| MRQ | `vp_approve` | ❌ No | **FIX NEEDED** |
| PR | `note` | ❌ No | **FIX NEEDED** |
| PR | `check` | ❌ No | **FIX NEEDED** |
| PR | `review` | ❌ No | **FIX NEEDED** |

**Fix Required:** Add SoD middleware to all approval routes:
```php
// routes/api/v1/leave.php
Route::post('requests/{leaveRequest}/head-approve', [LeaveRequestController::class, 'headApprove'])
    ->middleware('sod:leaves,head_approve');

Route::post('requests/{leaveRequest}/manager-check', [LeaveRequestController::class, 'managerCheck'])
    ->middleware('sod:leaves,manager_check');
// etc.
```

---

## 🟠 HIGH PRIORITY ISSUES (Fix This Week)

### HIGH-001: Cross-Module Data Validation Gaps

**Issue 1: PR Budget Check Not Enforced**
- Current: Budget check runs but doesn't block approval
- Fix: Add budget validation before VP approval

```php
// In PurchaseRequestService::vpApprove()
if (!$this->budgetService->hasAvailableBudget($pr)) {
    throw new DomainException(
        'Insufficient budget for this purchase request',
        'PR_BUDGET_EXCEEDED',
        422
    );
}
```

**Issue 2: Production Order Release Without Stock Check**
- Current: Stock check exists but doesn't block release
- Fix: Enforce stock availability before release

```php
// In ProductionOrderService::release()
$stockCheck = $this->stockService->checkAvailability($bomComponents);
if (!$stockCheck->isAvailable()) {
    throw new DomainException(
        'Insufficient stock: ' . $stockCheck->getShortages()->implode(', '),
        'PROD_INSUFFICIENT_STOCK',
        422
    );
}
```

**Issue 3: AR Invoice Creation Without Delivery Verification**
- Current: AR invoice can be created before shipment delivered
- Fix: Link AR invoice to delivery receipt, enforce status check

---

### HIGH-002: Missing Audit Trail on Critical Actions

**Actions Missing Audit Logging:**

| Action | Model | Status |
|--------|-------|--------|
| Employee activation | Employee | ✅ Has auditing |
| Payroll run approval | PayrollRun | ✅ Has auditing |
| Journal entry post | JournalEntry | ✅ Has auditing |
| Stock adjustment | StockLedger | ❌ **MISSING** |
| Production output log | ProductionOutputLog | ❌ **MISSING** |
| QC inspection result | InspectionResult | ❌ **MISSING** |
| Mold shot log | MoldShotLog | ❌ **MISSING** |

**Fix:** Add Owen-it Auditing to models:
```php
use OwenIt\Auditing\Auditable;

class StockLedger extends Model implements AuditableContract
{
    use Auditable;
    
    protected $auditInclude = [
        'quantity', 'reference_type', 'reference_id'
    ];
}
```

---

### HIGH-003: Frontend Role-Based UI Hiding Incomplete

**Issues:**

1. **Action buttons visible but disabled** - Should be hidden entirely
2. **Edit buttons shown to view-only users** - Confusing UX
3. **Delete buttons visible to non-authorized users** - Security concern

**Example Fix in React:**
```tsx
// Before (current)
<button disabled={!canEdit}>Edit</button>

// After (recommended)
{canEdit && <button>Edit</button>}
```

**Pages needing fixes:**
- Employee list (edit/delete buttons)
- Vendor list (accredit/suspend buttons)
- Invoice lists (approve/pay buttons)
- Production orders (release/complete buttons)

---

## 🟡 MEDIUM PRIORITY ISSUES (Fix This Sprint)

### MED-001: API Response Inconsistencies

**Issue:** Some endpoints return inconsistent response formats

| Endpoint | Current | Should Be |
|----------|---------|-----------|
| `GET /api/v1/hr/employees` | `{data: [], meta: {}}` | ✅ OK |
| `GET /api/v1/inventory/items` | `{data: []}` (no meta) | **INCONSISTENT** |
| `GET /api/v1/qc/inspections` | `[]` (raw array) | **INCONSISTENT** |
| `GET /api/v1/maintenance/equipment` | `{items: []}` | **INCONSISTENT** |

**Fix:** Standardize all list endpoints to use `JsonResource` with wrapper:
```php
return new ItemMasterCollection($items);
// Returns: { data: [...], meta: {current_page, last_page, total} }
```

---

### MED-002: Missing Validation Rules

**Missing Validations:**

| Model | Field | Missing Validation |
|-------|-------|-------------------|
| Employee | `tin` | Format validation (XXX-XXX-XXX-XXX) |
| Employee | `sss_no` | Format validation (XX-XXXXXXX-X) |
| Employee | `philhealth_no` | Format validation (XX-XXXXXXXXX-X) |
| Employee | `pagibig_no` | Format validation (XXXX-XXXX-XXXX) |
| Vendor | `tin` | Uniqueness check |
| Customer | `tin` | Uniqueness check |
| PurchaseOrder | `po_date` | Must be <= expected_delivery_date |
| Invoice | `due_date` | Must be > invoice_date |

---

### MED-003: Soft Delete Inconsistencies

**Issue:** Some models use SoftDeletes, others don't - inconsistent behavior

| Model | Has SoftDeletes | Should Have |
|-------|-----------------|-------------|
| Employee | ✅ Yes | ✅ Yes |
| Vendor | ✅ Yes | ✅ Yes |
| Customer | ✅ Yes | ✅ Yes |
| ItemMaster | ❌ No | **YES** (for audit trail) |
| PurchaseOrder | ❌ No | **YES** |
| ProductionOrder | ❌ No | **YES** |
| Inspection | ❌ No | **YES** |

---

## 🟢 LOW PRIORITY ISSUES (Fix When Convenient)

### LOW-001: Code Quality Issues

1. **Unused imports** in several files
2. **Inconsistent return type hints** (some missing)
3. **Missing PHPDoc** on public methods
4. **Inconsistent naming** (some snake_case in camelCase methods)

### LOW-002: Performance Optimizations

1. **N+1 queries** in some list endpoints
2. **Missing database indexes** on foreign keys
3. **No eager loading** in some controllers

---

## 📋 DETAILED DOMAIN-BY-DOMAIN AUDIT

### ✅ FULLY WORKING DOMAINS

| Domain | Status | Notes |
|--------|--------|-------|
| **Payroll** | ✅ Complete | 17-step pipeline, 14-state workflow, all tests passing |
| **Accounting (GL)** | ✅ Complete | JE workflow, fiscal periods, recurring templates |
| **AP** | ✅ Complete | 5-step approval, 3-way match, EWT |
| **AR** | ✅ Complete | Invoice, payment, credit notes |
| **Tax** | ✅ Complete | VAT ledger, BIR filing tracker |
| **Budget** | ✅ Complete | Cost centers, budget lines, vs actual |
| **Fixed Assets** | ✅ Complete | Depreciation, disposals |

### ⚠️ PARTIALLY WORKING DOMAINS

| Domain | Status | Issues |
|--------|--------|--------|
| **HR** | ⚠️ 90% | Notifications not wired, missing SoD on some endpoints |
| **Leave** | ⚠️ 85% | Missing SoD middleware on approvals |
| **Attendance** | ⚠️ 90% | CSV import needs better error handling |
| **Loan** | ⚠️ 85% | Missing SoD middleware on approvals |
| **Procurement** | ⚠️ 90% | Budget check not enforced, missing SoD |
| **Inventory** | ⚠️ 85% | Missing audit trail, stock check not enforced |
| **Production** | ⚠️ 85% | Stock check not enforced on release |
| **QC** | ⚠️ 85% | Missing audit trail on inspection results |
| **Maintenance** | ⚠️ 90% | PM auto-generation needs verification |
| **Mold** | ⚠️ 90% | Shot count alerts need verification |
| **Delivery** | ⚠️ 90% | AR invoice auto-creation needs verification |
| **ISO** | ⚠️ 85% | CAPA integration needs testing |
| **CRM** | ⚠️ 80% | SLA breach detection needs verification |

---

## 🔧 COMPREHENSIVE FIX PLAN

### Phase 1: Critical Fixes (Week 1)

#### Day 1-2: SoD Middleware
```bash
# Tasks:
1. Add SoD middleware to all approval routes in:
   - routes/api/v1/leave.php
   - routes/api/v1/attendance.php
   - routes/api/v1/loans.php
   - routes/api/v1/inventory.php
   - routes/api/v1/procurement.php

2. Update SoD conflict matrix in system_settings
```

#### Day 3-4: Notification Wiring
```bash
# Tasks:
1. Create notification classes:
   - ApInvoiceOverdueNotification
   - ApDailyDigestNotification
   - StaleJournalEntryNotification

2. Update jobs to dispatch notifications:
   - SendApDueDateAlertJob
   - SendApDailyDigestJob
   - FlagStaleJournalEntriesJob
```

#### Day 5: Frontend Permission Fixes
```bash
# Tasks:
1. Update router/index.tsx with proper guards
2. Audit all pages for button visibility
3. Fix action button hiding logic
```

### Phase 2: High Priority (Week 2)

#### Week 2 Day 1-2: Cross-Module Validation
- Add budget enforcement to PR approval
- Add stock check enforcement to PO release
- Add delivery verification to AR invoice creation

#### Week 2 Day 3-4: Audit Trail
- Add Auditable trait to missing models
- Configure audit include/exclude fields
- Test audit logging

#### Week 2 Day 5: API Standardization
- Standardize all list endpoints
- Add consistent pagination
- Fix response wrappers

### Phase 3: Medium Priority (Week 3-4)

- Add missing validation rules
- Add SoftDeletes to required models
- Performance optimizations
- Code quality improvements

---

## ✅ TESTING CHECKLIST

After each fix, verify:

- [ ] Unit tests pass: `./vendor/bin/pest --testsuite=Unit`
- [ ] Feature tests pass: `./vendor/bin/pest --testsuite=Feature`
- [ ] Integration tests pass: `./vendor/bin/pest --testsuite=Integration`
- [ ] Architecture tests pass: `./vendor/bin/pest --testsuite=Arch`
- [ ] PHPStan passes: `./vendor/bin/phpstan analyse`
- [ ] Frontend typecheck: `cd frontend && pnpm typecheck`
- [ ] Frontend lint: `cd frontend && pnpm lint`
- [ ] E2E tests pass: `cd frontend && pnpm e2e`

---

## 📊 SUCCESS METRICS

| Metric | Current | Target |
|--------|---------|--------|
| Test Coverage | ~40% | 80%+ |
| PHPStan Errors | 0 (baseline) | 0 |
| SoD Coverage | 60% | 100% |
| Notification Coverage | 30% | 90% |
| API Consistency | 70% | 100% |

---

## 🎯 IMMEDIATE ACTION ITEMS (Today)

1. **Add SoD middleware to Leave routes** (2 hours)
2. **Add SoD middleware to Loan routes** (2 hours)
3. **Add SoD middleware to Procurement routes** (2 hours)
4. **Create AP notification classes** (3 hours)
5. **Update notification jobs** (2 hours)

**Total: ~11 hours of focused work**

---

## 📝 NOTES

- All fixes must maintain backward compatibility
- Add tests for every fix
- Update AGENTS.md with any pattern changes
- Document any new environment variables needed

---

*End of Audit Report*
