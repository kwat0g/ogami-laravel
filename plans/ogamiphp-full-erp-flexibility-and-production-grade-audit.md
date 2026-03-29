# ogamiPHP - Full ERP Flexibility & Production-Grade Audit Report

## PHASE 0 - CODEBASE DISCOVERY

### 0A. Domain Inventory

| Domain | Model Count | Service Count | Route File? | StateMachine? | JE Posts? | React Pages? | Tests? | Seeder? |
|---|---|---|---|---|---|---|---|---|
| Accounting | 9 | 13 | Yes | No | N/A | Yes | No dedicated | Yes |
| AP | 8 | 10 | Yes | Yes (VendorInvoice) | Yes | Yes (vendor-portal) | No dedicated | No dedicated |
| AR | 7 | 7 | Yes | Yes (CustomerInvoice) | Yes | Yes | No dedicated | No dedicated |
| Attendance | 5 | 4 | Yes | No | No | Yes (via employee) | No dedicated | Yes (SampleAttendance) |
| Budget | 2 | 2 | Yes | Yes | No | Yes | No dedicated | No dedicated |
| CRM | 7 | 4 | Yes (sales+crm) | Yes (ClientOrder) | No | Yes | No dedicated | No dedicated |
| Dashboard | 0 | 3 | N/A | N/A | N/A | Yes | N/A | N/A |
| Delivery | 6 | 4 | Yes | Yes (DeliveryReceipt) | No | Yes | No dedicated | No dedicated |
| FixedAssets | 4 | 1 | Yes | No | Yes | Yes | No dedicated | No dedicated |
| HR | 6+12 recruit | 5+9 recruit | Yes | Yes (Employee, Requisition, Application, Offer) | No | Yes | Yes (Recruitment) | Yes |
| Inventory | 11 | 9 | Yes | Yes (PhysicalCount) | No | Yes | Yes (MRQ) | No dedicated |
| Leave | 3 | 5 | Yes | Yes (LeaveRequest) | No | Yes | No dedicated | Yes |
| Loan | 3 | 3 | Yes | Yes | Yes | Yes | No dedicated | Yes |
| Maintenance | 4 | 4 | Yes | Yes (WorkOrder) | No | Yes | No dedicated | No dedicated |
| Mold | 2 | 2 | Yes | No | No | Yes | No dedicated | No dedicated |
| Payroll | 14 | 20 | Yes | Yes (PayrollRun-14 states) | Yes | Yes | Yes (Golden Suite + 10 tests) | Yes |
| Procurement | 9 | 7 | Yes | Yes (PO, PR) | No (via listener) | Yes | Yes (6 test files) | No dedicated |
| Production | 8 | 8 | Yes | Yes (ProductionOrder) | Yes | Yes | Yes (MaterialConsumption) | No dedicated |
| QC | 6 | 7 | Yes | Yes (Inspection, CAPA) | No | Yes | Yes (2 test files) | No dedicated |
| Sales | 6 | 4 | Yes | Yes (SO, Quotation) | No (via AR) | Yes | Yes (2 test files) | No dedicated |
| Tax | 2 | 5 | Yes | No | Yes | Yes | Yes (1 test file) | Yes |

**Key Observations:**
- All 20 domains have backend implementation with models, services, routes, and frontend pages
- 15/20 domains have StateMachines with TRANSITIONS constants
- Missing StateMachines: Accounting JournalEntry, Attendance OvertimeRequest, FixedAssets, Mold, Tax
- Payroll is the most mature domain (14-state machine, 17-step pipeline, golden suite)
- Test coverage is sparse outside Payroll and Procurement

### 0B. Architecture Compliance Scan

| Rule | Description | Status | Details |
|---|---|---|---|
| ARCH-001 | No DB:: in controllers | **VIOLATION** | [`ChartOfAccountsController`](app/Http/Controllers/Admin/ChartOfAccountsController.php:42) uses `DB::raw()`, [`BackupController`](app/Http/Controllers/Admin/BackupController.php:372) uses `DB::statement()` |
| ARCH-002 | Services implement ServiceContract | PASS | All domain services implement `ServiceContract` |
| ARCH-003 | Exceptions extend DomainException | PASS | Enforced via arch test |
| ARCH-004 | Value objects final readonly | PASS | 7 VOs all final readonly |
| ARCH-005 | No dd/dump in app/ | PASS | Zero debug statements found |
| ARCH-006 | Shared\Contracts only interfaces | PASS | Only `ServiceContract` and `BusinessRule` |
| ARCH-007 | No direct status assignment | **CRITICAL VIOLATION** | 28 instances across Leave, Loan, Attendance, Payroll - see detail below |
| ARCH-008 | DomainException 3+ args | PASS | All instances use 3+ args |
| ARCH-009 | Non-final services | PASS | All services are `final class` |
| ARCH-010 | Direct stock manipulation | **VIOLATION** | [`QuarantineService`](app/Domains/QC/Services/QuarantineService.php:52) uses `StockBalance::firstOrCreate` + `increment/decrement` directly, bypassing `StockService` |
| ARCH-011 | Missing ULID on domain models | Not fully audited | Need migration-by-migration check |
| ARCH-012 | Missing strict_types | PASS | All files in app/Domains have `declare(strict_types=1)` |
| ARCH-013 | Notification without fromModel | PASS | Zero instances of `new...Notification(` found |
| ARCH-014 | Extra Zustand stores | PASS | Only `authStore.ts` and `uiStore.ts` |

#### ARCH-007 Detail - Direct Status Assignments Bypassing StateMachines

| File | Line(s) | Statuses Assigned | StateMachine Exists? |
|---|---|---|---|
| [`OvertimeRequestService`](app/Domains/Attendance/Services/OvertimeRequestService.php:207) | 207, 298, 337, 385, 459, 509, 549, 584 | supervisor_approved, manager_checked, officer_reviewed, approved, rejected, cancelled | **No StateMachine** |
| [`LoanRequestService`](app/Domains/Loan/Services/LoanRequestService.php:213) | 213, 262, 305, 340, 394, 459, 637, 678, 702, 730 | approved, head_noted, manager_checked, officer_reviewed, ready_for_disbursement, active, rejected, cancelled, written_off | Yes - `LoanStateMachine` exists but NOT USED |
| [`LoanAmortizationService`](app/Domains/Loan/Services/LoanAmortizationService.php:102) | 102, 105 | fully_paid, active | Yes but NOT USED |
| [`LeaveRequestService`](app/Domains/Leave/Services/LeaveRequestService.php:115) | 115, 144, 196, 234, 296, 354, 380 | head_approved, manager_checked, rejected, ga_processed, approved, cancelled | Yes - `LeaveRequestStateMachine` exists but NOT USED |
| [`Step16OtherDeductionsStep`](app/Domains/Payroll/Pipeline/Step16OtherDeductionsStep.php:49) | 49, 58 | applied, deferred | N/A (PayrollAdjustment, not PayrollRun) |

### 0C. Hardcoded Account Codes Registry

| File | Line | Value | Business Rule | Should Live In |
|---|---|---|---|---|
| [`PayrollPostingService`](app/Domains/Payroll/Services/PayrollPostingService.php:146) | 146 | `'5001'` (fallback) | Salaries Expense GL code | `account_mapping` table |
| [`PayrollPostingService`](app/Domains/Payroll/Services/PayrollPostingService.php:155) | 155-179 | `'2100','2101','2102','2103'` | SSS/PH/Pag/WHT payable | `account_mapping` table |
| [`VendorInvoiceService`](app/Domains/AP/Services/VendorInvoiceService.php:560) | 560-561 | `'2001','6001'` | AP Payable, Expense | `account_mapping` table |
| [`VendorInvoiceService`](app/Domains/AP/Services/VendorInvoiceService.php:697) | 697-698 | `'2001','6001'` | Same, duplicated | `account_mapping` table |
| [`VatLedgerService`](app/Domains/Tax/Services/VatLedgerService.php:159) | 159-160 | `'2105','2106'` | Output VAT, VAT Remittable | `account_mapping` table |
| [`ProductionCostPostingService`](app/Domains/Production/Services/ProductionCostPostingService.php:71) | 71-76 | `'1400','5900','1300'` | WIP, Variance, Raw Material | `account_mapping` table |
| [`LoanRequestService`](app/Domains/Loan/Services/LoanRequestService.php:492) | 492-562 | Constants ACCT_LOANS_RECEIVABLE etc. | Loan GL accounts | `account_mapping` table |
| [`ApPaymentPostingService`](app/Domains/AP/Services/ApPaymentPostingService.php:147) | 147-148 | Via `$code` param | AP/Cash accounts | `account_mapping` table |
| [`InvoiceAutoDraftService (AP)`](app/Domains/AP/Services/InvoiceAutoDraftService.php:102) | 102-107 | LIKE `'2%'`, LIKE `'5%'` | AP and Expense wildcard | `account_mapping` table |
| [`InvoiceAutoDraftService (AR)`](app/Domains/AR/Services/InvoiceAutoDraftService.php:96) | 96-101 | LIKE `'1%'`, LIKE `'4%'` | AR and Revenue wildcard | `account_mapping` table |
| [`YearEndClosingService`](app/Domains/Accounting/Services/YearEndClosingService.php:73) | 73 | LIKE `'3%'` + retained earnings | Retained Earnings wildcard | `account_mapping` table |

**Total: 20+ hardcoded GL account lookups across 8 service files. No `account_mapping` table exists.**

### 0D. Cross-Module Integration Map

| Integration | Status | Evidence |
|---|---|---|
| Procurement -> Inventory (GR creates stock) | **Implemented** | Via [`UpdateStockOnThreeWayMatch`](app/Listeners/UpdateStockOnThreeWayMatch.php:16) listener calling `StockService::receive()` |
| Procurement -> AP (GR triggers invoice) | **Implemented** | Via [`CreateApInvoiceOnThreeWayMatch`](app/Listeners/Procurement/CreateApInvoiceOnThreeWayMatch.php:18) listener |
| Procurement -> Accounting (GR posts JE) | **Missing** | No JE posted on GR confirm; AP invoice post creates JE but not GR itself |
| Sales -> Delivery (SO triggers DR) | **Partial** | DR created via [`CreateDeliveryReceiptOnProductionComplete`](app/Listeners/Delivery/CreateDeliveryReceiptOnProductionComplete.php:21) and OQC pass |
| Sales -> AR (Invoice auto-created) | **Implemented** | Via [`CreateCustomerInvoiceOnShipmentDelivered`](app/Listeners/AR/CreateCustomerInvoiceOnShipmentDelivered.php:25) |
| Sales -> Accounting (Invoice posts JE) | **Implemented** | [`CustomerInvoiceService::autoPostJournalEntry()`](app/Domains/AR/Services/CustomerInvoiceService.php:407) |
| Production -> Inventory (Material issue) | **Implemented** | [`MaterialRequisitionService`](app/Domains/Inventory/Services/MaterialRequisitionService.php) + `StockService::issue()` |
| Production -> Inventory (FG receipt) | **Implemented** | Via [`ProductionOrderService`](app/Domains/Production/Services/ProductionOrderService.php) calling StockService |
| Production -> Accounting (Cost posting) | **Implemented** | [`ProductionCostPostingService`](app/Domains/Production/Services/ProductionCostPostingService.php:32) via [`AutoPostProductionCostOnComplete`](app/Listeners/Production/AutoPostProductionCostOnComplete.php:23) |
| Payroll -> Accounting (JE posting) | **Implemented** | [`PayrollPostingService`](app/Domains/Payroll/Services/PayrollPostingService.php:37) + [`PayrollAutoPostService`](app/Domains/Accounting/Services/PayrollAutoPostService.php:52) |
| Payroll -> Loan (Deductions) | **Implemented** | [`Step15LoanDeductionsStep`](app/Domains/Payroll/Pipeline/Step15LoanDeductionsStep.php) |
| Payroll -> Leave (LWOP) | **Implemented** | [`Step03AttendanceSummaryStep`](app/Domains/Payroll/Pipeline/Step03AttendanceSummaryStep.php) feeds into Step05 |
| Payroll -> Attendance | **Implemented** | Step03 reads attendance logs for tardiness/absences |
| HR -> Leave (Balance allocation) | **Implemented** | [`CreateLeaveBalances`](app/Domains/HR/Listeners/CreateLeaveBalances.php) listener on EmployeeActivated |
| FixedAssets -> Accounting (Depreciation JE) | **Partial** | Posts JE only if GL accounts configured; **silently skips** otherwise (GOTCHA-13) |
| FixedAssets -> Accounting (Disposal JE) | **Partial** | Same silent skip issue |
| QC -> Inventory (Quarantine) | **Implemented** | [`QuarantineService`](app/Domains/QC/Services/QuarantineService.php:51) but **bypasses StockService** |
| QC -> Production (Rework) | **Implemented** | [`CreateReworkOrderOnOqcFail`](app/Listeners/Production/CreateReworkOrderOnOqcFail.php:23) |
| Delivery -> CRM (Order update) | **Implemented** | [`UpdateClientOrderOnShipmentDelivered`](app/Listeners/CRM/UpdateClientOrderOnShipmentDelivered.php:21) |
| Budget -> Procurement (PO check) | **Implemented** | [`BudgetEnforcementService`](app/Domains/Budget/Services/BudgetEnforcementService.php) |
| Inventory -> Procurement (Low stock alert) | **Implemented** | [`LowStockReorderService`](app/Domains/Inventory/Services/LowStockReorderService.php) + [`NotifyLowStock`](app/Listeners/Inventory/NotifyLowStock.php) |
| Attendance -> Leave | **Implemented** | [`RecordLeaveAttendanceCorrection`](app/Listeners/Attendance/RecordLeaveAttendanceCorrection.php:22) |
| Tax -> AP (EWT) | **Implemented** | [`EwtService`](app/Domains/AP/Services/EwtService.php) |
| Tax -> AR (VAT) | **Implemented** | [`VatLedgerService`](app/Domains/Tax/Services/VatLedgerService.php) |
| HR -> Loan (Separation) | **Missing** | No evidence of separation triggering loan balance due |
| HR -> Payroll (New hire enrollment) | **Missing** | No auto-enrollment in next payroll cutoff |
| Maintenance -> Inventory (Spare parts) | **Partial** | WorkOrderPart model exists but no StockService integration found |
| Maintenance -> FixedAssets | **Missing** | No asset service history linkage |
| Mold -> Production (Shot count) | **Partial** | MoldShotLog exists but no automatic increment on production run |
| CRM -> Sales (Opportunity convert) | **Missing** | ClientOrder exists but no formal lead-to-SO conversion chain |

---

## PHASE 1 - DOMAIN-BY-DOMAIN KEY FINDINGS

### 1.1 Procurement

| Scenario | Status | Evidence |
|---|---|---|
| PO-S01: Partial GR | **Implemented** | `quantity_received` tracked per PO item, `partially_received` state exists |
| PO-S02: Over-delivery | **Implemented** | [`GoodsReceiptService`](app/Domains/Procurement/Services/GoodsReceiptService.php:70) throws `GR_QTY_EXCEEDS_PENDING` |
| PO-S03: Split delivery | **Implemented** | Multiple GRs per PO supported |
| PO-S04: PO amendment | **Implemented** | Negotiation fields exist (negotiated_quantity, proposed_delivery_date) |
| PO-S05: PO cancellation | **Implemented** | `cancelled` state in StateMachine |
| PO-S06: Return to supplier | **Missing** | No GR reversal, debit memo, or return-to-supplier workflow |
| PO-S07: 3-way match | **Implemented** | [`ThreeWayMatchService`](app/Domains/Procurement/Services/ThreeWayMatchService.php) |
| PO-S09: Foreign currency | **Missing** | No currency field on PO |
| PO-S10: Blanket PO | **Implemented** | [`BlanketPurchaseOrderService`](app/Domains/Procurement/Services/BlanketPurchaseOrderService.php) |
| PO-S11: Status coverage | **Implemented** | 10 states in [`PurchaseOrderStateMachine`](app/Domains/Procurement/StateMachines/PurchaseOrderStateMachine.php:28) including `delivered` |
| PO-S12: Budget check | **Implemented** | [`BudgetEnforcementService`](app/Domains/Budget/Services/BudgetEnforcementService.php) |

### 1.2 Inventory

| Scenario | Status | Evidence |
|---|---|---|
| INV-S01: Negative stock | **Hard block** | [`StockService::issue()`](app/Domains/Inventory/Services/StockService.php:102) throws `INV_INSUFFICIENT_STOCK` - not configurable |
| INV-S02: Multi-warehouse | **Implemented** | `location_id` on all stock operations |
| INV-S03: Lot/batch tracking | **Implemented** | [`LotBatch`](app/Domains/Inventory/Models/LotBatch.php) model with lot_number |
| INV-S06: Physical count | **Implemented** | [`PhysicalCountService`](app/Domains/Inventory/Services/PhysicalCountService.php) with StateMachine |
| INV-S08: Costing method | **Implemented** | [`CostingMethodService`](app/Domains/Inventory/Services/CostingMethodService.php) - standard and weighted_average |
| INV-S11: Reorder alert | **Implemented** | [`LowStockReorderService`](app/Domains/Inventory/Services/LowStockReorderService.php) with auto-PR creation |
| INV-S12: StockService only path | **VIOLATION** | [`QuarantineService`](app/Domains/QC/Services/QuarantineService.php:52) bypasses StockService |
| INV-S13: Stock ledger entries | **Implemented** | Every `StockService` operation creates `StockLedger` entry |

### 1.3 Payroll

| Scenario | Status | Evidence |
|---|---|---|
| PAY-S05: LWOP deduction | **Implemented** | Step03 + Step05 |
| PAY-S06: OT multipliers configurable | **Implemented** | `overtime_multiplier_configs` table + [`OvertimeMultiplierSeeder`](database/seeders/OvertimeMultiplierSeeder.php) |
| PAY-S11: Gov rate tables in DB | **Implemented** | SSS, PhilHealth, PagIBIG tables with `effective_date` |
| PAY-S12: BIR WHT in DB | **Implemented** | [`TrainTaxBracket`](app/Domains/Payroll/Models/TrainTaxBracket.php) with `effective_date` |
| PAY-S15: 14-state machine | **Implemented** | [`PayrollRunStateMachine`](app/Domains/Payroll/StateMachines/PayrollRunStateMachine.php:51) - 15 states + legacy compat |
| PAY-S17: Payroll -> JE | **Implemented** | Posts on DISBURSED via [`PayrollPostingService`](app/Domains/Payroll/Services/PayrollPostingService.php) |
| PAY-S19: Golden suite | **Exists** | [`GoldenSuiteTest.php`](tests/Unit/Payroll/GoldenSuiteTest.php) - 24 scenarios |
| PAY-S20: Pipeline DB queries | **Need audit** | Pipeline steps should only mutate context |

### 1.4 FixedAssets - Known Gotchas

| Gotcha | Status | Evidence |
|---|---|---|
| GOTCHA-10: asset_code trigger | **Documented** | PG trigger sets it - not verified if PHP/factories avoid it |
| GOTCHA-11: under_maintenance vs impaired | **STILL BROKEN** | [`fixed_assets.ts`](frontend/src/types/fixed_assets.ts:23) uses `'under_maintenance'` but DB CHECK uses `'impaired'` |
| GOTCHA-12: CSV export table name | **Documented** | `asset_depreciation_entries` vs `fixed_asset_depreciation_entries` |
| GOTCHA-13: GL silent skip | **STILL BROKEN** | [`FixedAssetService`](app/Domains/FixedAssets/Services/FixedAssetService.php:138) silently skips JE if GL accounts null - should throw DomainException |

---

## PHASE 2 - PRODUCTION-GRADE INFRASTRUCTURE AUDIT

### 2A. Configuration Infrastructure

| Config Table | Exists? | Has effective_date? | Has Seeder? | Has UI? | Used by? |
|---|---|---|---|---|---|
| system_settings | Yes | No | Yes | Yes (admin) | Multiple modules |
| overtime_multiplier_configs | Yes | Yes | Yes | Partial | Payroll Step06 |
| sss_contribution_tables | Yes | Yes | Yes | No admin UI | Payroll Step10 |
| philhealth_premium_tables | Yes | Yes | Yes | No admin UI | Payroll Step11 |
| pagibig_contribution_tables | Yes | Yes | Yes | No admin UI | Payroll Step12 |
| train_tax_brackets | Yes | Yes | Yes | No admin UI | Payroll Step14 |
| holiday_calendar | Yes | Yes | Yes | Yes | Payroll Step07 |
| minimum_wage_rates | Yes | Yes | Yes | No admin UI | Payroll |
| ewt_rates | Yes | Yes (effective_from) | No dedicated | No admin UI | AP/Tax |
| **account_mapping** | **MISSING** | N/A | N/A | N/A | **CRITICAL NEED** |
| **document_sequences** | **MISSING** | N/A | N/A | N/A | Each service has own numbering |
| **exchange_rates** | **MISSING** | N/A | N/A | N/A | No multicurrency |
| **uom_conversions** | **MISSING** | N/A | N/A | N/A | No UOM conversion |

### 2B. Account Mapping Audit

**No `account_mapping` table exists.** All GL account lookups use hardcoded codes or `config()` with hardcoded fallbacks.

| Module | Event | Hardcoded Codes | Risk |
|---|---|---|---|
| Payroll | PAYROLL_POST | 5001, 2100-2103 via config fallback | Medium - uses config() |
| AP | INVOICE_POST | 2001, 6001 hardcoded | **Critical** |
| AP | PAYMENT_POST | Via code param | Medium |
| AR | INVOICE_POST | 1xxx, 4xxx wildcard LIKE | **Critical** - fragile |
| Production | COST_POST | 1400, 5900, 1300 with name fallback | **Critical** |
| Tax | VAT_CLOSE | 2105, 2106 | High |
| Loan | DISBURSEMENT | Constants in service | High |
| FixedAssets | DEPRECIATION | Category GL fields | OK (configurable per category) |
| YearEnd | CLOSING | 3% LIKE + name search | **Critical** - fragile |

### 2C. Document Numbering

**No centralized `DocumentNumberService`.** Each domain generates numbers independently:
- GR: Uses PostgreSQL sequence `goods_receipt_seq` via `NEXTVAL` - good
- Recruitment: Uses `lockForUpdate()` + MAX pattern - good  
- Other documents: Not audited for race safety

### 2D. Approval Workflow Engine

[`HasApprovalWorkflow`](app/Shared/Concerns/HasApprovalWorkflow.php:28) trait exists but is **only used by `JobRequisition`**. It provides audit logging only, not workflow routing.

| Module | Approval Logic | Uses HasApprovalWorkflow? | Uses StateMachine? | Configurable? |
|---|---|---|---|---|
| Procurement (PR) | Hardcoded head/manager/VP | No | Yes | No |
| Procurement (PO) | Via StateMachine states | No | Yes | No |
| Leave | Hardcoded head/manager/GA/VP chain | No | Yes (unused) | No |
| Loan | Hardcoded head/manager/officer/VP chain | No | Yes (unused) | No |
| Payroll | HR/Acctg/VP chain in StateMachine | No | Yes | No |
| Budget | Via StateMachine | No | Yes | No |

**Finding: 5+ modules have duplicated approval logic with hardcoded role chains.**

### 2E. Concurrency & Race Conditions

| Operation | Has lockForUpdate? | Race Risk |
|---|---|---|
| Stock deduction (StockService) | **No** | **HIGH** - concurrent issues can go negative |
| Document numbering (GR) | Via PG sequence | Safe |
| Document numbering (Recruitment) | Yes (lockForUpdate) | Safe |
| Payroll processing | Via status machine | Low |
| Budget consumption | **No** | **HIGH** - concurrent POs could overspend |
| QC Quarantine stock moves | **No** | **HIGH** - direct increment/decrement |

### 2F. Idempotency Audit

| Operation | Idempotent? | Mechanism |
|---|---|---|
| Payroll JE posting | **Yes** | Source_type + source_id check in [`PayrollPostingService`](app/Domains/Payroll/Services/PayrollPostingService.php:40) |
| AP Invoice JE | **Yes** | `journal_entry_id IS NULL` check |
| AR Invoice JE | **Yes** | `journal_entry_id IS NULL` check |
| Depreciation run | **Yes** | Unique constraint on `(fixed_asset_id, fiscal_period_id)` |
| GR confirmation | **Yes** | Status check prevents re-confirm |
| Production cost posting | **Partial** | Uses source_type/source_id but no explicit idempotency guard |

### 2G. HasPublicUlid without SoftDeletes

**GOTCHA-15 violations found:**
| Model | Has HasPublicUlid | Has SoftDeletes |
|---|---|---|
| [`VendorFulfillmentNote`](app/Domains/AP/Models/VendorFulfillmentNote.php:29) | Yes | **No** |
| [`Budget/CostCenter`](app/Domains/Budget/Models/CostCenter.php:39) | Yes | **No** (only Auditable) |
| [`Budget/AnnualBudget`](app/Domains/Budget/Models/AnnualBudget.php:43) | Yes | **No** (only Auditable) |

---

## PHASE 3 - FRONTEND AUDIT KEY FINDINGS

### 3A. Stores
- Only 2 Zustand stores: `authStore.ts` and `uiStore.ts` - compliant

### 3B. Types & Schemas Coverage
- **Types:** 26 files covering all domains
- **Schemas:** 22 files covering most domains
- **Missing schemas:** No banking, client-order, or recruitment schemas found

### 3C. Known Frontend Type Mismatch
- [`fixed_assets.ts`](frontend/src/types/fixed_assets.ts:23): status includes `'under_maintenance'` but DB CHECK constraint uses `'impaired'` - **must be corrected to match DB**

---

## PHASE 4 - GOTCHA VERIFICATION

| Gotcha | Status | Evidence |
|---|---|---|
| GOTCHA-01: PO 'delivered' in canReceiveGoods | **FIXED** | [`PurchaseOrder::canReceiveGoods()`](app/Domains/Procurement/Models/PurchaseOrder.php:146) includes `'delivered'` |
| GOTCHA-02: VendorFulfillmentService in AP | **CONFIRMED** | Located at [`app/Domains/AP/Services/VendorFulfillmentService.php`](app/Domains/AP/Services/VendorFulfillmentService.php) |
| GOTCHA-03: Direct StockBalance manipulation | **VIOLATION** | [`QuarantineService`](app/Domains/QC/Services/QuarantineService.php:52) uses `StockBalance::firstOrCreate` + `increment/decrement` |
| GOTCHA-05: Vendor portal raw JSON | **NOT VERIFIED** | Need to check vendor portal controller |
| GOTCHA-07: Notification without fromModel | **CLEAN** | Zero instances of `new...Notification(` |
| GOTCHA-09: pnpm-lock.yaml at root | **CORRECT** | `pnpm-workspace.yaml` at repo root |
| GOTCHA-10: asset_code in PHP | **NOT VERIFIED** | Need factory check |
| GOTCHA-11: under_maintenance vs impaired | **STILL BROKEN** | [`fixed_assets.ts:23`](frontend/src/types/fixed_assets.ts:23) uses `under_maintenance` |
| GOTCHA-13: GL silent skip | **STILL BROKEN** | [`FixedAssetService:138`](app/Domains/FixedAssets/Services/FixedAssetService.php:138) silently skips |
| GOTCHA-14: api.ts 1500ms cooldown | **CONFIRMED** | [`api.ts:22`](frontend/src/lib/api.ts:22) `WRITE_COOLDOWN_MS = 1500` |
| GOTCHA-15: HasPublicUlid without SoftDeletes | **3 VIOLATIONS** | VendorFulfillmentNote, CostCenter, AnnualBudget |

---

## PHASE 5 - MASTER FINDINGS REGISTER

| ID | Phase | Domain | Finding | F-Principle | Severity | Fix |
|---|---|---|---|---|---|---|
| F-001 | 0B | Leave/Loan/Attendance | Direct status assignments bypass existing StateMachines (28 instances) | F-12 | **Critical** | Route all status changes through StateMachine.transition() |
| F-002 | 0C | All posting modules | No account_mapping table - 20+ hardcoded GL account lookups | F-05 | **Critical** | Create account_mapping table + migration + seeder + admin UI |
| F-003 | 0B | QC | QuarantineService directly manipulates StockBalance bypassing StockService | F-04, F-08 | **Critical** | Refactor to use StockService::receive()/issue() |
| F-004 | 2E | Inventory | StockService has no lockForUpdate - concurrent issues can create negative stock | F-06 | **Critical** | Add pessimistic locking in StockService |
| F-005 | 0D | FixedAssets | Depreciation/disposal silently skips GL posting when accounts null | F-07 | **High** | Throw DomainException instead of silent skip |
| F-006 | 3C | FixedAssets | Frontend type under_maintenance vs DB impaired mismatch | F-10 | **High** | Change frontend type to match DB CHECK |
| F-007 | 2D | Multiple | Approval workflows hardcoded per module - 5+ modules duplicate logic | F-02 | **High** | Expand HasApprovalWorkflow into a configurable engine |
| F-008 | 2G | AP/Budget | 3 models use HasPublicUlid without SoftDeletes | - | **High** | Add SoftDeletes trait to VendorFulfillmentNote, CostCenter, AnnualBudget |
| F-009 | 0B | Admin | ChartOfAccountsController and BackupController have DB:: calls | F-01 | **Medium** | Move logic to service classes |
| F-010 | 0D | HR/Payroll | No auto-enrollment of new hires into next payroll cutoff | F-09 | **Medium** | Add listener on EmployeeActivated |
| F-011 | 0D | HR/Loan | Employee separation does not trigger loan balance due | F-09 | **Medium** | Add listener on EmployeeResigned |
| F-012 | 0D | Maintenance | Spare parts issuance does not use StockService | F-04 | **Medium** | Integrate WorkOrderService with StockService |
| F-013 | 2C | Multiple | No centralized DocumentNumberService | F-01 | **Medium** | Create centralized service with lockForUpdate |
| F-014 | 2A | Payroll | Government rate tables have no admin UI | F-01 | **Medium** | Build admin pages for SSS/PH/PagIBIG/BIR tables |
| F-015 | 0D | Procurement | No GR -> Accounting JE posting (inventory debit / AP credit) | F-04 | **Medium** | GR confirm should post inventory recognition JE |
| F-016 | 1.1 | Procurement | No return-to-supplier / GR reversal workflow | F-03 | **Medium** | Add GR reversal + debit memo + stock OUT |
| F-017 | 2E | Budget | Budget consumption check has no lockForUpdate | F-06 | **Medium** | Add pessimistic locking |
| F-018 | 0D | Mold/Production | Mold shot count not auto-incremented on production run | F-09 | **Low** | Add event listener |
| F-019 | 0D | Maintenance/FA | Maintenance jobs dont update asset service history | F-09 | **Low** | Add equipment-to-asset linkage |
| F-020 | 2A | Multiple | No UOM conversion table | F-01 | **Low** | Future enhancement |

---

## PHASE 6 - DELIVERABLES

### 6A. Module Flexibility Scorecard

| Domain | Scenario /10 | Config /10 | Integration /10 | StateMachine /10 | Frontend /10 | Total /50 |
|---|---|---|---|---|---|---|
| Accounting | 7 | 6 | 8 | 5 (no SM for JE) | 7 | 33 |
| AP | 7 | 5 | 8 | 7 | 7 | 34 |
| AR | 6 | 5 | 8 | 7 | 7 | 33 |
| Attendance | 5 | 6 | 7 | 3 (no SM) | 6 | 27 |
| Budget | 5 | 5 | 7 | 7 | 6 | 30 |
| CRM | 6 | 5 | 7 | 7 | 7 | 32 |
| Dashboard | 6 | 5 | N/A | N/A | 7 | 18/30 |
| Delivery | 6 | 5 | 7 | 7 | 6 | 31 |
| FixedAssets | 5 | 6 | 4 | 3 (no SM) | 5 | 23 |
| HR | 7 | 6 | 6 | 8 | 7 | 34 |
| Inventory | 8 | 7 | 8 | 7 | 7 | 37 |
| Leave | 6 | 6 | 7 | 4 (SM unused) | 6 | 29 |
| Loan | 6 | 5 | 6 | 4 (SM unused) | 6 | 27 |
| Maintenance | 5 | 5 | 4 | 7 | 6 | 27 |
| Mold | 4 | 4 | 3 | 3 (no SM) | 5 | 19 |
| **Payroll** | **9** | **9** | **9** | **10** | **8** | **45** |
| Procurement | 8 | 7 | 8 | 9 | 8 | 40 |
| Production | 7 | 6 | 8 | 8 | 7 | 36 |
| QC | 7 | 6 | 6 | 8 | 6 | 33 |
| Sales | 6 | 6 | 7 | 8 | 7 | 34 |
| Tax | 5 | 5 | 7 | 3 (no SM) | 5 | 25 |

### 6B. Fix Priority Queue

```
P1: F-001 - Direct status assignments bypass StateMachines (Leave, Loan, Attendance)
     Reason: Panelist will ask "show me your state machine" and find it's unused
     
P2: F-002 - Create account_mapping table for all GL postings
     Reason: Panelist will ask "what if Finance needs to remap an account?"
     
P3: F-004 - Add lockForUpdate to StockService
     Reason: Concurrent stock operations can corrupt data
     
P4: F-003 - QuarantineService must use StockService
     Reason: Audit trail gap - stock moves without ledger entries
     
P5: F-005 - FixedAssets GL silent skip -> throw DomainException  
     Reason: Known gotcha, panelist will test this
     
P6: F-006 - Fix frontend type under_maintenance -> impaired
     Reason: Frontend/DB mismatch will cause runtime errors
     
P7: F-008 - Add SoftDeletes to 3 models with HasPublicUlid
     Reason: Trait requirement violation
     
P8: F-007 - Expand HasApprovalWorkflow into configurable engine
     Reason: Addresses F-02 flexibility principle
     
P9: F-013 - Centralized DocumentNumberService
     Reason: Prevents duplicate numbers under load
     
P10: F-014 - Admin UI for government rate tables
      Reason: Panelist will ask "how do you update SSS rates?"
```

### 6C. Panelist Pressure Test

| Domain | Dangerous Question | Current State | Risk |
|---|---|---|---|
| Procurement | "What happens if 110 units delivered vs PO 100?" | Blocks with `GR_QTY_EXCEEDS_PENDING` | **Low** - handled |
| Payroll | "What if SSS rate changes - do you redeploy?" | Rate tables in DB with effective_date | **Low** - handled |
| Accounting | "Can someone edit a JE after period is closed?" | FiscalPeriod has lock_status, JE checks period | **Low** |
| FixedAssets | "What happens if GL account not configured?" | **Silently skips JE posting** | **HIGH** - no error thrown |
| Inventory | "Can stock go negative?" | Hard block, not configurable | **Medium** - inflexible |
| Production | "Which BOM version was used for this run?" | BOM FK on ProductionOrder, but no version snapshot | **Medium** |
| QC | "What happens to stock when QC rejects a lot?" | Quarantine exists but **bypasses StockService** | **HIGH** - no audit trail |
| Leave/Loan | "Show me your state machine for leave approval" | StateMachine exists but **service doesn't use it** | **CRITICAL** - embarrassing |
| Sales | "What if customer returns 30 units from 100?" | No return/RMA workflow implemented | **Medium** |
| AP | "How does Finance remap GL accounts?" | **Hardcoded account codes** | **HIGH** - requires code change |
| Budget | "What if two people approve POs against the same budget simultaneously?" | **No lock on budget check** | **HIGH** - race condition |
| HR | "What happens to loans when employee separates?" | **Nothing** - no integration | **Medium** |

### 6D. Top 5 Things to Fix Before Thesis Defense

1. **Wire up the existing StateMachines in Leave, Loan, and Attendance services.** The state machines exist with proper TRANSITIONS constants but the services do raw `$model->status = 'x'` instead. A panelist seeing this will question the entire architecture claim. Every `->status =` assignment must route through `StateMachine::transition()`.

2. **Create an `account_mapping` table and refactor all 20+ hardcoded GL lookups.** Every module that posts journal entries uses `ChartOfAccount::where('code', '2001')` or worse, LIKE patterns. Build the table with columns `module`, `event`, `account_id`, add a seeder with current defaults, and replace every hardcoded lookup. This is the single most visible flexibility gap.

3. **Add `lockForUpdate()` to `StockService::issue()` and `StockService::receive()`.** The current code reads balance then writes without a lock. Two concurrent material requisitions could both pass the balance check and create negative stock. This is a data integrity bug.

4. **Fix FixedAssets GL silent skip to throw DomainException.** Lines 138-142 and 223-225 of [`FixedAssetService`](app/Domains/FixedAssets/Services/FixedAssetService.php) silently skip journal entry creation when GL accounts are null. Change to: `throw new DomainException('GL accounts not configured...', 'FA_GL_NOT_CONFIGURED', 422)`. This is a documented gotcha that a panelist familiar with the codebase will specifically test.

5. **Fix the 3 HasPublicUlid-without-SoftDeletes models and the frontend type mismatch.** `VendorFulfillmentNote`, `CostCenter`, and `AnnualBudget` violate the documented trait requirement. The [`fixed_assets.ts`](frontend/src/types/fixed_assets.ts:23) type using `under_maintenance` instead of `impaired` will cause API response mismatches. Both are quick fixes with high impact.
