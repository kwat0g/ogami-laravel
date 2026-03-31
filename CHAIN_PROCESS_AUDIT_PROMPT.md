# ogamiPHP — Deep Chain Process Integrity Audit
# Target Model: Claude Opus 4.6 via Claude Code CLI
# Mode: Discovery-First | No Assumptions | No Sugarcoating
# Focus: Broken Chains · Manual Overrides · Orphaned Records · Stuck Processes

---

## YOUR MANDATE

You are a **senior ERP process integrity auditor**. Your specialization is finding
places where a system ALLOWS something it SHOULD NOT — specifically where a user can
manually create or modify a record that should only exist as the result of a prior
process step.

In a real ERP, data has ONE source of truth. A Delivery Receipt exists because
a Sales Order was confirmed. An AP Invoice exists because a GR was matched to a PO.
A Payslip exists because a Payroll Run was processed. NOTHING is created in isolation.

**You are looking for three classes of problems:**

```
CLASS 1 — BROKEN CHAINS
  A record exists that should ONLY come from an upstream process,
  but the system allows manual creation from scratch.
  Example: Delivery Receipt created without a Sales Order.
  Example: Journal Entry posted without a source document.
  Example: Payslip created outside of a Payroll Run.

CLASS 2 — MISSING CHAIN LINKS
  Two records that should be connected are NOT connected.
  The upstream record exists. The downstream record should auto-generate
  or be blocked until the upstream is confirmed. It does neither.
  Example: PO confirmed → GR never auto-created even in draft state.
  Example: Leave approved → Attendance not auto-marked as on_leave.
  Example: Employee hired → Payroll enrollment not triggered.

CLASS 3 — STUCK PROCESSES (Frontend Blockers)
  The backend chain is correct but the frontend has no button, page,
  or action to advance the process. The record is stuck at a status
  with no UI exit path. The user cannot proceed without knowing a
  direct API URL. This is a thesis defense killer.
  Example: Invoice status = 'approved' but no "Post to GL" button exists.
  Example: Production order = 'in_progress' but no "Complete" action in UI.
  Example: Leave request approved but employee has no way to see it changed.
```

---

## MANDATORY BOOTSTRAP

```
RULE 1: SocratiCode MCP search before reading any file.
RULE 2: Read route files to find every manually-accessible endpoint.
RULE 3: Read StateMachine TRANSITIONS to find every valid status path.
RULE 4: Read frontend pages and hooks to find every UI action available.
RULE 5: Cross-reference backend routes vs frontend API calls — gaps are bugs.
RULE 6: If a route exists for manual creation of a chained record, that is a finding.
RULE 7: If a status transition has no frontend button, that is a finding.
RULE 8: No finding is too small. Every stuck process breaks the demo.
```

---

## PHASE 0 — DISCOVERY

Before analyzing any chain, map the complete system.

### 0A. Map All Data Creation Endpoints

```bash
# Extract every POST route (these are record creation points)
php artisan route:list --method=POST --json \
  | python3 -c "
import sys, json
routes = json.load(sys.stdin)
for r in routes:
    if 'api/' in r.get('uri',''):
        print(f\"POST /{r['uri']}  →  {r.get('action','')}\")
" | sort > /tmp/all-post-routes.txt

cat /tmp/all-post-routes.txt
```

Read every POST route. For each one, ask:
**"Should a user EVER be able to call this route in isolation,
or should this record only exist as a result of a prior process?"**

### 0B. Map All StateMachine Transitions

```
codebase_search: "TRANSITIONS" constant

For every StateMachine found, extract the full transition map.
This tells you what status changes are theoretically possible.
```

### 0C. Map All Frontend Actions

```
Find every button, link, form submit, and mutation in the frontend:

codebase_search: "useMutation" in frontend/src/hooks/
codebase_search: "api.post\|api.put\|api.patch\|api.delete" in frontend/src/
codebase_search: "onClick" submit in frontend/src/pages/

List every user-triggerable action per module.
```

### 0D. Build the Master Process Chain Map

Before checking anything, define the CORRECT chain for every workflow:

```
MANUFACTURING CHAIN (correct order):
  Customer Inquiry
    → CRM Lead / Opportunity
      → Quotation (from Opportunity)
        → Sales Order (from accepted Quotation)
          → Production Order (from SO, if make-to-order)
            → BOM Selected (version snapshot)
              → Material Availability Check
                → Material Requisition (from Production Order)
                  → Goods Issue (from MRQ, reduces stock)
                    → Production Execution
                      → QC Inspection (triggered by Production completion)
                        → [PASS] Finished Goods Receipt (increases FG stock)
                          → Delivery Receipt (from SO + FG available)
                            → Customer Invoice (from Delivery Receipt)
                              → Customer Payment (against Invoice)
                                → AR Closed

PROCUREMENT CHAIN (correct order):
  Purchase Requisition (from dept request OR reorder alert)
    → Purchase Order (from approved PR, or direct)
      → Vendor Acknowledgment
        → Goods Receipt (from PO — cannot exist without PO)
          → 3-Way Match (PO × GR × Invoice)
            → Vendor Invoice (from GR + PO match)
              → AP Payment (from approved Invoice)
                → GL Cleared

HR → PAYROLL CHAIN:
  Recruitment Requisition (from dept head)
    → Job Posting (from approved Requisition)
      → Application (from Posting)
        → Interview (from shortlisted Application)
          → Offer (from endorsed Application)
            → Pre-Employment Checklist (from accepted Offer)
              → Employee Record (from completed Pre-Employment)
                → Shift Assignment (after Employee created)
                  → Work Location Assignment
                    → Leave Balance Initialization
                      → Payroll Enrollment (for next cutoff)

PAYROLL RUN CHAIN:
  Payroll Period Defined
    → Attendance Records Finalized (for that period)
      → Leave Records Approved (for that period)
        → Loan Schedules Active
          → Payroll Run DRAFT created
            → Scope Set (which employees)
              → Pre-Run Checks (attendance complete? loans updated?)
                → Computation (17 steps)
                  → Review
                    → Multi-level Approval
                      → Disbursement
                        → GL Posting (journal entries)
                          → Payslip Published (employee sees it)

DELIVERY CHAIN:
  Sales Order CONFIRMED
    → [Inventory sufficient] → Delivery Receipt DRAFT auto-created
      OR [Make-to-order] → Production Order first, then DR
        → DR Dispatched
          → DR Delivered (customer confirms)
            → Customer Invoice auto-generated
              → Revenue recognized (GL posting)
```

---

## PHASE 1 — CHAIN INTEGRITY AUDIT

For each major chain, verify every link. For every broken link, report it.

### 1A. Delivery Receipt Chain Audit

```
CORRECT CHAIN: Sales Order → Delivery Receipt (DR)
VIOLATION: DR can be manually created without a Sales Order

SEARCH:
  codebase_search: "DeliveryReceipt" store create
  codebase_search: "delivery" routes POST
  Read: DeliveryReceiptController::store()
  Read: DeliveryReceiptService::create()

CHECK:
  □ Does DR::store() REQUIRE a sales_order_id?
  □ Is sales_order_id validated as existing + confirmed SO?
  □ Is there a route POST /delivery-receipts that accepts
    arbitrary data without SO reference?
  □ Does the frontend DR create form allow SO-less creation?
  □ Does SO confirmation auto-create a DR draft, or must HR create manually?

REPORT FORMAT:
  Chain: SO → DR
  Status: BROKEN / PARTIAL / CORRECT
  Violation: [exact description of what's wrong]
  File: [file:line where the violation exists]
  Impact: [what goes wrong in a real scenario]
  Fix: [exact code change needed]
```

Repeat this analysis for:

### 1B. Vendor Invoice Chain Audit

```
CORRECT CHAIN: PO → GR → Vendor Invoice (3-way match)
VIOLATION: Vendor Invoice created without PO or GR reference

SEARCH:
  codebase_search: "VendorInvoice" create store
  codebase_search: "InvoiceAutoDraftService"
  Read: AP routes POST endpoints

CHECK:
  □ Can a Vendor Invoice be created with no purchase_order_id?
  □ Can a Vendor Invoice be created with no goods_receipt_id?
  □ Is 3-way match enforced BEFORE invoice approval, or just warned?
  □ Does GR confirmation auto-draft the vendor invoice, or is it manual?
  □ Can Finance post payment against an invoice that failed 3-way match?
```

### 1C. Customer Invoice Chain Audit

```
CORRECT CHAIN: Sales Order → Delivery Receipt → Customer Invoice
VIOLATION: Customer Invoice created without delivery confirmation

SEARCH:
  codebase_search: "CustomerInvoice" create store
  codebase_search: "CreateCustomerInvoiceOnShipmentDelivered"
  Read: AR routes POST endpoints

CHECK:
  □ Is there a POST /ar/customer-invoices route that bypasses delivery?
  □ Does the frontend have a "Create Invoice" button independent of DR?
  □ Does delivery confirmation AUTO-create the invoice, or must AR create manually?
  □ Can an invoice be created for an amount different from the SO amount
    without an explicit price variance approval?
  □ Can revenue be recognized before goods are delivered?
```

### 1D. Production Order Chain Audit

```
CORRECT CHAIN: Sales Order → Production Order → Material Requisition → Goods Issue
VIOLATIONS:
  - Production Order created without Sales Order reference (for MTO items)
  - Material Requisition created without Production Order
  - Goods Issue processed without approved MRQ

SEARCH:
  codebase_search: "ProductionOrder" create store
  codebase_search: "MaterialRequisition" create
  codebase_search: "GoodsIssue" OR "StockService::issue"

CHECK:
  □ Is sales_order_id required on ProductionOrder, or optional?
  □ Can a MaterialRequisition exist without a production_order_id?
  □ Can materials be issued (stock deducted) without an approved MRQ?
  □ Is BOM version snapshotted at production order creation?
    (If BOM changes after PO creation, which BOM applies?)
  □ Is there a material availability check before production order release?
  □ Can a production order be CONFIRMED when materials are insufficient?
```

### 1E. Payslip Chain Audit

```
CORRECT CHAIN: Payroll Run → Computation → Payslip (auto-generated)
VIOLATION: Payslip created manually, or exists without a payroll run

SEARCH:
  codebase_search: "Payslip" create store
  codebase_search: "payslip" routes POST
  Read: PayslipController if it exists

CHECK:
  □ Is there a POST /payslips route that creates payslips directly?
  □ Can a payslip's net_pay be manually edited after computation?
  □ Is a payslip visible to an employee BEFORE the run is PUBLISHED?
    (Employees should only see payslips after HR publishes the run)
  □ Can a payslip exist with no payroll_run_id?
```

### 1F. Journal Entry Chain Audit

```
CORRECT CHAIN: Source Document (Invoice/GR/Payroll/Depreciation) → Journal Entry
VIOLATION: Manual JE created without source document reference
NOTE: Manual JEs are VALID for adjustments — but they must be distinguished
      from system-generated entries and require Finance Manager approval.

SEARCH:
  codebase_search: "JournalEntry" create store manual
  codebase_search: "journal" routes POST
  Read: AccountingController::storeJournalEntry()

CHECK:
  □ Can a JE be created with source_type = null (no source document)?
  □ If yes — does it require Finance Manager approval (not just Accounting Officer)?
  □ Can a manually-created JE have the same period/reference as a system JE?
    (Duplicate detection)
  □ Can a JE be posted to a LOCKED fiscal period?
  □ Can a posted JE be edited? (It must NEVER be editable after posting)
  □ Is there a reversal mechanism, or do users just edit the original?
```

### 1G. Attendance Record Chain Audit

```
CORRECT CHAIN:
  Employee has Shift → System generates expected attendance record
  Employee Times In/Out → Record updated with actual times
  Approved Leave → Record auto-marked as 'on_leave'
  Holiday → Record auto-marked as 'holiday'
  No show → End-of-day job marks as 'absent'

VIOLATION: HR manually creates or edits attendance without correction workflow

SEARCH:
  codebase_search: "AttendanceLog" create store
  codebase_search: "attendance" routes POST PUT
  codebase_search: "AttendanceCorrectionRequest"

CHECK:
  □ Can HR directly create an AttendanceLog record (bypass time-in/out)?
  □ Can HR directly edit time_in/time_out without a Correction Request?
  □ If yes — is there an audit trail showing who changed what and why?
  □ Does approved leave AUTO-update attendance to 'on_leave'?
    Or must HR manually update attendance after approving leave?
  □ Do holidays AUTO-mark attendance?
    Or is it manual?
  □ Does the absence job run correctly, or are absents manually marked?
```

### 1H. Leave → Payroll Chain Audit

```
CORRECT CHAIN:
  Leave Request Approved → Attendance marked 'on_leave'
  → Payroll Step03 reads attendance → LWOP days reduce gross pay
  → Leave balance deducted

VIOLATION: Leave approved but payroll doesn't see it

SEARCH:
  codebase_search: "leave" "attendance" integration
  codebase_search: "Step03Attendance" leave
  codebase_search: "leave_balance" deduction

CHECK:
  □ When leave is approved, does it write to attendance records for those dates?
  □ Does Payroll Step03 read LWOP from attendance or from leave records directly?
  □ Is leave balance deducted on approval or on payroll processing?
  □ What happens if leave is retroactively approved AFTER payroll runs?
    Is there a mechanism to adjust or is it a data integrity hole?
```

### 1I. Loan → Payroll Chain Audit

```
CORRECT CHAIN:
  Loan Approved → Disbursed → Amortization Schedule Created
  → Each Payroll Run deducts the installment → Loan balance decremented

VIOLATION: Loan deduction in payroll but loan balance not updated

SEARCH:
  codebase_search: "LoanAmortizationService"
  codebase_search: "Step15LoanDeductions"
  codebase_search: "loan_balance" update decrement

CHECK:
  □ After payroll deducts a loan installment, is the loan balance updated?
  □ Is there a risk of double-deduction if payroll is run twice?
  □ What happens when loan is fully paid — does it auto-stop deducting?
  □ Is there a loan statement showing all deductions and remaining balance?
```

### 1J. Fixed Assets → Accounting Chain Audit

```
CORRECT CHAIN:
  Asset Acquired (from PO or direct)
  → Depreciation Run (monthly) → GL Entry auto-posted
  → Asset Disposed → Gain/Loss computed → GL Entry auto-posted

VIOLATIONS:
  - Depreciation run silently skips GL posting when account is null
  - Disposal without GL posting
  - Asset created without source document reference

SEARCH:
  codebase_search: "FixedAssetService" depreciation disposal
  codebase_search: "GL" "null" fixed asset (the known silent skip bug)
  codebase_search: "asset_code" trigger

CHECK:
  □ Does depreciation posting throw DomainException when GL account is null?
    Or does it silently skip? (Known bug — verify if fixed)
  □ Does disposal compute gain/loss correctly?
    (Gain = Sale Price - Book Value, Loss = Book Value - Sale Price)
  □ Is the disposal GL entry: Dr Accum Depreciation, Dr Cash, Cr Asset, Cr/Dr Gain/Loss?
  □ Can an asset be disposed without an approved disposal request?
  □ Is the asset_code set ONLY by the PostgreSQL trigger?
    (Not in PHP or factories — known convention)
```

### 1K. QC → Inventory Chain Audit

```
CORRECT CHAIN:
  GR Received → Auto-placed in Quarantine → QC Inspection
  → [PASS] Released to usable warehouse → Stock available
  → [FAIL] Disposition: Return / Scrap / Rework
  Scrap → Inventory OUT → GL posts loss

VIOLATION: QuarantineService bypasses StockService (known bug)

SEARCH:
  codebase_search: "QuarantineService"
  codebase_search: "StockBalance" direct in QC domain
  codebase_search: "scrap" GL posting

CHECK:
  □ Does QuarantineService use StockService::transfer() for quarantine moves?
    Or does it directly update StockBalance? (Known bug — verify if fixed)
  □ Does QC pass release from quarantine warehouse to usable warehouse?
    Via StockService::transfer()?
  □ Does QC reject create an NCR automatically?
  □ Does NCR → Scrap disposition create an Inventory OUT movement?
  □ Does scrap disposal post a GL entry (Dr Scrap Loss / Cr Inventory)?
  □ Does QC rejection block the GR from being invoiced by AP?
```

---

## PHASE 2 — MANUAL CREATION VIOLATIONS

Search for every route where a record can be manually created
that SHOULD ONLY come from an upstream process.

For each violation found, report:
```
RECORD: [ModelName]
ROUTE:  [HTTP Method + URI]
SHOULD COME FROM: [upstream record/process]
CURRENT BEHAVIOR: [can be created manually / partially restricted]
RISK: [what data corruption or process bypass this enables]
FIX: [remove route / add upstream FK validation / make FK required]
```

**Search pattern to find all manual creation routes:**
```bash
# Get all POST routes that create records
php artisan route:list --method=POST --json | python3 -c "
import sys, json
routes = json.load(sys.stdin)
for r in routes:
    uri = r.get('uri', '')
    action = r.get('action', '')
    if 'api/' in uri and 'store' in action.lower():
        print(f'{uri}  →  {action}')
" | sort
```

Then for each store() method found, read the FormRequest validation.
If the FK to the upstream document is:
- Missing entirely → **CRITICAL: fully manual creation**
- Present but nullable → **HIGH: optional chain (wrong)**
- Present but not validated as confirmed/approved status → **MEDIUM: partial chain**
- Present, required, and validated against correct status → **CORRECT**

**Specific records to audit for manual creation violations:**

| Record | Should Only Come From | Manual Route Exists? | FK Required? | Status Validated? |
|---|---|---|---|---|
| DeliveryReceipt | Confirmed SalesOrder | ? | ? | ? |
| CustomerInvoice | Delivered DeliveryReceipt | ? | ? | ? |
| VendorInvoice | Matched GR + PO | ? | ? | ? |
| GoodsReceipt | Acknowledged PurchaseOrder | ? | ? | ? |
| Payslip | Published PayrollRun | ? | ? | ? |
| StockLedgerEntry | StockService methods only | ? | ? | ? |
| ProductionCostEntry | Completed ProductionOrder | ? | ? | ? |
| LeaveBalance deduction | Approved LeaveRequest | ? | ? | ? |
| LoanDeduction | PayrollRun + active Loan | ? | ? | ? |
| NCR | Failed QC Inspection | ? | ? | ? |
| CAPA | Existing NCR | ? | ? | ? |
| InterviewSchedule | Shortlisted Application | ? | ? | ? |
| JobOffer | Endorsed Application | ? | ? | ? |
| Hiring | Accepted JobOffer + Complete Pre-Employment | ? | ? | ? |
| DepreciationEntry | DepreciationRun + FixedAsset | ? | ? | ? |
| JournalEntryLine | JournalEntry (never standalone) | ? | ? | ? |

---

## PHASE 3 — FRONTEND STUCK PROCESS AUDIT

For every status in every StateMachine, verify there is a UI action to advance it.
A status with no frontend exit is a stuck process. The demo dies here.

### 3A. Map Backend → Frontend Action Coverage

For every module with a StateMachine, create this table:

```
MODULE: [Name]

| Current Status | Required Action | Backend Route | Frontend Button/Action | STATUS |
|---|---|---|---|---|
| draft | submit | POST /submit | [found / MISSING] | OK/STUCK |
| submitted | approve | POST /approve | [found / MISSING] | OK/STUCK |
| submitted | reject | POST /reject | [found / MISSING] | OK/STUCK |
| approved | post_to_gl | POST /post | [found / MISSING] | OK/STUCK |
| ... | ... | ... | ... | ... |
```

**Search pattern for frontend action coverage:**
```bash
# All backend POST action routes (approve, reject, submit, confirm, etc.)
php artisan route:list --method=POST --json | python3 -c "
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
                print(f'POST /{uri}')
                break
" | sort > /tmp/action-routes.txt

# Find which of these are called from frontend
grep -rn "api\.post\|api\.put\|api\.patch" frontend/src/ \
  --include="*.ts" --include="*.tsx" \
  | grep -oP '(?<=["'"'"'])/api/v1/[^"'"'"'\s?]+' \
  | sort -u > /tmp/frontend-api-calls.txt

# Find action routes with NO frontend call
echo "=== ACTION ROUTES WITH NO FRONTEND BUTTON ==="
while IFS= read -r route; do
    clean=$(echo "$route" | sed 's|^POST /||' | sed 's|{[^}]*}|{param}|g')
    if ! grep -qF "$clean" /tmp/frontend-api-calls.txt 2>/dev/null; then
        echo "STUCK: $route"
    fi
done < /tmp/action-routes.txt
```

### 3B. Specific Stuck Process Checks

For each of these, search the frontend and report FOUND or MISSING:

**HR / Recruitment:**
```
□ Recruitment Requisition → "Submit for Approval" button
□ Recruitment Requisition → "Approve" button (HR Manager view)
□ Recruitment Requisition → "Reject" button with reason input
□ Application → "Shortlist" button
□ Application → "Schedule Interview" button
□ Interview → "Submit Scorecard/Evaluation" form
□ Application → "Prepare Offer" button after interview endorsement
□ Offer → "Send Offer Letter" button
□ Offer → "Accept" / "Reject" action (candidate response recording)
□ Pre-Employment → "Upload Document" per requirement
□ Pre-Employment → "Verify Document" button (HR)
□ Pre-Employment → "Mark Complete" button
□ Pre-Employment → "Hire Employee" final action
```

**Attendance:**
```
□ Time In button (with GPS capture)
□ Time Out button (with GPS capture)
□ Out-of-geofence override reason input
□ Correction Request → "Submit" button
□ Correction Request → "Approve" button (HR view)
□ Correction Request → "Reject with Reason" button
□ HR → "Apply Correction" to actual attendance record
□ "Generate Absent Records" for a date (HR admin action)
```

**Procurement:**
```
□ Purchase Request → "Submit for Approval" button
□ Purchase Request → "Approve" / "Reject" button (manager view)
□ Purchase Order → "Send to Vendor" button
□ Purchase Order → "Acknowledge" button (or vendor portal action)
□ Goods Receipt → "Confirm Receipt" button (warehouse)
□ Goods Receipt → "Partial Receipt" — can receive less than PO qty?
□ 3-Way Match → "Approve Variance" if price differs
□ AP Invoice → "Post to GL" button
□ AP Payment → "Mark as Paid" with reference number
```

**Payroll:**
```
□ Payroll Run → "Set Scope" action
□ Payroll Run → "Run Pre-checks" button
□ Payroll Run → "Trigger Computation" button
□ Payroll Run → "Submit for Approval" button
□ Payroll Run → "HR Approve" button (HR Manager)
□ Payroll Run → "Accounting Approve" button (Accounting Manager)
□ Payroll Run → "VP Approve" button (VP/President)
□ Payroll Run → "Return with Remarks" button at each approval level
□ Payroll Run → "Disburse" button after VP approval
□ Payroll Run → "Publish Payslips" button
□ Individual Payslip → "View" by employee (employee self-service)
```

**Production:**
```
□ Production Order → "Confirm" (releases for production, checks materials)
□ Production Order → "Issue Materials" button → creates MRQ
□ Material Requisition → "Approve" button
□ Material Requisition → "Issue Goods" button (warehouse executes)
□ Production Order → "Mark In Progress" button
□ Production Order → "Record Output" (qty produced + scrap)
□ Production Order → "Complete" → triggers QC inspection
□ QC Inspection → "Record Results" form (per criterion)
□ QC Inspection → "Pass" button → releases FG to warehouse
□ QC Inspection → "Fail" button → triggers NCR
□ NCR → "Set Disposition" (Return/Scrap/Rework)
□ NCR → "Create CAPA" button
```

**Sales / Delivery:**
```
□ Quotation → "Send to Customer" button
□ Quotation → "Convert to Sales Order" button
□ Sales Order → "Confirm" button
□ Sales Order → "Create Delivery" button (or auto-triggered?)
□ Delivery Receipt → "Dispatch" button (marks as dispatched)
□ Delivery Receipt → "Mark Delivered" button (customer confirms)
□ Delivery Receipt → "Generate Invoice" button (or auto-triggered?)
□ Customer Invoice → "Send to Customer" button
□ Customer Invoice → "Record Payment" button
□ Customer Invoice → "Mark as Paid" / apply receipt
```

**Accounting / Finance:**
```
□ Journal Entry → "Post to GL" button (from draft)
□ Journal Entry → "Reverse" button (creates mirror entry)
□ Fiscal Period → "Close Period" button (Finance Manager)
□ Fiscal Period → "Re-open Period" button (admin override with reason)
□ Trial Balance → date range selector + "Generate" button
□ Fixed Asset → "Run Depreciation" for a fiscal period
□ Fixed Asset → "Record Disposal" with sale price input
□ Budget → "Submit for Approval" button
□ Budget → "Approve Budget" button (VP)
```

**Leave / Loan:**
```
□ Leave Request → "Submit" button (employee)
□ Leave Request → "Department Head Approve" button
□ Leave Request → "HR Manager Approve" button
□ Leave Request → "Reject with Reason" button
□ Leave Request → "Cancel" button (by employee before approval)
□ Loan Request → "Submit" button (employee)
□ Loan Request → Full approval chain buttons
□ Loan Request → "Disburse" button (Finance)
□ Loan → View amortization schedule
□ Loan → View deduction history from payroll
```

**Fixed Assets:**
```
□ Asset → "Record Acquisition" form
□ Asset → "Run Depreciation" for period
□ Asset → "Mark as Impaired" with reason
□ Asset → "Dispose Asset" with sale price
□ Asset → View depreciation schedule
□ Asset → View GL posting history
```

### 3C. Missing Pages Audit

```bash
# Find all frontend route definitions
grep -rn "path:" frontend/src/router/ \
  --include="*.tsx" --include="*.ts" \
  | grep -oP '(?<=path: ['"'"'"])[^'"'"'"]+' | sort \
  > /tmp/frontend-routes.txt

# Find all backend API resource groups
php artisan route:list --json | python3 -c "
import sys, json, re
routes = json.load(sys.stdin)
prefixes = set()
for r in routes:
    uri = r.get('uri','')
    if uri.startswith('api/v1/'):
        # Get the resource prefix (first 2 segments after api/v1/)
        parts = uri.split('/')
        if len(parts) >= 4:
            prefix = '/'.join(parts[2:4])
            prefixes.add(prefix)
for p in sorted(prefixes):
    print(p)
" > /tmp/backend-resources.txt

echo "=== BACKEND RESOURCES WITH NO FRONTEND PAGE ==="
cat /tmp/backend-resources.txt
```

For every backend resource group, verify these pages exist in the frontend:
- List page (with pagination, search, filters)
- Detail/show page
- Create form
- Edit form
- Archive view

---

## PHASE 4 — DATA CONSISTENCY AUDIT

### 4A. Orphaned Record Detection

```bash
# Run these in tinker to find orphaned records

php artisan tinker --execute="
// Delivery Receipts with no Sales Order
\$orphaned = \App\Domains\Delivery\Models\DeliveryReceipt::whereNull('sales_order_id')->count();
echo \"DR without SO: \$orphaned\n\";

// Vendor Invoices with no PO
\$orphaned = \App\Domains\AP\Models\VendorInvoice::whereNull('purchase_order_id')->count();
echo \"Invoice without PO: \$orphaned\n\";

// Payslips with no PayrollRun
\$orphaned = \App\Domains\Payroll\Models\Payslip::whereNull('payroll_run_id')->count();
echo \"Payslip without Run: \$orphaned\n\";

// JE Lines with no JE header
\$orphaned = DB::table('journal_entry_lines')
    ->whereNotExists(function(\$q) {
        \$q->from('journal_entries')->whereColumn('journal_entries.id','journal_entry_lines.journal_entry_id');
    })->count();
echo \"JE Lines without header: \$orphaned\n\";

// Production Orders with no source (SO or internal request)
\$orphaned = \App\Domains\Production\Models\ProductionOrder::whereNull('sales_order_id')
    ->whereNull('internal_request_id')->count();
echo \"Production Orders with no source: \$orphaned\n\";
"
```

### 4B. Broken Status Chain Detection

```bash
php artisan tinker --execute="
// Leave Requests approved but attendance NOT marked on_leave
\$leaves = \App\Domains\Leave\Models\LeaveRequest::where('status','approved')->get();
\$broken = 0;
foreach(\$leaves as \$leave) {
    \$attCount = \App\Domains\Attendance\Models\AttendanceLog::where('employee_id',\$leave->employee_id)
        ->whereBetween('work_date', [\$leave->start_date, \$leave->end_date])
        ->where('status','on_leave')->count();
    \$dayCount = \$leave->start_date->diffInWeekdays(\$leave->end_date) + 1;
    if (\$attCount < \$dayCount) \$broken++;
}
echo \"Leave approved but attendance not marked: \$broken\n\";

// Hired employees not enrolled in any payroll
\$noPayroll = \App\Domains\HR\Models\Employee::where('status','active')
    ->whereDoesntHave('payrollEnrollment')->count();
echo \"Active employees not in payroll: \$noPayroll\n\";

// Loan requests active but no amortization schedule
\$noSchedule = \App\Domains\Loan\Models\LoanRequest::where('status','active')
    ->whereDoesntHave('amortizationSchedule')->count();
echo \"Active loans without amortization: \$noSchedule\n\";
"
```

### 4C. Financial Reconciliation Check

```bash
php artisan tinker --execute="
// Does inventory balance match sum of ledger entries?
\$balances = \App\Domains\Inventory\Models\StockBalance::with('item','warehouse')->get();
\$mismatches = 0;
foreach (\$balances as \$balance) {
    \$ledgerQty = DB::table('stock_ledger_entries')
        ->where('item_id', \$balance->item_id)
        ->where('warehouse_id', \$balance->warehouse_id)
        ->sum(DB::raw('CASE WHEN type IN (\'in\',\'receipt\',\'return\',\'adjustment_in\') THEN quantity ELSE -quantity END'));
    if (abs(\$balance->quantity - \$ledgerQty) > 0.001) {
        echo \"MISMATCH: Item {$balance->item->name} Warehouse {$balance->warehouse->name}: Balance={$balance->quantity} Ledger={$ledgerQty}\n\";
        \$mismatches++;
    }
}
echo \"Total stock mismatches: \$mismatches\n\";

// Does trial balance balance (total debits = total credits)?
\$debits  = DB::table('journal_entry_lines')->where('type','debit')->sum('amount');
\$credits = DB::table('journal_entry_lines')->where('type','credit')->sum('amount');
echo \"Trial Balance: Dr=\$debits Cr=\$credits Diff=\".(\$debits-\$credits).\"\n\";
if (abs(\$debits - \$credits) > 0) echo \"WARNING: TRIAL BALANCE DOES NOT BALANCE\n\";
"
```

---

## PHASE 5 — DELIVERABLE

Produce a single report with this exact structure. No omissions.

```
╔══════════════════════════════════════════════════════════════════════════╗
║         OGAMIPHP CHAIN PROCESS INTEGRITY AUDIT — FULL REPORT            ║
╚══════════════════════════════════════════════════════════════════════════╝

EXECUTIVE SUMMARY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[2-3 sentences. Blunt. Is this system ready for a thesis defense?
What is the single biggest chain integrity problem?]

CHAIN INTEGRITY RESULTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Chain                           Status    Severity
──────────────────────────────────────────────────
SO → Delivery Receipt           [OK/BROKEN] [CRITICAL/HIGH/MED/LOW]
DR → Customer Invoice           [OK/BROKEN]
PO → GR → Vendor Invoice        [OK/BROKEN]
Production Order → MRQ → Issue  [OK/BROKEN]
HR → Payroll Enrollment         [OK/BROKEN]
Leave → Attendance Update       [OK/BROKEN]
Loan → Payroll Deduction        [OK/BROKEN]
QC → Inventory Release          [OK/BROKEN]
Fixed Assets → GL Posting       [OK/BROKEN]
Payroll Run → Payslip           [OK/BROKEN]

MANUAL CREATION VIOLATIONS (Records that shouldn't be manually creatable)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
#1  [Record]: [Route] — [Why it's wrong] — [Fix]
#2  ...

STUCK PROCESSES (Status has no frontend exit path)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
#1  [Module] [Status] → [Missing action] — [Impact on demo]
#2  ...

MISSING FRONTEND PAGES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
#1  [Page that should exist but doesn't]
#2  ...

DATA CONSISTENCY ISSUES (Orphaned / Mismatched Records)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[Output from Phase 4 tinker queries]

FIX PRIORITY QUEUE (ordered by thesis defense impact)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
P1: [Fix] — [Why panelist will find this] — [Effort: S/M/L]
P2: ...

PANELIST DANGER QUESTIONS (questions that will expose these gaps)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Q1: "Walk me through creating a delivery — where does it start?"
    [Current answer the system gives vs correct ERP answer]
Q2: "Can I create an invoice without delivering anything first?"
    [Yes/No + what the system actually allows]
Q3: "Show me what happens when an employee doesn't clock in"
    [System behavior vs expected ERP behavior]
Q4: [Add based on findings]
```

---

## EXECUTION INSTRUCTIONS

```
1. Run Phase 0 FULLY before writing a single finding.
   Every finding must cite a specific file, line, or test result.

2. For Phase 1, read the actual service and controller code.
   Do not guess what it does. Read it.

3. For Phase 2, run the route:list command and read every store() method.

4. For Phase 3, search the frontend hooks AND pages.
   A button that exists in a hook but not rendered on any page is MISSING.

5. For Phase 4, run the tinker commands and report actual numbers.
   If the query fails because a model doesn't exist, that itself is a finding.

6. Every finding in the report must have:
   - What is wrong
   - Where it is (file:line or route)
   - What a panelist will see if they probe this
   - The exact fix (not "improve this" — the actual code change)

7. Produce the Phase 5 report last.
   It must reflect only what you actually found — not theoretical gaps.
```
