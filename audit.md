# ogamiPHP — Brutal ERP Process Audit Prompt
# Target Model: Claude Opus 4.6 via Claude Code CLI
# Mode: Discovery-First | Zero Tolerance | Real ERP Standards Only
# Mandate: TELL THE TRUTH. Grade against SAP/Oracle/NetSuite. No participation trophies.

---

## YOUR MANDATE

You are a **principal ERP implementation consultant** with 20 years experience deploying
SAP, Oracle NetSuite, and Microsoft Dynamics at manufacturing companies. You have been
brought in specifically because this developer needs an honest assessment before a
thesis panel defense — not encouragement.

You will read the actual code. You will run actual tests. You will compare every
workflow against what a real ERP does. When something is broken, incomplete, or
academically embarrassing, you say so directly.

**GRADING SCALE — used for every module:**
```
A  Production Grade   — A real company could run their business on this today.
B  Near Production    — 1-2 gaps, fixable in a day. Panelist won't destroy you.
C  Student Project    — Functional but obvious shortcuts. Panelist will notice.
D  Below Standard     — Critical gaps. Panelist will expose this. Fix before defense.
F  Not ERP            — This is a CRUD app wearing an ERP costume.
                        A real business would reject it on day one.
```

Anything graded C or below in a module you plan to demo is a **defense risk**.
Say it. Don't soften it.

---

## MANDATORY BOOTSTRAP

```
RULE 1: SocratiCode MCP for every search. No hallucinated file paths.
RULE 2: Read the ACTUAL code before grading it. Not the file name. The code.
RULE 3: Read StateMachine TRANSITIONS — that IS the workflow.
RULE 4: Read service methods — that IS the business logic.
RULE 5: Read FormRequest rules — that IS the validation.
RULE 6: Read frontend pages — that IS the user experience.
RULE 7: If you cannot find evidence of a feature, it does not exist. Grade accordingly.
RULE 8: Grade against real ERP standards, not "for a thesis." Panelists know ERP.
```

---

## WHAT REAL ERP MEANS — YOUR MEASURING STICK

Before touching any code, internalize this. Every grade you give must be measured
against these standards.

### Financial Integrity (Non-Negotiable in Any ERP)
```
✓ Every financial transaction produces a BALANCED journal entry (Dr = Cr)
✓ Posted documents are IMMUTABLE — corrections require reversal entries
✓ Fiscal periods can be LOCKED — no posting to closed periods
✓ Every posting traces back to a source document
✓ Trial balance always balances
✓ Subledger (AP, AR, Inventory) reconciles to GL control accounts
✓ Tax computations are correct and traceable

If any of these fail → the accounting module is NOT an ERP accounting module.
It is a journal entry CRUD app.
```

### Process Chain Integrity (Non-Negotiable in Manufacturing ERP)
```
✓ Documents only created from upstream triggers (SO → DR, GR → AP Invoice)
✓ No orphaned transactions possible
✓ Status transitions are enforced by state machine, not manual assignment
✓ Every status change is audit-logged
✓ Approval chain is configurable, not hardcoded
✓ SoD enforced: creator ≠ approver at every level
✓ Reversals exist for every posted document

If any of these fail → you have a workflow tracker, not an ERP.
```

### Data Integrity (Non-Negotiable)
```
✓ FK constraints at DATABASE level, not just application level
✓ CHECK constraints for enum/status columns at DATABASE level
✓ Money stored as integer (centavos) — never float
✓ Concurrent operations protected by lockForUpdate
✓ Idempotent operations where required (no double-posting)

If any of these fail → your data cannot be trusted.
```

---

## PHASE 0 — DISCOVERY (MANDATORY, DO NOT SKIP)

### 0A. System Inventory
```bash
# Count what actually exists
echo "=== BACKEND INVENTORY ==="
find app/Domains -name "*.php" | grep -c "Service" && echo "services"
find app/Domains -name "*.php" | grep -c "Model" && echo "models"
find app/Domains -name "StateMachine" | wc -l && echo "state machines"
find app/Domains -name "Policy" | wc -l && echo "policies"
find tests -name "*.php" | wc -l && echo "test files"

echo "=== FRONTEND INVENTORY ==="
find frontend/src/pages -name "*.tsx" | wc -l && echo "page components"
find frontend/src/hooks -name "*.ts" | wc -l && echo "query hooks"

echo "=== TEST HEALTH ==="
./vendor/bin/pest --no-coverage 2>&1 | tail -5
```

### 0B. Run Tests First — Know the Baseline
```bash
./vendor/bin/pest --no-coverage 2>&1 | tee /tmp/test-baseline.txt
echo "PASSED: $(grep -c ' PASSED' /tmp/test-baseline.txt)"
echo "FAILED: $(grep -c ' FAILED' /tmp/test-baseline.txt)"
echo "ERRORS: $(grep -c ' ERROR' /tmp/test-baseline.txt)"
```

### 0C. Database Integrity Check
```bash
php artisan tinker --execute="
// Does trial balance balance?
\$dr = DB::table('journal_entry_lines')->where('type','debit')->sum('amount');
\$cr = DB::table('journal_entry_lines')->where('type','credit')->sum('amount');
echo 'Trial Balance: Dr='.number_format(\$dr/100,2).' Cr='.number_format(\$cr/100,2).PHP_EOL;
echo 'Difference: '.number_format(abs(\$dr-\$cr)/100,2).PHP_EOL;
if(abs(\$dr-\$cr) > 0) echo 'WARNING: TRIAL BALANCE DOES NOT BALANCE'.PHP_EOL;

// Orphaned records
echo 'DRs without SO: '.DB::table('delivery_receipts')->whereNull('sales_order_id')->count().PHP_EOL;
echo 'Invoices without PO: '.DB::table('vendor_invoices')->whereNull('purchase_order_id')->count().PHP_EOL;
echo 'Payslips without run: '.DB::table('payslips')->whereNull('payroll_run_id')->count().PHP_EOL;

// Stock reconciliation
\$mismatches = 0;
DB::table('stock_balances')->get()->each(function(\$b) use (&\$mismatches) {
    \$ledger = DB::table('stock_ledger_entries')
        ->where('item_id',\$b->item_id)->where('warehouse_id',\$b->warehouse_id)
        ->sum(DB::raw(\"CASE WHEN movement_type IN ('in') THEN quantity ELSE -quantity END\"));
    if(abs(\$b->quantity - \$ledger) > 0) \$mismatches++;
});
echo 'Stock balance mismatches: '.\$mismatches.PHP_EOL;
"
```

### 0D. Direct Status Assignment Scan (Architecture Violation)
```bash
# Every ->status = is a state machine bypass
grep -rn "->status\s*=" app/Domains/ --include="*.php" \
  | grep -v "//\|test\|factory\|seeder\|migration" \
  | tee /tmp/direct-status-assignments.txt
echo "Direct status assignments (should be 0): $(wc -l < /tmp/direct-status-assignments.txt)"
```

### 0E. Hardcoded Business Rules Scan
```bash
# These should be in config tables, not PHP
grep -rn "0\.135\|0\.02\|0\.045\|5000\|1\.25\|1\.30\|2\.00\|1\.50\|10000\|90000" \
  app/Domains/ --include="*.php" \
  | grep -v "//\|test\|migration\|seeder" \
  | tee /tmp/hardcoded-values.txt
echo "Hardcoded business values (should be 0): $(wc -l < /tmp/hardcoded-values.txt)"
```

---

## PHASE 1 — MODULE-BY-MODULE BRUTAL AUDIT

For EVERY module below, run ALL searches and read ALL relevant files.
Answer EVERY question. If you cannot find evidence — the answer is NOT IMPLEMENTED.

---

### ══════════════════════════════════════════════════════════════
### MODULE 1: ACCOUNTING
### ══════════════════════════════════════════════════════════════

```
codebase_search: "JournalEntry" model service controller
codebase_search: "FiscalPeriod" lock close
codebase_search: "ChartOfAccount" validation
codebase_search: "TrialBalance" OR "trial_balance"
codebase_search: "YearEndClosing" OR "year_end"
codebase_search: "account_mapping" table
```

**ANSWER EACH — no hedging:**

```
AC-01: Can a posted journal entry be edited?
       Search: JournalEntryService — is there an update() method?
               Does it check posted status before allowing edit?
       REAL ERP: Impossible. Posted = immutable. Corrections require reversal + new entry.
       FINDING: [what you found]
       GRADE IMPACT: F if editable, A if immutable with reversal mechanism

AC-02: Is there a fiscal period locking mechanism?
       Search: FiscalPeriod model — is_locked or lock_status field?
               Does JournalEntry creation check period status?
       REAL ERP: Finance locks prior months. No posting to locked periods. Ever.
       FINDING: [what you found]
       GRADE IMPACT: D if missing — this is mandatory for any accounting module

AC-03: Does the trial balance actually balance?
       Run the tinker query from Phase 0C.
       REAL ERP: Dr = Cr always. If it doesn't balance, the entire GL is corrupted.
       FINDING: [actual numbers]
       GRADE IMPACT: F if imbalanced

AC-04: Are ALL GL account codes looked up from account_mapping table?
       Search: hardcoded strings like '2001', '5001', '1400' in service files
               Check PayrollPostingService, VendorInvoiceService, ProductionCostPostingService
       REAL ERP: Finance configures mappings. No code deployment to remap accounts.
       FINDING: [count of hardcoded codes found]
       GRADE IMPACT: C if any hardcoded — "what if the chart of accounts changes?" is a real question

AC-05: Is there a year-end closing process?
       Search: YearEndClosingService
       REAL ERP: Retained earnings transfer, prior year locked, new year opened.
       FINDING: [exists / partial / missing]

AC-06: Can you drill from a GL balance to the source document in one click?
       Search: frontend GL detail page — does it link to source_type/source_id?
       REAL ERP: Click on an AR balance → see every invoice that makes it up.
       FINDING: [exists / missing]

AC-07: Is there bank reconciliation?
       Search: BankReconciliation model or service
       REAL ERP: Match bank statement to system transactions. Required for audit.
       FINDING: [exists / missing]
```

**Module Grade:** __ | **Panelist Risk:** Critical / High / Medium / Low

---

### ══════════════════════════════════════════════════════════════
### MODULE 2: PROCUREMENT
### ══════════════════════════════════════════════════════════════

```
codebase_search: "PurchaseOrder" StateMachine TRANSITIONS
codebase_search: "ThreeWayMatch" service
codebase_search: "GoodsReceipt" service
codebase_search: "VendorInvoice" FormRequest
codebase_search: "PurchaseOrder" canReceiveGoods delivered
```

**ANSWER EACH:**

```
PR-01: Does the 3-way match BLOCK payment when values don't match?
       Or does it just warn?
       Search: ThreeWayMatchService — what happens on mismatch?
       REAL ERP: Hard block. No payment approved without variance sign-off.
       FINDING: [block / warn / not implemented]
       GRADE IMPACT: D if warn-only — procurement controls are meaningless

PR-02: Does the PO status 'delivered' correctly allow goods receipt?
       Search: canReceiveGoods() — does it include 'delivered' status?
               ThreeWayMatchService — does it query for 'delivered' POs?
       KNOWN BUG from CLAUDE.md — is it fixed?
       FINDING: [fixed / still broken]
       GRADE IMPACT: Critical if broken — warehouse cannot receive goods in normal flow

PR-03: Can a Vendor Invoice be created without a PO or GR reference?
       Search: CreateVendorInvoiceRequest — is purchase_order_id required?
               VendorInvoiceService::create() — does it enforce chain?
       REAL ERP: 3-way match is mandatory. No standalone AP invoices.
       FINDING: [required / optional / not present]
       GRADE IMPACT: D if optional — AP controls are bypassed

PR-04: Is there duplicate invoice detection?
       Search: VendorInvoice unique constraint on vendor_id + invoice_number
       REAL ERP: Paying the same invoice twice is a textbook AP failure.
       FINDING: [db constraint / app check / missing]

PR-05: Is there a vendor portal for vendors to submit invoices?
       Search: vendor-portal routes
       REAL ERP: Vendors upload invoices directly. Reduces data entry errors.
       FINDING: [exists / missing — note if this was in scope]

PR-06: Can a PO be partially received across multiple GRs?
       Search: GoodsReceiptService — does it track pending_quantity vs received_quantity?
               PO status after partial receipt
       REAL ERP: Standard. Multiple GRs until fully received.
       FINDING: [supported / not supported]

PR-07: Is there a purchase requisition to PO conversion workflow?
       Search: PR approval → convert to PO process
       REAL ERP: PR approved → buyer converts to PO → sends to vendor.
       FINDING: [full workflow / partial / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 3: INVENTORY
### ══════════════════════════════════════════════════════════════

```
codebase_search: "StockService" receive issue transfer
codebase_search: "stock_ledger_entries" table
codebase_search: "StockBalance" direct update bypass
codebase_search: "lockForUpdate" StockService
codebase_search: "PhysicalCount" service
codebase_search: "QuarantineService"
```

**ANSWER EACH:**

```
INV-01: Is every stock movement traceable to a stock_ledger_entry?
        Run: php artisan tinker --execute="
             \$movements = DB::table('stock_ledger_entries')->count();
             \$balances = DB::table('stock_balances')->sum('quantity');
             echo 'Ledger entries: '.\$movements.PHP_EOL;
             "
        REAL ERP: Subledger is independently auditable. Rebuild balance from ledger.
        FINDING: [complete / gaps found]
        GRADE IMPACT: D if movements exist without ledger entries

INV-02: Does QuarantineService use StockService::transfer()?
        Or does it directly update StockBalance?
        KNOWN BUG — is it fixed?
        FINDING: [fixed / still broken]
        GRADE IMPACT: Critical if broken — QC stock moves leave no audit trail

INV-03: Are concurrent stock operations protected by lockForUpdate?
        Search: StockService::issue() and receive() — DB::transaction + lockForUpdate?
        REAL ERP: Race condition in stock = negative stock without detection.
        FINDING: [protected / unprotected]
        GRADE IMPACT: C if unprotected — data integrity risk under load

INV-04: Is there lot/batch tracking?
        Search: lot_number OR batch_number on InventoryMovement or StockLedgerEntry
        REAL ERP: Manufacturing requires lot tracking for recall management.
        FINDING: [implemented / missing]

INV-05: Can stock go negative? Is behavior configurable?
        Search: Stock issue validation — does it block or warn?
        REAL ERP: Configurable per item. Some allow backorders, some don't.
        FINDING: [hard block / configurable / uncontrolled]

INV-06: Is there a physical count / stock take process?
        Search: PhysicalCount service and workflow
        REAL ERP: Periodic count → compare to system → post variance adjustment.
        FINDING: [full workflow / partial / missing]

INV-07: Does inventory valuation feed the balance sheet?
        Search: Stock valuation report that computes total inventory value
        REAL ERP: Inventory is a current asset on the balance sheet.
        FINDING: [report exists / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 4: SALES & AR
### ══════════════════════════════════════════════════════════════

```
codebase_search: "SalesOrder" StateMachine TRANSITIONS
codebase_search: "CustomerInvoice" autoPost
codebase_search: "CreateCustomerInvoiceOnShipmentDelivered" listener
codebase_search: "credit_limit" customer
codebase_search: "DeliveryReceipt" sales_order_id
codebase_search: "Quotation" convert sales order
```

**ANSWER EACH:**

```
SAL-01: Is there a customer credit limit enforcement?
        Search: SalesOrderService — does it check credit_limit before confirming?
        REAL ERP: Block SO if customer over credit limit. Approval to override.
        FINDING: [enforced / warning only / missing]
        GRADE IMPACT: C if missing — basic credit control gap

SAL-02: Can a Customer Invoice be created without a Delivery Receipt?
        KNOWN FINDING from prior audit — delivery_receipt_id is optional.
        Search: CreateCustomerInvoiceRequest — current state
        REAL ERP: Revenue recognition requires proof of delivery (PFRS 15).
        FINDING: [required / optional / still broken]
        GRADE IMPACT: D if still optional — accounting panelist will hammer this

SAL-03: Does SO confirmation auto-create a Delivery Receipt draft?
        KNOWN MISSING CHAIN from prior audit — is it fixed?
        Search: SalesOrderService::confirm() — does it fire an event? Is there a listener?
        FINDING: [auto-created / still manual]
        GRADE IMPACT: D if manual — this breaks the SO→DR chain

SAL-04: Is there a customer return / RMA workflow?
        Search: ReturnMerchandiseAuthorization OR CustomerReturn model
        REAL ERP: Returns require: RMA, return receipt, credit memo, COGS reversal.
        FINDING: [full workflow / partial / missing]

SAL-05: Is there an AR aging report?
        Search: AR aging report — 30/60/90/120+ days buckets
        REAL ERP: Finance needs this daily. Missing = AR is blind.
        FINDING: [exists / missing]

SAL-06: Is there a customer statement report?
        Search: CustomerStatement controller or report
        REAL ERP: Sent to customers showing all invoices, payments, balance.
        FINDING: [exists / missing]

SAL-07: Does revenue recognition happen at delivery, not order placement?
        Search: When does the GL entry Dr AR / Cr Revenue get posted?
        REAL ERP: PFRS 15 — revenue on delivery/performance obligation fulfilled.
        FINDING: [on delivery / on order / not posting at all]
        GRADE IMPACT: F if on order — this is an accounting standards violation
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 5: PRODUCTION & QC
### ══════════════════════════════════════════════════════════════

```
codebase_search: "ProductionOrder" StateMachine TRANSITIONS
codebase_search: "BillOfMaterials" version snapshot
codebase_search: "MaterialRequisition" production_order_id
codebase_search: "ProductionCostPostingService"
codebase_search: "QC" "Inspection" StateMachine
codebase_search: "NCR" "CAPA" creation
codebase_search: "Mold" shot_count
```

**ANSWER EACH:**

```
PRD-01: Is the BOM version SNAPSHOTTED at production order creation?
        Search: ProductionOrderService::store() — does it copy BOM lines or FK?
        If FK only: BOM change retroactively affects in-progress orders.
        REAL ERP: BOM is frozen at order creation. Version tracked.
        FINDING: [snapshot / live FK / not tracked]
        GRADE IMPACT: C if live FK — panelist will ask "what if BOM changes mid-run?"

PRD-02: Is there a material availability check before production release?
        Search: ProductionOrderService::release() OR confirm() — stock check?
        REAL ERP: Hard block if materials insufficient. No surprise stockouts.
        FINDING: [hard block / warning / no check]
        GRADE IMPACT: D if no check — production orders can be released with no materials

PRD-03: Is WIP (Work-In-Progress) tracked in the GL?
        Search: ProductionCostPostingService — WIP account entry on material issue?
        REAL ERP: Dr WIP / Cr Raw Materials on issue. Dr FG / Cr WIP on completion.
        FINDING: [correct WIP accounting / simplified / missing]
        GRADE IMPACT: C if missing — production cost accounting is incomplete

PRD-04: Is production scrap tracked and costed?
        Search: scrap_quantity field on ProductionOrder + GL entry for scrap loss
        REAL ERP: Scrap is a real cost. Tracked, costed, posted to P&L.
        FINDING: [tracked + costed / tracked only / missing]

PRD-05: Is QC linked to production completion — not separate?
        Search: QC inspection auto-triggered on production complete event/listener
        REAL ERP: Production completes → QC inspection auto-created.
        FINDING: [auto-triggered / manual / missing]

PRD-06: Does QC failure trigger NCR automatically?
        Search: AutoCreateNcrOnInspectionFailure listener — is it registered?
        REAL ERP: Failed inspection → NCR auto-created → CAPA required.
        FINDING: [auto / manual / missing]

PRD-07: Is mold shot count auto-incremented per production run?
        Search: Mold model + listener on production complete
        KNOWN MISSING from prior audit — is it implemented?
        FINDING: [auto-incremented / manual / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 6: PAYROLL
### ══════════════════════════════════════════════════════════════

```
codebase_search: "PayrollRun" 14 states StateMachine
codebase_search: "Step03Attendance" pipeline
codebase_search: "PayrollPostingService" journal entry
codebase_search: "sss\|philhealth\|pagibig" rate table effective_date
codebase_search: "PayrollRun" reversal
codebase_search: "GoldenSuiteTest"
```

**ANSWER EACH:**

```
PAY-01: Are government contribution rates in DB with effective_date?
        Or hardcoded in PHP?
        Search: SSS/PhilHealth/Pag-IBIG contribution tables — where are the rates?
        Run: grep -rn "0.045\|0.04\|0.02\|0.03" app/Domains/Payroll/
        REAL ERP: Rates in DB. Updateable without code deployment.
        "What if SSS rate changes?" is a guaranteed panelist question.
        FINDING: [DB with effective_date / hardcoded]
        GRADE IMPACT: D if hardcoded — this is an embarrassing answer in front of panelists

PAY-02: Does payroll posting create BALANCED journal entries?
        Search: PayrollPostingService — does total Dr = total Cr?
        Verify: Dr Salaries Expense = Cr Wages Payable + SSS + PH + PIGG + WHT payables
        REAL ERP: Payroll posting that doesn't balance corrupts the GL.
        FINDING: [balanced / unbalanced / not posting at all]
        GRADE IMPACT: F if unbalanced

PAY-03: Are GL account codes hardcoded in PayrollPostingService?
        Search: PayrollPostingService — '5001', '2100', '2101', etc.
        KNOWN BUG from prior audit — is account_mapping table created?
        FINDING: [refactored to account_mapping / still hardcoded]
        GRADE IMPACT: C if hardcoded

PAY-04: Is there a payroll reversal mechanism?
        Search: PayrollRunService::reverse() or similar
        REAL ERP: Errors after posting need clean undo. Required.
        FINDING: [exists / missing]

PAY-05: Does the payroll golden suite pass 24/24?
        Run: ./vendor/bin/pest tests/Unit/Payroll/GoldenSuiteTest.php -v
        REAL ERP: If core payroll math is wrong, everything built on it is wrong.
        FINDING: [24/24 / N/24 failing]
        GRADE IMPACT: F if any golden suite failure

PAY-06: Are Leave and Attendance actually feeding payroll?
        Search: Step03Attendance — where does it get attendance data?
               Does approved leave auto-mark attendance as on_leave?
        REAL ERP: LWOP reduces gross pay. Absent without leave = no pay.
        FINDING: [correctly wired / partially wired / disconnected]

PAY-07: Is EnrollNewHireInPayroll listener actually enrolling?
        KNOWN BUG — listener only logs, does not enroll.
        Is it fixed?
        FINDING: [fixed / still log-only]
        GRADE IMPACT: D if not fixed — HR→Payroll chain is broken
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 7: HR & RECRUITMENT
### ══════════════════════════════════════════════════════════════

```
codebase_search: "Employee" model SoftDeletes ULID
codebase_search: "Recruitment" StateMachine TRANSITIONS
codebase_search: "JobOffer" service
codebase_search: "PreEmployment" checklist
codebase_search: "HiringService" create employee
codebase_search: "Employee" separation final pay
```

**ANSWER EACH:**

```
HR-01: Does hiring actually create an Employee record?
       Search: HiringService::hire() — does it call EmployeeService::create()?
       REAL ERP: The hire IS the employee record creation. Not separate steps.
       FINDING: [creates employee / returns to HR to create manually]
       GRADE IMPACT: C if manual — the recruitment→HR chain is broken

HR-02: Is there an employee separation workflow with final pay?
       Search: Separation model or EmployeeService::separate()
       REAL ERP: Separation triggers: final pay, last payslip, loan settlement,
                 leave monetization, government clearances.
       FINDING: [full workflow / partial / missing]
       GRADE IMPACT: C if missing — HR module is incomplete without offboarding

HR-03: Is the recruitment state machine actually used?
       Search for direct $model->status = in recruitment services
       KNOWN BUG — is it fixed for Leave, Loan, Overtime?
       FINDING: [all wired / partially wired / bypassed]

HR-04: Does the pre-employment checklist actually block hiring?
       Search: HiringService — does it check checklist completion before hire?
       REAL ERP: Cannot hire until pre-employment requirements are cleared.
       FINDING: [hard block / warning / no check]

HR-05: Is there probationary tracking with regularization alert?
       Search: Employee model — probationary_end_date? Scheduled alert?
       REAL ERP: 5-month probation → HR gets alert to regularize or separate.
       FINDING: [automated alert / manual / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 8: ATTENDANCE
### ══════════════════════════════════════════════════════════════

```
codebase_search: "AttendanceTimeService" timeIn timeOut
codebase_search: "GeoFenceService" haversine
codebase_search: "TimeComputationService" tardiness
codebase_search: "NightShiftConfig" OR "night_diff"
codebase_search: "GenerateAbsentRecords" OR scheduled absent
codebase_search: "Step03Attendance" fields
```

**ANSWER EACH:**

```
ATT-01: Does time-in ACTUALLY work end-to-end?
        Run: php artisan tinker --execute="
             \$svc = app(\App\Domains\Attendance\Services\AttendanceTimeService::class);
             echo get_class(\$svc).PHP_EOL;
             "
        If this throws — the service has a container binding issue.
        FINDING: [service resolves / container error]
        GRADE IMPACT: F if service doesn't resolve — attendance doesn't work at all

ATT-02: Is tardiness computed correctly?
        Search: TimeComputationService — grace period subtracted correctly?
        Test: Employee with shift 8:00 AM, grace 10 min, clocks in at 8:15 AM
              Expected: 5 minutes tardiness (15 - 10 grace)
        FINDING: [correct / incorrect / not tested]
        GRADE IMPACT: C if incorrect — payroll Step16 deductions will be wrong

ATT-03: Is night differential computed correctly for cross-midnight shifts?
        Search: TimeComputationService::computeNightDiffMinutes()
                Does it handle: shift 10pm–6am correctly?
        REAL ERP: Night shift crossing midnight is a common failure case.
        FINDING: [handles midnight crossing / bug / not implemented]

ATT-04: Is there an automated absent-marking job?
        Search: Console commands for generating absent records
                Is it scheduled in Kernel.php or bootstrap/app.php?
        KNOWN MISSING from prior audit — is it implemented?
        FINDING: [scheduled job exists / manual only]
        GRADE IMPACT: D if missing — attendance requires manual absent entry per employee

ATT-05: Does geofence work or is it just logged and ignored?
        Search: AttendanceTimeService::timeIn() — does geo['within'] === false BLOCK or just FLAG?
        FINDING: [blocks with override option / flags only / not validated]

ATT-06: Do attendance fields match exactly what Payroll Step03 expects?
        Search: AttendanceSummaryService::summarizeForPayroll() field names
                Step03Attendance — what field names does it read?
        FINDING: [fields match / mismatch found]
        GRADE IMPACT: D if mismatch — attendance data silently ignored by payroll
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 9: LEAVE
### ══════════════════════════════════════════════════════════════

```
codebase_search: "LeaveRequest" StateMachine TRANSITIONS
codebase_search: "LeaveRequestService" direct status assignment
codebase_search: "LeaveBalance" deduction
codebase_search: "RecordLeaveAttendanceCorrection" listener
codebase_search: "leave" payroll LWOP
```

**ANSWER EACH:**

```
LEA-01: Is the LeaveRequestStateMachine ACTUALLY being used?
        KNOWN BUG — 7 direct $leave->status = assignments found.
        Is it fixed?
        Run: grep -n "->status\s*=" app/Domains/Leave/Services/LeaveRequestService.php
        FINDING: [fixed — 0 direct assignments / still broken — N found]
        GRADE IMPACT: D if not fixed — panelist who asks "show me your state machine"
                      will open this file and find manual assignments

LEA-02: When leave is approved, is attendance auto-marked as on_leave?
        Search: RecordLeaveAttendanceCorrection listener — is it registered in EventServiceProvider?
        Search: Does it actually update AttendanceLog records for the leave dates?
        FINDING: [auto-updates attendance / listener not registered / missing]

LEA-03: Does approved leave feed into payroll as LWOP where applicable?
        Search: If leave type has no balance → is LWOP flagged for Step16 deduction?
        FINDING: [correctly flows / disconnected]

LEA-04: Is leave balance deducted on approval or on payroll?
        Search: LeaveRequestService — when is leave_balance decremented?
        REAL ERP: Deduct on approval (committed). Restore on cancellation.
        FINDING: [on approval / on payroll / unclear]

LEA-05: Is there a leave balance carry-over process?
        Search: Year-end leave balance processing
        REAL ERP: Unused leave carries over (with cap) or is monetized at year-end.
        FINDING: [implemented / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 10: LOAN
### ══════════════════════════════════════════════════════════════

```
codebase_search: "LoanRequest" StateMachine
codebase_search: "LoanAmortizationService"
codebase_search: "Step15LoanDeductions" pipeline
codebase_search: "LoanRequest" separation settlement
```

**ANSWER EACH:**

```
LOA-01: Is the LoanStateMachine ACTUALLY being used?
        KNOWN BUG — 10 direct $loan->status = assignments found.
        Run: grep -n "->status\s*=" app/Domains/Loan/Services/LoanRequestService.php
        FINDING: [fixed / still broken — N found]

LOA-02: Does each payroll deduction ACTUALLY reduce the loan balance?
        Search: Step15LoanDeductions — after deduction, is loan_balance updated?
        REAL ERP: Employee sees remaining balance decreasing each payroll.
        FINDING: [balance updated / deduction happens but balance unchanged]

LOA-03: When loan is fully paid, does it auto-transition to fully_paid?
        Search: LoanAmortizationService — check after last installment
        FINDING: [auto-transitions / manual update required]

LOA-04: Is there employee separation handling for outstanding loans?
        KNOWN MISSING from prior audit — is it implemented?
        Search: HR separation service — does it trigger loan settlement?
        FINDING: [implemented / still missing]

LOA-05: Can an employee have multiple active loans simultaneously?
        Search: LoanRequest — is there a check for existing active loans?
        REAL ERP: SSS Loan + Pag-IBIG Loan + Company Loan simultaneously.
        FINDING: [supported / blocks multiple / no check]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 11: FIXED ASSETS
### ══════════════════════════════════════════════════════════════

```
codebase_search: "FixedAsset" StateMachine
codebase_search: "FixedAssetService" depreciation disposal
codebase_search: "FixedAssets" GL null silent skip
codebase_search: "asset_code" trigger PHP
codebase_search: "under_maintenance" impaired TypeScript
```

**ANSWER EACH:**

```
FA-01: Does depreciation posting THROW when GL account is null?
       KNOWN BUG from CLAUDE.md — silent skip.
       Search: FixedAssetService::depreciateMonth() — null check behavior
       Run: grep -n "return\|null\|skip" app/Domains/FixedAssets/Services/FixedAssetService.php | head -20
       FINDING: [throws DomainException / still silently skips]
       GRADE IMPACT: Critical — GL entries are missing without any error

FA-02: Is asset_code set ONLY by PostgreSQL trigger?
       Search: FixedAsset model booted(), factories, seeders for asset_code assignment
       KNOWN CONVENTION — any violation?
       FINDING: [trigger only / PHP assignments found]

FA-03: Is the TypeScript type using 'impaired' (DB) or 'under_maintenance' (wrong)?
       KNOWN BUG — check if fixed
       Search: frontend/src/types/fixed_assets.ts
       FINDING: [fixed to impaired / still under_maintenance]
       GRADE IMPACT: Runtime API mismatch — UI shows wrong status for impaired assets

FA-04: Is the CSV export using the correct table name?
       KNOWN BUG from CLAUDE.md — wrong table name in route closure
       Search: fixed_asset_depreciation_entries vs asset_depreciation_entries
       FINDING: [fixed / still wrong]

FA-05: Is there an asset disposal gain/loss computation?
       Search: FixedAssetService::dispose() — does it compute gain/loss?
               Dr Accum Depreciation, Dr Cash, Cr Asset Cost, Cr/Dr Gain/Loss
       FINDING: [correct GL entry / simplified / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 12: DELIVERY
### ══════════════════════════════════════════════════════════════

```
codebase_search: "DeliveryReceipt" StateMachine TRANSITIONS
codebase_search: "StoreDeliveryReceiptRequest" validation
codebase_search: "DeliveryReceiptService" store
codebase_search: "CreateDeliveryReceiptOnProductionComplete" listener
```

**ANSWER EACH:**

```
DEL-01: Is sales_order_id REQUIRED on DeliveryReceipt?
        KNOWN CRITICAL BUG — DR can be created with no SO reference.
        Search: StoreDeliveryReceiptRequest — current rules() method
        FINDING: [required / optional / missing entirely]
        GRADE IMPACT: Critical — breaks the entire SO→DR→Invoice chain

DEL-02: Does SO confirmation auto-create a DR draft?
        KNOWN MISSING CHAIN — is it implemented?
        Search: SalesOrderService::confirm() — fires event? Listener exists?
        FINDING: [auto-creates DR / still manual]

DEL-03: Does delivery confirmation auto-create the Customer Invoice?
        Search: CreateCustomerInvoiceOnShipmentDelivered listener — registered?
        FINDING: [auto-creates invoice / manual]

DEL-04: Is partial delivery supported?
        Search: DeliveryReceipt — can you deliver 60 of 100 ordered units?
                Does SO status track partially_delivered?
        FINDING: [supported / not supported]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 13: AP
### ══════════════════════════════════════════════════════════════

```
codebase_search: "VendorInvoice" StateMachine
codebase_search: "ApPaymentPostingService"
codebase_search: "ap" aging report
codebase_search: "withholding_tax" vendor payment
```

**ANSWER EACH:**

```
AP-01: Can a Vendor Invoice be created without PO/GR reference?
       KNOWN BUG — is purchase_order_id required?
       Search: CreateVendorInvoiceRequest current rules()
       FINDING: [required / optional / not present]

AP-02: Does AP payment posting create balanced JE?
       Search: ApPaymentPostingService — Dr AP Payable / Cr Cash
       FINDING: [balanced / not posting / partial]

AP-03: Is there an AP aging report?
       Search: AP aging controller or service — 30/60/90/120+ days
       REAL ERP: Finance needs this to manage cash flow.
       FINDING: [exists / missing]

AP-04: Is withholding tax on vendor payments computed and posted?
       Search: Tax module integration with AP payment
       REAL ERP: BIR requires WHT on qualifying payments. Tax certificate (BIR 2307) required.
       FINDING: [computed and posted / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 14: BUDGET
### ══════════════════════════════════════════════════════════════

```
codebase_search: "Budget" StateMachine
codebase_search: "BudgetService" check availability
codebase_search: "lockForUpdate" budget
codebase_search: "procurement" budget check
```

**ANSWER EACH:**

```
BUD-01: Does PO creation check budget availability?
        Search: PurchaseOrderService OR BudgetService — budget check on PO?
        REAL ERP: Cannot create PO if department is over budget.
        FINDING: [hard check / soft warning / no check]

BUD-02: Is the budget consumption check protected by lockForUpdate?
        KNOWN BUG from prior audit — race condition in budget check.
        Search: BudgetService — lockForUpdate present?
        FINDING: [protected / unprotected]

BUD-03: Is there budget vs actual variance reporting?
        Search: Budget report comparing budget to actual GL spend
        REAL ERP: Finance monitors budget utilization monthly.
        FINDING: [exists / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 15: CRM
### ══════════════════════════════════════════════════════════════

```
codebase_search: "CRM" OR "Client" StateMachine
codebase_search: "ClientOrder" OR "CRMOrder" convert sales order
codebase_search: "CRM" frontend pages
```

**ANSWER EACH:**

```
CRM-01: Does CRM connect to Sales? Can a CRM opportunity/order become a SO?
        Search: CRM→Sales conversion workflow
        FINDING: [full conversion / partial / completely disconnected]

CRM-02: Are CRM pages actually implemented in the frontend?
        Search: frontend/src/pages/crm/
        FINDING: [full pages / partial / empty placeholders]

CRM-03: Is client credit history visible from CRM?
        Search: CRM client page — does it show outstanding invoices/balance?
        FINDING: [visible / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

### ══════════════════════════════════════════════════════════════
### MODULE 16: TAX
### ══════════════════════════════════════════════════════════════

```
codebase_search: "VatLedger" OR "VatLedgerService"
codebase_search: "WithholdingTax" OR "WHT"
codebase_search: "BIR" form 2307 2316
codebase_search: "tax_codes" table effective_date
```

**ANSWER EACH:**

```
TAX-01: Are VAT output (sales) and input (purchases) tracked separately?
        Search: VatLedger — output_vat and input_vat tracked per period?
        REAL ERP: Monthly VAT return: Output VAT - Input VAT = VAT Payable
        FINDING: [correctly separated / combined / missing]

TAX-02: Are tax rates in DB with effective_date, or hardcoded?
        Search: tax_codes table — effective_date column exists?
        FINDING: [DB with effective_date / hardcoded]

TAX-03: Is BIR 2307 data available for vendor payments?
        Search: WHT computation on vendor payments, 2307 report
        REAL ERP: Required by BIR. Every WHT payment needs a certificate.
        FINDING: [data available / missing]
```

**Module Grade:** __ | **Panelist Risk:** __

---

## PHASE 2 — FRONTEND COMPLETENESS AUDIT

For every module graded above, verify frontend completeness:

```bash
# Pages per module
for module in hr payroll attendance procurement inventory sales ar ap accounting \
              production qc delivery leave loan fixed-assets budget crm tax mold maintenance; do
    count=$(find frontend/src/pages/$module frontend/src/pages/${module^} \
            -name "*.tsx" 2>/dev/null | wc -l)
    echo "$module: $count pages"
done
```

For each module, the minimum frontend for a defensible demo:
```
Required minimum:
  ✓ List page (with pagination, search, status filter, loading/empty/error states)
  ✓ Detail page (with all fields, status badge, action buttons per status)
  ✓ Create/Edit form (with validation feedback, submit state)
  ✓ Archive view (View Archive toggle, restore button)
  ✓ All workflow action buttons (submit, approve, reject, post, etc.)
  ✓ Archive/soft-delete on every list

For each module, mark: COMPLETE / PARTIAL / MISSING
```

---

## PHASE 3 — PANELIST PRESSURE TEST

Read the findings from Phases 0-2 and predict the 5 most dangerous
questions each panelist will ask. For each:

- **What will they ask** (exact question they'll ask during demo)
- **What the system currently answers** (based on actual code)
- **What a correct ERP answers** (the standard)
- **Gap rating** (Critical / Acceptable / No gap)

Format:
```
PANELIST PRESSURE TEST

Q1: "Walk me through what happens when an employee doesn't clock in."
    SYSTEM ANSWER: [what actually happens based on code]
    ERP STANDARD: End-of-day job auto-marks absent. Leave check first.
    GAP: [Critical / Acceptable / No gap]

Q2: "Can I create a customer invoice without delivering anything first?"
    SYSTEM ANSWER: [current state of delivery_receipt_id in request]
    ERP STANDARD: No. PFRS 15. Revenue on delivery.
    GAP: [Critical / Acceptable / No gap]

Q3: "What if the SSS contribution rate changes — do you redeploy?"
    SYSTEM ANSWER: [hardcoded or DB]
    ERP STANDARD: Update config table. No deployment.
    GAP: [Critical / Acceptable / No gap]

Q4: "Show me your state machine for leave approval."
    SYSTEM ANSWER: [is it wired or bypassed]
    ERP STANDARD: Every transition through SM. Audit logged.
    GAP: [Critical / Acceptable / No gap]

Q5: "How does your system handle partial delivery?"
    SYSTEM ANSWER: [supported or not]
    ERP STANDARD: SO stays open. DR for delivered qty. Invoice for delivered only.
    GAP: [Critical / Acceptable / No gap]

[Add more based on findings]
```

---

## PHASE 4 — FINAL VERDICT

### Overall System Grade

After all module grades, compute:

```
MODULE GRADES SUMMARY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Accounting:    __  (A/B/C/D/F)
Procurement:   __
Inventory:     __
Sales/AR:      __
Production/QC: __
Payroll:       __
HR/Recruitment:__
Attendance:    __
Leave:         __
Loan:          __
Fixed Assets:  __
Delivery:      __
AP:            __
Budget:        __
CRM:           __
Tax:           __
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Average Grade: __

THESIS DEFENSE VERDICT:
[ ] READY        — Panelists will be impressed. Minor gaps documented.
[ ] CONDITIONAL  — Fix the C/D modules before defense. Otherwise exposed.
[ ] NOT READY    — Multiple F-grade modules. Defense at serious risk.
                   Specific items that MUST be fixed listed below.
```

### Must-Fix Before Defense (Ordered by Risk)

```
If NOT READY or CONDITIONAL:

CRITICAL (defense-breaking if not fixed):
  1. [Finding] — [Why it's fatal] — [Exact fix] — [Effort: hours]
  2. ...

HIGH (panelist will probe and find):
  1. [Finding] — [Why it matters] — [Exact fix] — [Effort: hours]
  2. ...

MEDIUM (noted but not defense-blocking):
  1. [Finding] — [Note for Q&A preparation]
  2. ...
```

---

## EXECUTION INSTRUCTIONS

```
1. Run Phase 0 commands first. The numbers don't lie.
   - If trial balance doesn't balance → start there.
   - If direct status assignments > 0 → architecture is broken.
   - If golden suite < 24/24 → payroll math is wrong.

2. For Phase 1, read ACTUAL code. Do not guess.
   Use SocratiCode. Read the service. Read the FormRequest. Read the test.

3. Grade honestly. C is not B. D is not C.
   If something is broken, grade it broken.

4. For Phase 3, answer as the panelist would ask.
   Not "what does the code try to do."
   "What does the system actually do when this scenario occurs?"

5. End with Phase 4 verdict.
   One of three: READY / CONDITIONAL / NOT READY.
   If CONDITIONAL or NOT READY, the must-fix list must be specific enough
   to execute without further clarification.

6. The developer needs to know the truth before the defense.
   Not after.
```