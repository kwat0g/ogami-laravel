# Ogami ERP - Production-Grade Full-Stack Gap Report

Focus: Core modules only. No new modules. Enhancement of existing workflows to production-grade quality.

---

## SECTION 1: Frontend-Backend Misalignments (Type/Status Mismatch)

### FS-001: Delivery Receipt Status - Frontend Missing Backend States
**Frontend type:** `DrStatus = 'draft' | 'confirmed' | 'cancelled'`  
**Backend state machine:** `draft -> confirmed -> partially_delivered -> delivered -> cancelled`  
**Impact:** The frontend has NO UI for `partially_delivered` or `delivered` states. The `DeliveryReceiptStateMachine` added M6 FIX for partial delivery support, but the frontend type and pages cannot display or transition to these states. Delivery receipts get stuck at `confirmed` in the UI.

### FS-002: Fixed Assets Status - Frontend has `under_maintenance`, Backend has `impaired`
**Frontend type:** `status: 'active' | 'disposed' | 'impaired' | 'fully_depreciated'`  
**Backend DB CHECK:** Uses `impaired` in DB constraint  
**CLAUDE.md note:** "Frontend TS type uses `under_maintenance`; DB CHECK uses `impaired` -- DB is authoritative"  
**Actual frontend type:** Uses `impaired` (correct). But no search for `under_maintenance` found in hooks. This was already fixed.

### FS-003: Fixed Asset Transfer Type Defined but No Backend
**Frontend type:** `AssetTransfer` interface fully defined in `frontend/src/types/fixed_assets.ts:60-79`  
**Backend:** No AssetTransfer model, no migration, no routes (TODO Phase 4)  
**Impact:** Frontend has a complete type definition for a feature that does not exist in the backend. Any UI built against this type will fail.

### FS-004: Sales Order Has States the Backend Cannot Produce
**Frontend type:** `status: 'draft' | 'confirmed' | 'in_production' | 'partially_delivered' | 'delivered' | 'invoiced' | 'cancelled'`  
**Backend routes:** Only `confirm` and `cancel` endpoints exist on `SalesOrderController`  
**Impact:** States `in_production`, `partially_delivered`, `delivered`, `invoiced` have no backend transition endpoints. Sales orders get stuck at `confirmed` and never progress through their lifecycle in the UI.

### FS-005: Budget Module - Frontend Has Budget Lines Pages, Backend Has Budget Lines API, But No Frontend Route for Budget Lines
**Frontend pages:** Only `CostCentersPage` and `DepartmentBudgetsPage` exist  
**Frontend hooks:** `useBudget.ts` has `useBudgetLines()`, `useSubmitBudget()`, `useApproveBudget()`, `useRejectBudget()`  
**Backend routes:** Full budget lines CRUD with approval workflow at `/budget/lines`  
**Router:** Only `/budget/cost-centers` and `/budget/department-budgets` routes registered  
**Impact:** The budget line approval workflow (create -> submit -> approve/reject) is fully built in backend and hook layer but has NO frontend page. Budget managers cannot use this feature.

### FS-006: Budget Variance Analysis - Hook Exists, No Page Route
**Frontend hook:** `useAnalytics.ts` has `useBudgetVariance()`, `useBudgetVarianceByCostCenter()`  
**Backend routes:** `/budget/variance`, `/budget/variance/by-cost-center`, `/budget/variance/forecast`  
**Router:** No route for variance analysis page  
**Impact:** Budget variance analysis and forecasting is fully built but inaccessible from the UI.

---

## SECTION 2: Frontend Pages With No Backend Support (Ghost Pages)

### FS-007: Performance Appraisal Page - No Backend
**Frontend page:** `frontend/src/pages/hr/PerformanceAppraisalListPage.tsx`  
**Frontend route:** `/hr/appraisals`  
**Backend:** No PerformanceAppraisal model, no service, no controller, no routes  
**Impact:** Page exists and is routable but will show empty data or crash on API call.

### FS-008: HR Reports Page - Likely Incomplete Backend
**Frontend page:** `frontend/src/pages/hr/HRReportsPage.tsx`  
**Frontend hook:** `useHRReports.ts` exists  
**Backend:** No dedicated HR reports route found in `routes/api/v1/hr.php`  
**Impact:** Page may not have matching backend endpoints for all report types.

---

## SECTION 3: Backend APIs With No Frontend (Orphaned Endpoints)

### FS-009: Dunning System - Backend Routes Missing, Frontend Hook Calls Non-Existent API
**Frontend hook:** `useDunning.ts` calls `/ar/dunning/notices`, `/ar/dunning/generate`, `/ar/dunning/notices/:ulid/send`, `/ar/dunning/notices/:ulid/resolve`  
**Frontend page:** `DunningNoticesPage.tsx` exists, routed at `/ar/dunning`  
**Backend routes:** NO dunning routes in `routes/api/v1/ar.php`  
**Impact:** The dunning page renders but every API call 404s. Complete feature break.

### FS-010: Inventory Analytics - Backend Has Endpoints, No Dedicated Frontend Page
**Backend routes:** `/inventory/analytics/abc`, `/inventory/analytics/turnover`, `/inventory/analytics/dead-stock`  
**Frontend:** No dedicated analytics page in `frontend/src/pages/inventory/`  
**Impact:** ABC analysis, turnover analysis, and dead stock detection are available via API but have no UI.

### FS-011: Low Stock Auto-Reorder - Backend Has Endpoint, No Frontend Page
**Backend routes:** `/inventory/low-stock`, `/inventory/low-stock/create-reorder`  
**Frontend:** No dedicated page for low stock alerts or auto-reorder  
**Impact:** Warehouse managers cannot view low stock items or trigger auto-reorder PRs from the UI.

### FS-012: Recurring Journal Templates - Backend Fully Built, Frontend Page Exists But Limited
**Backend routes:** Full CRUD + toggle at `/finance/recurring-templates`  
**Frontend page:** `RecurringTemplatesPage.tsx` exists  
**Backend gap:** No scheduled command to auto-generate entries  
**Impact:** Templates can be created but never auto-execute.

---

## SECTION 4: Broken Chain Processes (Process Stuck Mid-Flow)

### FS-013: Order-to-Cash Chain Breaks at Sales Order Confirm
**Chain:** Quotation -> Accept -> Convert to SO -> Confirm SO -> ??? -> Delivery -> Invoice -> Payment  
**Break point:** After `SalesOrderService::confirm()`, the system attempts stock reservation and auto production order creation, but:
1. Stock reservation failures are caught and logged silently
2. Production order creation failures are caught and logged silently
3. No state transition to `in_production` exists in backend
4. No mechanism to create delivery receipt from sales order
5. No mechanism to auto-create AR invoice from delivery

**Impact:** The entire O2C chain after SO confirmation depends on manual intervention with no UI guidance for the next step.

### FS-014: Client Order Chain Breaks After Approval
**Chain:** Client places order -> Sales reviews -> VP approves -> Production -> Delivery -> Fulfillment  
**State machine:** `approved -> in_production -> ready_for_delivery -> delivered -> fulfilled`  
**Break point:** `OrderAutomationService` attempts to auto-create production orders on approval but has many failure points (no BOM, no product reference). If it fails:
1. No way in CRM UI to manually create a production order
2. No button to transition `approved -> in_production`
3. Client order stuck at `approved` with no next action visible

### FS-015: Procurement GR-to-Invoice Chain - QC Step May Block Indefinitely
**Chain:** GR created -> Submit for QC -> QC inspection -> Pass/Fail -> Confirm GR -> Three-way match -> AP Invoice auto-draft  
**Break point:** If GR is submitted for QC (`pending_qc` status) but QC team never creates an inspection or the inspection is never completed:
1. GR stays at `pending_qc` forever
2. PO stays at `delivered` and never reaches `partially_received`/`fully_received`
3. AP invoice never gets auto-drafted
4. No timeout or escalation mechanism

### FS-016: Payroll Pipeline May Stall at COMPUTED
**Chain:** DRAFT -> SCOPE_SET -> PRE_RUN_CHECKED -> PROCESSING -> COMPUTED -> REVIEW -> SUBMITTED -> ...  
**Break point:** After computation completes (`COMPUTED`), the transition to `REVIEW` requires manual action. But in the router, the review page is at `/payroll/runs/:ulid/review`. If the payroll initiator navigates away from the computing page:
1. The run stays at `COMPUTED` with no automatic redirection
2. No notification sent to initiator that computation is complete
3. Dashboard may not surface `COMPUTED` runs prominently

### FS-017: Delivery Receipt Confirm Does NOT Transition to Delivered
**Chain:** Create DR -> Confirm -> Ship -> Deliver to Customer  
**Break point:** `DeliveryReceiptService::confirm()` transitions to `confirmed` and moves stock. But:
1. There is no API endpoint to transition from `confirmed` to `partially_delivered` or `delivered`
2. The `DeliveryReceiptStateMachine` supports these transitions but no controller method calls them
3. The shipment status is tracked separately (ShipmentStatus: `pending -> in_transit -> delivered`)
4. Shipment reaching `delivered` does NOT auto-update the delivery receipt status
5. AR invoice creation checks for DR status `delivered` which can never be reached

**Impact:** Outbound deliveries get stuck at `confirmed`. AR invoices cannot be auto-linked to DRs.

### FS-018: Material Requisition to Purchase Request Conversion - Frontend Gap
**Backend route:** `POST /procurement/purchase-requests/from-mrq/{materialRequisition}`  
**Frontend:** No button or UI flow in `MaterialRequisitionDetailPage.tsx` to trigger MRQ-to-PR conversion  
**Impact:** When MRQ is approved but stock is insufficient, the user is supposed to convert it to a PR. Without a UI button, this automation is backend-only and invisible.

---

## SECTION 5: Database/Model Misalignments

### FS-019: AR Invoice Uses Floating Point for Money
**File:** `app/Domains/AR/Services/CustomerInvoiceService.php:108-110`  
**Issue:** Subtotal and VAT use `(float)` casting and `round()` instead of integer centavos with `Money` VO  
**Convention:** Project requires all money as integer centavos via `Money` value object  
**Impact:** Rounding errors on high-volume invoice processing. Financial reports may not balance to the cent.

### FS-020: Stock Service Uses Float for Quantities
**File:** `app/Domains/Inventory/Services/StockService.php`  
**Issue:** All quantity parameters are `float`. Comparisons like `$balance < $quantity` are unreliable with floating-point  
**Impact:** Edge cases where `0.1 + 0.2 !== 0.3` could cause false insufficient stock errors.

### FS-021: Leave Total Days Calculation Counts Weekends
**File:** `app/Domains/Leave/Services/LeaveRequestService.php:75-78`  
**Issue:** `diffInDays + 1` counts calendar days including weekends/holidays  
**Impact:** A Monday-to-Friday leave request spanning a weekend deducts 7 days instead of 5 from leave balance.

### FS-022: Loan GL Account Codes Hardcoded
**File:** `app/Domains/Loan/Services/LoanRequestService.php:48-52`  
**Constants:** `ACCT_LOANS_RECEIVABLE = '1200'`, `ACCT_LOANS_PAYABLE = '2104'`, `ACCT_CASH_IN_BANK = '1001'`  
**Issue:** Not configurable via AccountMapping  
**Impact:** If client COA uses different codes, loan journal entries post to wrong accounts.

---

## SECTION 6: Missing UI for Complete Workflows

### FS-023: Budget Lines Approval Workflow - No UI
**Backend:** Complete CRUD + submit -> approve -> reject workflow  
**Frontend hook:** Complete mutations exist  
**Frontend route:** None  
**Need:** A `BudgetLinesPage.tsx` with list, create form, and approval actions

### FS-024: Vendor RFQ Comparison - No Response Recording UI
**Backend:** VendorRfq, VendorRfqVendor models; VendorRfqService  
**Frontend pages:** `VendorRfqListPage`, `VendorRfqDetailPage` exist  
**Missing:** No UI for vendors to submit quotation responses; no comparison matrix page  
**Impact:** RFQs can be created and viewed but the competitive bidding workflow has no completion path.

### FS-025: Blanket Purchase Orders - Minimal UI
**Backend:** BlanketPurchaseOrder model, BlanketPurchaseOrderService  
**Frontend page:** `BlanketPurchaseOrdersPage.tsx` exists  
**Missing:** No create/edit form, no release-against-blanket workflow  
**Impact:** BPOs may be list-only without the ability to create or draw down against them.

### FS-026: Maintenance Work Order Parts - No Inventory Integration UI
**Backend routes:** `GET/POST /maintenance/work-orders/{id}/parts`  
**Backend:** WorkOrderPart model links to inventory items  
**Frontend pages:** Work order detail page exists  
**Missing:** No UI to add spare parts from inventory to a work order, no stock deduction confirmation  
**Impact:** Parts consumption tracking is backend-only.

### FS-027: Employee Clearance Workflow - No Frontend Page
**Backend:** `EmployeeClearanceService` with full clearance process  
**Frontend:** No clearance page in any employee detail or HR section  
**Impact:** Resignation/termination clearance must be done via direct API calls.

### FS-028: Onboarding Checklist - No Frontend Page
**Backend:** `OnboardingChecklistService` exists  
**Frontend:** No onboarding page after recruitment hire step  
**Impact:** New hire onboarding cannot be tracked through the UI.

---

## SECTION 7: Silent Failure Risks (Production Blockers)

### FS-029: Delivery First-Warehouse Fallback
**File:** `DeliveryReceiptService.php:113`  
**Code:** `WarehouseLocation::where('is_active', true)->first()`  
**Issue:** If `null`, stock movement is silently skipped on delivery confirmation  
**Impact:** Confirmed deliveries do not deduct inventory. Stock records diverge from physical reality.

### FS-030: Production Order Release Auto-MRQ Failure Non-Fatal
**File:** `ProductionOrderService.php:407`  
**Issue:** Auto-MRQ creation on production release catches all exceptions  
**Impact:** Production starts without materials being requisitioned. Shop floor has no materials.

### FS-031: Sales Quotation Accept Auto-SO Creation Failure Non-Fatal
**File:** `QuotationService.php:120-122`  
**Issue:** Auto Sales Order creation from accepted quotation is wrapped in try-catch  
**Impact:** Quotation shows as `accepted` but no Sales Order exists. Customer thinks order is placed.

### FS-032: Year-End Closing Proceeds With Open Sub-Ledger Items
**File:** `YearEndClosingService.php:52-57`  
**Issue:** Warnings about open AP invoices, AR invoices, or unposted JEs are logged but closing proceeds  
**Impact:** Year-end closing may zero out P&L accounts while sub-ledgers have unreconciled items.

---

## SECTION 8: Cross-Module Chain Integrity Summary

### Chain: Procure-to-Pay Status
| Step | Backend | Frontend | Status |
|------|---------|----------|--------|
| PR Create | OK | OK | Working |
| PR Approval (4-stage) | OK | OK | Working |
| Budget Check | OK (soft) | Partial | Warning-only, proceeds without budget |
| PR to PO Convert | OK | OK | Working |
| PO Send to Vendor | OK | OK | Working |
| Vendor Acknowledge | OK | OK (portal) | Working |
| GR Create | OK | OK | Working |
| GR QC Flow | OK | Partial | No timeout; can stall indefinitely |
| GR Confirm + Stock | OK | OK | Working |
| Three-Way Match | OK | Implicit | Working |
| AP Invoice Auto-Draft | OK | OK | Working |
| AP Invoice Approve + GL | OK | OK | Working |
| AP Payment + GL | OK | OK | Working |

### Chain: Order-to-Cash Status
| Step | Backend | Frontend | Status |
|------|---------|----------|--------|
| Quotation Create | OK | OK | Working |
| Quotation Send/Accept | OK | OK | Working |
| Convert to SO | OK | OK | Working |
| SO Confirm | OK | OK | Silent failures on downstream |
| SO to Production | Fragile | No UI link | BROKEN - silent failure |
| SO to Delivery Receipt | Missing | Missing | BROKEN - no automation |
| DR Confirm | OK | OK | Stuck at confirmed |
| DR to Delivered | Missing endpoint | Missing UI | BROKEN - no transition |
| AR Invoice from DR | OK | Manual only | Missing automation link |
| AR Payment | OK | OK | Working |

### Chain: Hire-to-Retire Status
| Step | Backend | Frontend | Status |
|------|---------|----------|--------|
| Requisition to Hire | OK | OK | Working |
| Employee Create | OK | OK | Working |
| Shift Assignment | OK | OK | Working |
| Leave Balance Init | OK (event) | OK | Working |
| Attendance Logging | OK | OK | Working |
| OT Requests | OK | OK | Working |
| Leave Requests (4-step) | OK | OK | Working |
| Loan Requests (5-step) | OK | OK | Working |
| Payroll Compute | OK | OK | Working |
| Payroll GL Post | OK | OK | Working |
| Clearance on Resign | OK | No UI | BROKEN - no frontend |
| Onboarding | Service only | No UI | BROKEN - no frontend |

### Chain: Plan-to-Produce Status
| Step | Backend | Frontend | Status |
|------|---------|----------|--------|
| BOM Create | OK | OK | Working |
| MRP Explosion | OK | OK | Working |
| Production Order Create | OK | OK | Working |
| Stock Check (PROD-001) | OK | OK | Working |
| Release to Floor | OK | OK | Working |
| Auto-MRQ | Fragile | No UI feedback | Risk - silent failure |
| Material Consumption | OK | OK | Working |
| Output Logging | OK | OK | Working |
| QC Inspection | OK | OK | Working |
| Complete + Cost Post | OK (non-fatal) | OK | Risk - GL may not post |
| Close Order | OK | OK | Working |

---

## Priority Fix List (Production-Grade Readiness)

### P0 - CRITICAL (Process completely broken)
1. **FS-009:** Add dunning backend routes (frontend exists, backend 404s)
2. **FS-017:** Add DR status transition endpoints (confirmed -> delivered)
3. **FS-001:** Add `partially_delivered` and `delivered` to frontend DR type
4. **FS-013:** Add SO lifecycle transitions (confirm -> in_production -> delivered -> invoiced)

### P1 - HIGH (Key workflows incomplete)
5. **FS-005/FS-006:** Add budget lines page and variance analysis page
6. **FS-007:** Either build performance appraisal backend or remove the frontend page
7. **FS-004:** Add SO status transition endpoints and frontend buttons
8. **FS-014:** Add manual production order link from client order detail page
9. **FS-027:** Build employee clearance UI
10. **FS-018:** Add MRQ-to-PR conversion button in MRQ detail page

### P2 - MEDIUM (Silent failures that need surfacing)
11. **FS-029:** Throw error instead of skipping stock movement when no warehouse
12. **FS-030:** Surface MRQ auto-creation failure to user
13. **FS-031:** Surface auto-SO creation failure to user
14. **FS-019:** Convert AR invoice to integer centavos
15. **FS-021:** Exclude weekends from leave day calculation
16. **FS-022:** Make loan GL accounts configurable via AccountMapping
17. **FS-026:** Build work order parts UI in maintenance detail page

### P3 - LOW (Completeness improvements)
18. **FS-010:** Build inventory analytics page
19. **FS-011:** Build low stock reorder page
20. **FS-024:** Build RFQ response recording UI
21. **FS-025:** Build blanket PO create/release UI
22. **FS-028:** Build onboarding checklist UI
23. **FS-003:** Remove AssetTransfer type from frontend or mark as future
24. **FS-012:** Add scheduled command for recurring journal generation
