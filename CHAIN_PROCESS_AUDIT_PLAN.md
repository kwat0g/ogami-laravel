# Chain Process Integrity Audit — Execution Plan

## Context

This audit is driven by thesis defense readiness. The goal is to find every place where the ERP allows something it should not — records created without upstream process steps, status transitions with no frontend exit path, and missing chain links between domain modules. A panelist who asks "can I create an invoice without delivering anything?" must get the right answer.

The audit prompt defines 5 phases. Many findings from prior fix rounds (C1, C2, C3, H1, H5) are already resolved — the state machines and service guards exist. This plan focuses on **what remains broken** based on actual code reading.

---

## Phase 0 — Discovery (Commands to Run)

### 0A. Map All POST Routes
```bash
php artisan route:list --method=POST --json | python3 -c "
import sys, json
routes = json.load(sys.stdin)
for r in routes:
    if 'api/' in r.get('uri',''):
        print(f\"POST /{r['uri']}  →  {r.get('action','')}\")
" | sort
```

### 0B. Map All Action Routes (approve/reject/submit/etc.)
```bash
php artisan route:list --method=POST,PATCH --json | python3 -c "
import sys, json
routes = json.load(sys.stdin)
keywords = ['approve','reject','submit','confirm','post','publish',
            'dispatch','deliver','complete','cancel','restore','void',
            'release','close','disburse','process','generate']
for r in routes:
    uri = r.get('uri','')
    if 'api/' in uri:
        for kw in keywords:
            if kw in uri.lower():
                print(f\"{r.get('method','')} /{uri}\")
                break
" | sort
```

### 0C. Cross-reference Frontend API Calls
Search `frontend/src/hooks/` for all `api.post`, `api.patch`, `api.put`, `api.delete` calls and compare against backend routes.

### 0D. All 22 StateMachine TRANSITIONS (already mapped)
Files in `app/Domains/*/StateMachines/` — all 22 already identified with their transition maps.

---

## Phase 1 — Chain Integrity Audit (11 Chains)

### Already-Fixed Items (Verified in Code)
These were flagged in prior audits but are NOW FIXED — confirm and report as CORRECT:

| ID | Fix | Evidence |
|----|-----|----------|
| C1 | MaterialRequisitionStateMachine exists | `app/Domains/Inventory/StateMachines/MaterialRequisitionStateMachine.php` — 7-stage workflow |
| C2 | GoodsReceiptStateMachine exists | `app/Domains/Procurement/StateMachines/GoodsReceiptStateMachine.php` — 8-status workflow |
| C3 | Payroll GL posting is atomic | `PayrollPostingService.php:76` — `DB::transaction()` wraps all writes |
| H1 | AR has approval workflow | `CustomerInvoiceStateMachine.php` — draft→submitted→approved chain |
| H5 | Payroll posting has status guard | `PayrollPostingService.php:37` — `POSTABLE_STATUSES` constant |

### Remaining Chain Issues to Audit (Read Service + FormRequest + Controller for Each)

#### 1A. SO → Delivery Receipt
- **Files**: `StoreDeliveryReceiptRequest.php`, `DeliveryReceiptService::store()`, `delivery.php` routes
- **Known Issue**: `StoreDeliveryReceiptRequest` has NO `sales_order_id` and NO `delivery_schedule_id` required. Both vendor_id and customer_id are nullable. DR is fully standalone.
- **Mitigating Factor**: Listeners `CreateDeliveryReceiptOnProductionComplete` and `CreateDeliveryReceiptOnOqcPass` auto-create DRs. But manual creation is unrestricted.
- **Audit**: Verify if `DeliveryReceiptService::store()` enforces any chain. Check if SO confirmation auto-creates DR draft.

#### 1B. DR → Customer Invoice
- **Files**: `CreateCustomerInvoiceRequest.php`, `CustomerInvoiceService::create()`, `ar.php` routes
- **Known Issue**: `CreateCustomerInvoiceRequest` has NO `delivery_receipt_id`. The service accepts it optionally (HIGH-001 comment). Revenue can be recognized without proof of delivery.
- **Mitigating Factor**: Listener `CreateCustomerInvoiceOnShipmentDelivered` auto-creates invoice on shipment delivery. Service validates DR status if provided.
- **Audit**: Verify the listener is registered in EventServiceProvider/AppServiceProvider. Check if manual creation bypasses DR requirement.

#### 1C. PO → GR → Vendor Invoice
- **Files**: `CreateVendorInvoiceRequest.php`, `VendorInvoiceService::create()`, `CreateApInvoiceOnThreeWayMatch.php`
- **Known Issue**: `CreateVendorInvoiceRequest` has NO `purchase_order_id`. Manual AP invoice creation without PO is possible.
- **Mitigating Factor**: Listener `CreateApInvoiceOnThreeWayMatch` auto-creates invoice. Frontend has `useCreateInvoiceFromPO()` hook. But standalone creation endpoint exists.
- **GR chain**: `StoreGoodsReceiptRequest` DOES require `purchase_order_id` — this link is CORRECT.

#### 1D. Production Order Chain
- **Files**: `StoreProductionOrderRequest.php`, `ProductionOrderService`, production.php routes
- **Known Issue**: `delivery_schedule_id` is nullable. No `sales_order_id` in request at all. Production can be standalone.
- **Audit**: Check if this is by design (make-to-stock vs make-to-order).

#### 1E. Payslip Chain
- **Audit**: Search for POST /payslips route. Verify payslips only created by payroll computation pipeline (Step17NetPay), never manually.

#### 1F. Journal Entry Chain
- **Files**: `CreateJournalEntryRequest.php`, `JournalEntryService`
- **Known**: Manual JE creation is valid (for adjustments) but needs no `source_type` field. JE has strong validation (OpenFiscalPeriodRule, LeafAccountRule, min 2 lines, debit/credit exclusivity).
- **Audit**: Verify if manual JEs require higher approval than system-generated ones. Check `source_type`/`source_id` nullable.

#### 1G. Attendance Chain
- **Files**: `AttendanceLogController`, `AttendanceTimeService`, `RecordLeaveAttendanceCorrection.php`
- **Audit**: Verify listener `RecordLeaveAttendanceCorrection` is registered. Check if HR can directly create/edit attendance without correction request.

#### 1H. Leave → Payroll Chain
- **Audit**: Read `Step03Attendance` to see if it reads LWOP from attendance or leave directly. Verify `RecordLeaveAttendanceCorrection` listener fires on leave approval.

#### 1I. Loan → Payroll Chain
- **Files**: `Step15LoanDeductions`, `LoanAmortizationService`
- **Audit**: Check if loan deduction updates amortization schedule status. Verify no double-deduction guard.

#### 1J. Fixed Assets → GL Chain
- **Files**: `FixedAssetService`, depreciation methods
- **Known Issue (CLAUDE.md)**: Depreciation and disposal silently skip JE posting if GL accounts are null.
- **Audit**: Verify if this is still the case or fixed.

#### 1K. QC → Inventory Chain
- **Files**: `QuarantineService`, `StockService`, QC listeners
- **Known Issue (CLAUDE.md)**: QuarantineService may bypass StockService.
- **Mitigating Factor**: Listeners `CreateIqcInspectionOnGrSubmit`, `QuarantineItemsOnGrQcSubmit`, `UpdateGrOnInspectionResult` exist.
- **Audit**: Read QuarantineService to verify it uses StockService.

---

## Phase 2 — Manual Creation Violations

For each record in the audit table, read the FormRequest and Service to fill in:

| Record | Should Come From | Manual Route? | FK Required? | Status Validated? |
|--------|-----------------|---------------|-------------|-------------------|
| DeliveryReceipt | Confirmed SO | YES (POST /delivery/receipts) | NO — no SO/DS FK | NO |
| CustomerInvoice | Delivered DR | YES (POST /ar/invoices) | NO — DR optional | YES (if DR provided) |
| VendorInvoice | Matched GR+PO | YES (POST /accounting/ap/invoices) | NO — no PO FK | NO |
| GoodsReceipt | Acknowledged PO | YES (POST /procurement/goods-receipts) | YES — PO required | Need to check |
| ProductionOrder | SO/DS | YES (POST /production/orders) | NO — DS nullable | NO |
| Payslip | Published PayrollRun | Need to verify | - | - |
| StockLedgerEntry | StockService only | Need to verify | - | - |
| NCR | Failed Inspection | YES (POST /qc/ncrs) | YES — inspection_id required | Need to check |
| CAPA | NCR | Need to verify (auto via listener) | - | - |
| QC Inspection | GR or PO | YES (POST /qc/inspections) | NO — both nullable | NO |

**Key files to read for each**: The FormRequest `rules()` method + Service `store()`/`create()` method.

---

## Phase 3 — Frontend Stuck Process Audit

### Method
For every StateMachine transition, verify a frontend mutation exists that calls the corresponding backend route.

### Key Areas to Check

**Delivery Receipt**: draft→confirmed→partially_delivered→delivered
- Backend: confirm, partial-deliver, deliver routes exist
- Frontend: `useConfirmDeliveryReceipt`, `useMarkPartiallyDelivered`, `useMarkDelivered` exist
- Check: Are buttons rendered on the detail page?

**Customer Invoice**: draft→submitted→approved→partially_paid→paid
- Backend: approve route exists. Is there a "submit" route?
- Frontend: `useApproveInvoice` exists. Is there a `useSubmitInvoice`?
- Check: The state machine has draft→submitted but does a submit endpoint exist?

**Sales Order**: confirm, partial-deliver, deliver, invoiced, cancel routes
- Frontend: All mutations mapped. Check page rendering.

**Payroll Run**: Full 14-state workflow
- Frontend: Extensive wizard pages exist. Most thorough coverage.

**Budget**: submit, approve, reject
- Frontend: mutations exist. Check page rendering.

**Production Order**: release, start, complete, close, cancel, void, hold, resume
- Frontend: All mutations mapped. Check page rendering.

**Leave Request**: Full approval chain
- Frontend: Need to verify all approval buttons rendered.

**Loan**: Full 11-stage workflow
- Frontend: All mutations mapped. Check detail page.

### Missing Pages to Check
- Sales module: Only `QuotationMarginPage` found. Are there list/detail pages for quotations and sales orders?
- ISO module: Only `ISOController` found. What pages exist?
- Fixed Assets: Full page set exists?

---

## Phase 4 — Data Consistency (Tinker Queries)

Run the tinker commands from the prompt to check:
1. Orphaned DRs without SO
2. Orphaned invoices without PO
3. Orphaned payslips without run
4. JE lines without headers
5. Production orders without source
6. Leave approved but attendance not marked
7. Active employees not in payroll
8. Active loans without amortization
9. Stock balance vs ledger reconciliation
10. Trial balance (debits = credits)

---

## Phase 5 — Report Generation

Compile all findings into the exact report format specified in the prompt:
- Executive Summary
- Chain Integrity Results table
- Manual Creation Violations list
- Stuck Processes list
- Missing Frontend Pages
- Data Consistency Issues
- Fix Priority Queue (P1-Pn)
- Panelist Danger Questions

---

## Execution Order

1. **Run Phase 0 commands** — route:list to get definitive POST/action routes
2. **Phase 1 chain audits** — read each service/controller/request file for all 11 chains. Use parallel agents for independent chain audits.
3. **Phase 2** — fill in the manual creation violations table from Phase 1 findings
4. **Phase 3** — cross-reference StateMachine transitions with frontend hooks AND page components
5. **Phase 4** — run tinker queries for data consistency
6. **Phase 5** — compile the final report

### Critical Files to Read (Phase 1 Execution)

**Chain 1A (SO→DR)**:
- `app/Domains/Delivery/Services/DeliveryReceiptService.php` (already partially read)
- `app/Listeners/Delivery/CreateDeliveryReceiptOnProductionComplete.php`
- `app/Listeners/Delivery/CreateDeliveryReceiptOnOqcPass.php`
- `app/Domains/Sales/Services/SalesOrderService.php` — check if SO confirm triggers DR

**Chain 1B (DR→Invoice)**:
- `app/Listeners/AR/CreateCustomerInvoiceOnShipmentDelivered.php`
- `app/Providers/AppServiceProvider.php` or EventServiceProvider — verify listener registration

**Chain 1C (PO→GR→AP Invoice)**:
- `app/Listeners/Procurement/CreateApInvoiceOnThreeWayMatch.php`
- `app/Domains/Procurement/Services/ThreeWayMatchService.php`

**Chain 1G-1H (Leave→Attendance→Payroll)**:
- `app/Listeners/Attendance/RecordLeaveAttendanceCorrection.php`
- `app/Domains/Payroll/Pipeline/Step03Attendance.php`

**Chain 1I (Loan→Payroll)**:
- `app/Domains/Payroll/Pipeline/Step15LoanDeductions.php`

**Chain 1J (Fixed Assets→GL)**:
- `app/Domains/FixedAssets/Services/FixedAssetService.php`

**Chain 1K (QC→Inventory)**:
- `app/Domains/QC/Services/QuarantineService.php`

### Frontend Pages to Verify (Phase 3 Execution)
- `frontend/src/pages/delivery/DeliveryReceiptDetailPage.tsx` — confirm/deliver buttons
- `frontend/src/pages/ar/CustomerInvoiceDetailPage.tsx` — submit/approve buttons
- `frontend/src/pages/sales/` — check if quotation/SO list/detail pages exist
- `frontend/src/pages/hr/leave/LeaveDetailPage.tsx` — approval buttons
- `frontend/src/pages/hr/loans/LoanDetailPage.tsx` — approval chain buttons
- `frontend/src/pages/accounting/JournalEntryDetailPage.tsx` — submit/post buttons
- `frontend/src/pages/fixed-assets/` — depreciation/disposal buttons

---

## Verification

After producing the report:
1. Every finding must cite a specific file:line or route
2. Every "BROKEN" chain must have a reproducing scenario
3. Every "STUCK" process must identify the missing frontend button
4. Every fix must be an actionable code change, not a vague recommendation
5. Run `./vendor/bin/phpstan analyse` and `cd frontend && pnpm typecheck` to verify no regressions if any fixes are applied
