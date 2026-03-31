# Ogami ERP - Gap Analysis, Broken Processes & Risk Report

After deep analysis of all 22 domain modules -- services, state machines, policies, routes, and cross-module integrations -- here are the gaps, broken processes, risks, and missing functionality identified.

---

## CRITICAL: Silent Failures That Lose Data or Break Audit Trails

### GAP-001: Fixed Assets Depreciation Silently Skips GL Posting
**Module:** Fixed Assets  
**File:** `app/Domains/FixedAssets/Services/FixedAssetService.php:76-78`  
**Issue:** Depreciation entries are created in the database but GL journal entries are silently skipped if the category's GL accounts (asset, accumulated depreciation, depreciation expense) are null. The depreciation amount is recorded but never posted to the general ledger -- the balance sheet and P&L diverge from fixed asset sub-ledger.  
**Impact:** Financial statements become inaccurate over time without any error to the user.  
**Note:** Disposal was fixed (FA-GL-001 now throws), but the `depreciateMonth()` method catches errors and logs them at critical level while continuing -- depreciation entries exist without matching JEs.

### GAP-002: Physical Count Variance GL Posting Silently Skipped
**Module:** Inventory  
**File:** `app/Domains/Inventory/Services/PhysicalCountService.php:200-228`  
**Issue:** When physical count is approved and variances exist, the GL posting is skipped silently if `inventoryAccount` or `varianceAccount` is not configured. Also, if GL posting throws, the count is still approved (non-fatal).  
**Impact:** Inventory adjustments happen in sub-ledger but GL inventory value is wrong.

### GAP-003: Production Cost Variance Posting Non-Fatal
**Module:** Production  
**File:** `app/Domains/Production/Services/ProductionOrderService.php:637-638`  
**Issue:** When a production order is completed, cost variance posting to GL is wrapped in a try-catch. Failure is logged as a warning but the production order proceeds to `completed` status. The actual vs standard cost variance is never recorded in the GL.  
**Impact:** Manufacturing cost variance analysis against GL becomes unreliable.

---

## HIGH: Missing Modules / Incomplete Implementations

### GAP-004: ISO Document Management Module is Empty
**Module:** ISO  
**Location:** `app/Domains/ISO/` -- only contains `Policies/ISOPolicy.php`  
**Issue:** No models, no services, no routes (routes are TODO in `enhancements.php:377-379`). QC templates reference ISO form numbers (AD-084-00) but there is no document control system.  
**Impact:** ISO compliance tracking, document versioning, acknowledgment workflows are entirely manual.

### GAP-005: CRM Leads and Opportunities Not Implemented
**Module:** CRM  
**File:** `routes/api/v1/crm.php:27-52` -- all commented out as TODO Phase 2  
**Issue:** Lead management, lead scoring, opportunity pipeline, and conversion workflows are planned but not built. The CRM currently only has Tickets and Client Orders.  
**Impact:** No sales pipeline visibility, no lead-to-customer conversion tracking, no forecast data.

### GAP-006: Fixed Asset Transfers Not Implemented
**Module:** Fixed Assets  
**File:** `routes/api/v1/fixed_assets.php:78-84` -- TODO Phase 4  
**Issue:** There is no mechanism to transfer assets between departments. Asset register has `department_id` but no transfer approval workflow.  
**Impact:** Cannot track inter-department asset movements with audit trail.

### GAP-007: Fixed Asset Revaluation Not Implemented
**File:** `routes/api/v1/enhancements.php:440-442` -- TODO Phase 4  
**Issue:** No revaluation service. Assets can only depreciate; impairment/upward revaluation is not supported.  
**Impact:** Compliance gap for PFRS/IAS 36 requirements.

### GAP-008: Production Capacity Planning Not Implemented
**File:** `routes/api/v1/enhancements.php:331-333` -- TODO Phase 2  
**Issue:** CapacityPlanningService does not exist. MRP exists but cannot factor in work center capacity constraints.  
**Impact:** Production scheduling cannot detect bottlenecks or overcommitted work centers.

---

## HIGH: Workflow Gaps and Broken Process Chains

### GAP-009: Delivery Receipt State Machine vs AR Invoice Status Mismatch
**Modules:** Delivery + AR  
**Issue:** The DeliveryReceiptStateMachine has states: `draft -> confirmed -> partially_delivered -> delivered`. But the AR CustomerInvoiceService checks for `$deliveryReceipt->status !== 'delivered'` to block invoicing. However, the DeliveryReceiptService `confirm()` method only sets status to `confirmed` -- there is no route/endpoint to transition to `delivered`. The shipment status update is separate from the delivery receipt status.  
**Impact:** Customer invoices may not be creatable if the delivery receipt workflow doesn't reach 'delivered' status through the current API endpoints.

### GAP-010: Sales Order Confirm Has Extensive Silent Failures
**Module:** Sales  
**File:** `app/Domains/Sales/Services/SalesOrderService.php:194-322`  
**Issue:** When a sales order is confirmed, stock reservation, auto production order creation, and credit limit check all fail silently (catch + Log::warning). A confirmed sales order could have:
- No stock reserved
- No production order created even when stock is insufficient  
- Credit limit exceeded (soft mode, logged but proceeds)

**Impact:** Order fulfillment falls through with no user-visible alert. Items may be over-committed.

### GAP-011: Budget Enforcement is Soft-Only for OT and Maintenance
**Module:** Budget  
**File:** `app/Domains/Budget/Services/BudgetEnforcementService.php:27`  
**Issue:** Only Purchase Requests have hard budget blocking. Overtime approval and maintenance work orders use soft warnings -- the approver sees a warning but can still proceed. No configuration to toggle hard/soft per category.  
**Impact:** Departments can overspend on OT and maintenance with no system enforcement.

### GAP-012: PR Budget Check Proceeds Without Budget
**Module:** Procurement  
**File:** `app/Domains/Procurement/Services/PurchaseRequestService.php:534-535`  
**Issue:** When no approved annual budget exists for a department, the PR proceeds with only a warning log. No user notification or UI indication.  
**Impact:** Purchase requests can be approved for departments with zero budget configured.

### GAP-013: Maintenance Work Order Has No Approval Workflow
**Module:** Maintenance  
**State Machine:** `open -> in_progress -> completed`  
**Issue:** Work orders go directly from `open` to `in_progress` with no approval gate. No SoD enforcement. No manager review before work starts. No cost estimation pre-approval for expensive repairs.  
**Impact:** Uncontrolled maintenance spending. Any technician can start work without authorization.

### GAP-014: Mold Module Has No State Machine
**Module:** Mold  
**Issue:** MoldMaster has a `status` field and a `retire()` method, but no formal state machine. Status transitions are not validated -- a mold could potentially be un-retired or have invalid state changes.  
**Impact:** Data integrity risk for mold lifecycle tracking.

---

## MEDIUM: Cross-Module Integration Gaps

### GAP-015: No Automated Sales Order to Delivery Receipt Link
**Modules:** Sales -> Delivery  
**Issue:** When a sales order is confirmed, there is no automated creation of a delivery receipt. The delivery receipt must be created manually, referencing the sales order. There is no FK relationship between SalesOrder and DeliveryReceipt models.  
**Impact:** Manual process prone to missed deliveries. No traceability from SO to DR without manual effort.

### GAP-016: Client Order to Production Order Automation is Fragile
**Modules:** CRM -> Production  
**File:** `app/Domains/Production/Services/OrderAutomationService.php`  
**Issue:** The service has multiple failure points (no BOM found, no product reference, wrong status) that all result in Log::warning and empty returns. The ClientOrderService catches all automation failures silently.  
**Impact:** Client orders marked as "approved" may have no production orders created, with no user notification.

### GAP-017: No AR Invoice Auto-Generation from Sales Order
**Modules:** Sales -> AR  
**Issue:** While the AP module auto-drafts invoices from POs via the `ThreeWayMatchPassed` event + `InvoiceAutoDraftService`, there is no equivalent automation on the AR side from Sales Orders. Customer invoices must be created manually.  
**Impact:** Revenue recognition delay. Manual process increases risk of unbilled deliveries.

### GAP-018: Loan GL Posting Uses Hardcoded Account Codes
**Module:** Loan  
**File:** `app/Domains/Loan/Services/LoanRequestService.php:48-52`  
**Issue:** GL account codes are hardcoded as constants (`1200`, `2104`, `1001`). These are not configurable via AccountMapping. If the client's chart of accounts uses different codes, loan JEs fail or post to wrong accounts.  
**Impact:** Accounting errors if COA doesn't match hardcoded values.

### GAP-019: Delivery Location Falls Back to First Active Warehouse
**Module:** Delivery  
**File:** `app/Domains/Delivery/Services/DeliveryReceiptService.php:113`  
**Issue:** When confirming a delivery receipt, the stock movement uses `WarehouseLocation::where('is_active', true)->first()`. If the location is null, the stock update is silently skipped entirely.  
**Impact:** Confirmed deliveries may not update stock if no warehouse is configured.

---

## MEDIUM: Security & SoD Gaps

### GAP-020: Several Route Closures Bypass Service Layer
**Modules:** HR, Attendance, Budget, Inventory, CRM  
**Issue:** Many routes use inline closures with direct `DB::table()` queries (e.g., departments CRUD, positions CRUD, shift schedules, attendance summary, AR aging, CRM dashboard). These bypass the service layer pattern, skip policies, and don't use `DB::transaction()`.  
**Impact:** Inconsistent authorization enforcement; no audit trail for some operations.

### GAP-021: Vendor Portal orderDetail Exposes All Fillable Fields
**Module:** AP / Vendor Portal  
**File:** `CLAUDE.md:215`  
**Issue:** The vendor portal `orderDetail` endpoint returns raw model JSON without a Resource transformer. All `$fillable` attributes on the PO model are exposed directly to vendor users.  
**Impact:** Information leakage -- internal pricing, notes, or references visible to vendors.

### GAP-022: Year-End Closing Bypasses SoD
**Module:** Accounting  
**File:** `app/Domains/Accounting/Services/YearEndClosingService.php:106-116`  
**Issue:** The closing journal entry is created as `status: 'posted'` directly, bypassing the normal draft -> submitted -> posted workflow. The `created_by_id` and `posted_by` are the same user. No SoD check.  
**Impact:** Year-end closing JE cannot be independently reviewed before posting.

### GAP-023: Admin Role Bypasses All Employee Policies
**Module:** HR  
**File:** `app/Domains/HR/Policies/EmployeePolicy.php:26-32`  
**Issue:** The `before()` method returns `true` for admin role, bypassing all checks including SELF-001 (cannot edit own salary) and SELF-002 (cannot terminate own record).  
**Impact:** Admin can modify their own salary and status without restriction.

---

## MEDIUM: Data Integrity Risks

### GAP-024: AR Invoice Uses Float for Money
**Module:** AR  
**File:** `app/Domains/AR/Services/CustomerInvoiceService.php:108-110`  
**Issue:** `$subtotal` and `$vatAmount` are cast to `(float)` and calculated with `round()`. The Money value object is not used despite the project rule that money must always be integer centavos.  
**Impact:** Floating-point rounding errors in invoice amounts. Violates ARCH money convention.

### GAP-025: Leave Total Days Calculation Uses Calendar Days
**Module:** Leave  
**File:** `app/Domains/Leave/Services/LeaveRequestService.php:75-78`  
**Issue:** When total_days is not provided, the service computes inclusive calendar days (`diffInDays + 1`). This includes weekends and holidays. The system does not check against the employee's work schedule.  
**Impact:** A Mon-Fri leave request spanning a weekend counts as 7 days instead of 5.

### GAP-026: Stock Service Uses Float for Quantities
**Module:** Inventory  
**File:** `app/Domains/Inventory/Services/StockService.php:25-36`  
**Issue:** Stock quantities use `float` type throughout (parameters, comparisons, arithmetic). Floating-point precision issues could cause `$balance < $quantity` to fail incorrectly.  
**Impact:** Edge cases where stock appears insufficient due to floating-point rounding.

---

## LOW: Missing Features Within Existing Modules

### GAP-027: No Employee Onboarding Checklist Implementation
**Module:** HR  
**Issue:** `OnboardingChecklistService` exists but there are no routes, no models for onboarding checklist items. The recruitment pipeline ends at "hire" but the onboarding journey is not tracked.

### GAP-028: No Attendance Geofencing Implementation
**Module:** Attendance  
**Issue:** `GeoFenceService` exists but attendance logging via routes has no geofence parameters. Employee work locations model exists but no validation against location during clock-in.

### GAP-029: No SIL Monetization Route
**Module:** Leave  
**Issue:** `SilMonetizationService` exists but there are no API routes for employees to request SIL monetization or for HR to process it.

### GAP-030: No Recurring Journal Auto-Generation Trigger
**Module:** Accounting  
**Issue:** `RecurringJournalTemplateService` can generate JEs from templates, but there is no scheduled Artisan command or cron job to auto-generate them. It must be triggered manually.

### GAP-031: No Bank Reconciliation Route for Matching
**Module:** Accounting  
**Issue:** Bank reconciliation routes exist for CRUD but the actual reconciliation matching workflow (match bank transactions to JEs) has no dedicated API endpoint -- only the model and service exist.

### GAP-032: Delivery Module Has No Proof of Delivery Upload
**Module:** Delivery  
**Issue:** `ProofOfDeliveryService` exists but there are no routes for uploading proof of delivery documents (signed DR, photos). The shipment tracking is status-based only.

### GAP-033: No Dunning Automation
**Module:** AR  
**Issue:** DunningService and DunningLevel/DunningNotice models exist, but there are no API routes for dunning operations and no automated dunning notice generation.

---

## Summary Risk Matrix

| ID | Severity | Module | Issue | Type |
|----|----------|--------|-------|------|
| GAP-001 | CRITICAL | Fixed Assets | Depreciation GL posting silently skipped | Silent Failure |
| GAP-002 | CRITICAL | Inventory | Physical count GL posting silently skipped | Silent Failure |
| GAP-003 | CRITICAL | Production | Cost variance GL posting non-fatal | Silent Failure |
| GAP-004 | HIGH | ISO | Entire module empty | Missing Module |
| GAP-005 | HIGH | CRM | Leads/Opportunities not built | Missing Feature |
| GAP-006 | HIGH | Fixed Assets | Asset transfers not implemented | Missing Feature |
| GAP-007 | HIGH | Fixed Assets | Asset revaluation not implemented | Missing Feature |
| GAP-008 | HIGH | Production | Capacity planning not implemented | Missing Feature |
| GAP-009 | HIGH | Delivery/AR | DR status vs invoice status mismatch | Broken Process |
| GAP-010 | HIGH | Sales | Order confirm has silent failures | Silent Failure |
| GAP-011 | HIGH | Budget | OT/Maintenance budget soft-only | Process Gap |
| GAP-012 | HIGH | Procurement | PR proceeds without budget | Process Gap |
| GAP-013 | HIGH | Maintenance | No approval workflow for work orders | Process Gap |
| GAP-014 | HIGH | Mold | No formal state machine | Process Gap |
| GAP-015 | MEDIUM | Sales/Delivery | No automated SO to DR link | Integration Gap |
| GAP-016 | MEDIUM | CRM/Production | Order automation fragile | Integration Gap |
| GAP-017 | MEDIUM | Sales/AR | No auto-invoice from SO | Integration Gap |
| GAP-018 | MEDIUM | Loan | Hardcoded GL account codes | Data Integrity |
| GAP-019 | MEDIUM | Delivery | Falls back to first warehouse | Process Gap |
| GAP-020 | MEDIUM | Multiple | Route closures bypass services | Security Gap |
| GAP-021 | MEDIUM | AP | Vendor portal data leakage | Security Gap |
| GAP-022 | MEDIUM | Accounting | Year-end closing bypasses SoD | Security Gap |
| GAP-023 | MEDIUM | HR | Admin bypasses self-edit restrictions | Security Gap |
| GAP-024 | MEDIUM | AR | Float used for money amounts | Data Integrity |
| GAP-025 | MEDIUM | Leave | Calendar days include weekends | Business Logic |
| GAP-026 | MEDIUM | Inventory | Float for stock quantities | Data Integrity |
| GAP-027 | LOW | HR | Onboarding checklist not routed | Missing Feature |
| GAP-028 | LOW | Attendance | Geofencing not connected | Missing Feature |
| GAP-029 | LOW | Leave | SIL monetization not routed | Missing Feature |
| GAP-030 | LOW | Accounting | Recurring JE not scheduled | Missing Feature |
| GAP-031 | LOW | Accounting | Bank reconciliation matching not routed | Missing Feature |
| GAP-032 | LOW | Delivery | Proof of delivery not routed | Missing Feature |
| GAP-033 | LOW | AR | Dunning automation not routed | Missing Feature |

**Total: 33 gaps identified**
- CRITICAL: 3 (all silent GL posting failures)
- HIGH: 11 (missing modules, broken processes, missing approval workflows)
- MEDIUM: 12 (integration gaps, security issues, data integrity)
- LOW: 7 (services exist but not exposed via API)
