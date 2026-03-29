# Ogami ERP - Complete Module Workflow Testing Guide

All 20 domain modules with their full process workflows, state transitions, and test scenarios.

---

## 1. HR (Human Resources)

### Employee Lifecycle
```
draft -> active -> on_leave/suspended -> resigned/terminated/retired
```

**Happy Path:**
1. Create employee record (draft) with personal info, department, position, salary grade
2. Activate employee -> triggers: leave balance creation, payroll enrollment check
3. Employee works normally (active)
4. Separation (resignation/termination/retirement) -> triggers: loan flagging, final pay

**Test Scenarios:**
- Create employee with missing required fields -> validation error
- Activate employee without SSS/TIN numbers -> warning logged
- Resign employee with active loans -> loans flagged for settlement
- Rehire former employee -> link to previous record

### Recruitment Sub-Module
```
Job Requisition: draft -> pending_approval -> approved -> posted -> closed
Application: new -> screening -> shortlisted -> interview -> offered -> hired / rejected
Job Offer: draft -> sent -> accepted/declined -> onboarding
```

**Happy Path:**
1. Create Job Requisition (department head specifies position + headcount)
2. Approve requisition (HR Manager)
3. Create Job Posting from approved requisition
4. Receive Applications (candidates apply)
5. Screen -> Shortlist -> Schedule Interviews -> Evaluate
6. Create Job Offer for selected candidate
7. Candidate accepts -> Create Hiring record -> Convert to Employee

---

## 2. Attendance

### Daily Attendance
**Happy Path:**
1. Import biometric/time-in data via AttendanceImportService
2. System processes logs: computes hours, tardiness, undertime
3. Anomalies detected (missing time-out, duplicate) -> AnomalyResolutionService

### Overtime Requests
```
pending -> supervisor_approved -> manager_checked -> officer_reviewed -> approved
                                                                      -> rejected (at any step)
                                                                      -> cancelled (by employee)
```

**Happy Path:**
1. Employee submits OT request (date, hours, reason)
2. Supervisor approves (SoD: cannot be the requestor)
3. Manager checks
4. Officer reviews
5. VP/Executive gives final approval
6. Approved OT hours feed into Payroll Step06 for OT pay computation

**Test Scenarios:**
- Same user tries to approve own OT -> SodViolationException
- OT request after pay period cutoff -> warning
- Cancel approved OT -> only if not yet in payroll computation

---

## 3. Leave

### Leave Request Workflow
```
submitted -> head_approved -> manager_checked -> ga_processed -> approved
                                              -> rejected (GA disapproves, skips VP)
           -> rejected (at any step by the approver at that step)
           -> cancelled (by employee, only from submitted status)
```

**Happy Path:**
1. Employee submits leave request (type, dates, reason)
2. Department Head approves (SoD: must differ from submitter)
3. Plant Manager checks
4. GA Officer processes: sets action_taken (approved_with_pay / approved_without_pay / disapproved)
   - If approved_with_pay: captures balance snapshot, validates sufficient balance
   - If disapproved: immediately rejected, VP step skipped
5. VP notes (final step) -> balance deducted if approved_with_pay

**Test Scenarios:**
- Request leave with insufficient balance -> InsufficientLeaveBalanceException at GA step
- LWOP (Leave Without Pay) -> approved_without_pay, no balance deduction
- Half-day leave -> 0.5 day deduction
- Cancel submitted request -> cancelled
- Try to cancel after head_approved -> blocked (not cancellable)
- Rejected at any step -> no balance deducted
- OTH (Others) leave type -> no balance check (discretionary)

---

## 4. Loan

### Loan Application Workflow
```
pending -> head_noted -> manager_checked -> officer_reviewed -> ready_for_disbursement (v2 VP)
                                                             -> approved -> ready_for_disbursement (v1 accounting)
        -> rejected (at any step)
        -> cancelled (before approval)
ready_for_disbursement -> active (disbursed)
active -> fully_paid | written_off
```

**Happy Path:**
1. Employee submits loan application (type, principal, term)
2. Department Head notes (SoD check)
3. Manager checks
4. Officer reviews
5. VP approves -> generates amortization schedule + GL entry
6. Accounting/Finance disburses -> loan becomes active
7. Each payroll run deducts installment (Step15) -> reduces outstanding balance
8. All installments paid -> fully_paid

**Test Scenarios:**
- Duplicate active loan of same type -> DomainException (LN-001)
- Principal exceeds max_amount for loan type -> blocked (LN-002)
- Employee separates with active loan -> loan flagged for settlement
- Write off bad debt -> written_off status, HR notified
- Partial payroll deduction -> installment remains pending

---

## 5. Payroll

### Payroll Run State Machine (14+ states)
```
DRAFT -> SCOPE_SET -> PRE_RUN_CHECKED -> PROCESSING -> COMPUTED -> REVIEW -> SUBMITTED
-> HR_APPROVED -> ACCTG_APPROVED -> VP_APPROVED -> DISBURSED -> PUBLISHED
                                                              -> RETURNED/REJECTED -> DRAFT
```

### 17-Step Pipeline
```
Step01: Snapshots (employee data frozen)
Step02: Period metadata (working days, pay frequency)
Step03: Attendance summary (tardiness, absences, LWOP)
Step04: Load YTD (year-to-date totals for tax computation)
Step05: Basic pay (prorated for mid-period hire/separation)
Step06: Overtime pay (multipliers from overtime_multiplier_configs table)
Step07: Holiday pay (from holiday_calendar table)
Step08: Night differential
Step09: Gross pay (sum of Steps 05-08)
Step10: SSS contribution (from sss_contribution_tables with effective_date)
Step11: PhilHealth contribution (from philhealth_premium_tables)
Step12: Pag-IBIG contribution (from pagibig_contribution_tables)
Step13: Taxable income
Step14: Withholding tax (from train_tax_brackets with effective_date)
Step15: Loan deductions (reduces outstanding loan balance)
Step16: Other deductions (adjustments)
Step17: Net pay
```

**Happy Path:**
1. Create payroll run (DRAFT) for a pay period
2. Set scope (which employees) -> SCOPE_SET
3. Run pre-checks -> PRE_RUN_CHECKED (validates attendance, employee data)
4. Compute -> PROCESSING -> COMPUTED (runs 17-step pipeline)
5. Review results -> REVIEW
6. Submit for approval -> SUBMITTED
7. HR Manager approves -> HR_APPROVED (SoD: different from creator)
8. Accounting Manager approves -> ACCTG_APPROVED (SoD: different from HR approver)
9. VP approves -> VP_APPROVED
10. Disburse -> DISBURSED (generates bank file + posts GL journal entry)
11. Publish payslips -> PUBLISHED

**Test Scenarios:**
- Mid-period hire -> prorated basic pay in Step05
- Employee with LWOP -> salary deduction in Step03/Step05
- Multiple OT types -> correct multipliers from config table
- SSS rate change -> new rate table row with future effective_date, picked up automatically
- Same user tries HR_APPROVE and VP_APPROVE -> SoD violation
- Zero gross pay -> DISBURSED blocked with GL_ZERO_GROSS_PAY error
- Recompute for specific employee -> selective rerun
- Return from HR -> RETURNED -> DRAFT (restart)

---

## 6. Procurement

### Purchase Request Workflow
```
draft -> pending_review -> reviewed -> budget_verified -> approved -> converted_to_po
      -> returned (at any step) -> pending_review (resubmit)
      -> rejected | cancelled
```

### Purchase Order Workflow
```
draft -> sent -> negotiating -> acknowledged -> in_transit -> delivered
      -> partially_received -> fully_received -> closed
      -> cancelled (from most states)
```

### Goods Receipt
```
draft -> pending_qc (if IQC required) -> confirmed -> returned (to supplier)
      -> rejected
```

**Full Procurement Cycle:**
1. Department creates Purchase Request (items needed, estimated costs)
2. Head reviews -> Manager reviews -> Budget verified -> Approved
3. Purchasing creates PO from approved PR (converts to PO)
4. PO sent to vendor -> vendor acknowledges -> goods in transit -> delivered
5. Warehouse creates Goods Receipt (draft) against PO
6. If items require IQC: submit for QC -> QC inspection passes
7. Confirm GR -> triggers:
   - Three-way match (PO qty/price vs GR qty vs Invoice)
   - Stock update via StockService (inventory IN)
   - Inventory recognition JE (Dr Inventory / Cr GR Clearing)
   - AP invoice auto-draft
   - Item price update (standard or weighted average)
8. PO status auto-updates (partially_received or fully_received)

**Test Scenarios:**
- Partial GR: receive 60 of 100 -> PO stays partially_received
- Over-delivery: receive 110 vs PO 100 -> blocked (GR_QTY_EXCEEDS_PENDING)
- Split delivery: multiple GRs against one PO
- Return to supplier: confirmed GR -> returnToSupplier() -> stock OUT + PO qty reversed
- IQC gate: item requires inspection -> GR blocked until QC passes
- Budget exceeded: PR fails budget check -> soft warning or block
- Blanket PO: multiple releases against one master agreement

---

## 7. Inventory

### Stock Operations
- **Receive**: GR confirm -> StockService::receive() -> stock IN + ledger entry
- **Issue**: Material Requisition fulfillment -> StockService::issue() -> stock OUT + ledger entry
- **Transfer**: Between warehouses -> StockService::transfer() -> OUT + IN + ledger entries
- **Quarantine**: QC hold -> StockService::transfer() to quarantine location
- **Adjustment**: Physical count variance -> StockService with adjustment reference

### Physical Count
```
draft -> in_progress -> completed -> approved -> posted
      -> cancelled
```

**Test Scenarios:**
- Issue more than available -> INV_INSUFFICIENT_STOCK (hard block)
- Concurrent stock operations -> lockForUpdate prevents race conditions
- Low stock alert: quantity drops below reorder_point -> event fired
- Auto-PR creation when stock below reorder point
- Lot/batch tracking: stock tied to lot numbers with expiry dates
- Costing: standard vs weighted_average per item

---

## 8. Sales

### Quotation Workflow
```
draft -> sent -> accepted | rejected -> converted_to_order (from accepted)
```

### Sales Order Workflow
```
draft -> confirmed -> in_production -> partially_delivered -> delivered -> invoiced
      -> cancelled
```

**Full Sales Cycle:**
1. Create Quotation for customer (items, prices from PriceList)
2. Send to customer -> customer accepts
3. Convert Quotation to Sales Order
4. Credit limit check on SO creation (SAL-S12: soft-block if exceeded, override available)
5. Confirm SO -> triggers production order or direct delivery
6. Production completes -> delivery receipt created
7. Delivery confirmed -> customer invoice auto-created
8. Invoice posted -> AR entry + Revenue JE

**Test Scenarios:**
- Customer credit limit exceeded -> CreditLimitExceededException (with override option)
- Partial delivery: deliver 60 of 100 -> SO stays partially_delivered
- Price override on quotation -> may require approval
- SO from accepted quotation -> quotation marked converted_to_order

---

## 9. Production

### Production Order Workflow
```
draft -> released -> in_progress -> completed -> closed
      -> on_hold (from released or in_progress) -> back to released/in_progress
      -> cancelled (emergency stop)
```

**Happy Path:**
1. Create Production Order (product, BOM, quantity, target dates)
   - BOM components snapshotted at creation (PRD-S01)
   - Standard cost computed from BOM
2. Release order -> auto-creates Material Requisition for BOM components
   - QC gate check: blocks release if linked inspections failed
   - Stock reserved for BOM components
3. Fulfill material requisition -> stock issued OUT via StockService
4. Production runs -> log output quantities
5. Complete order -> triggers:
   - OQC (Outgoing QC) inspection
   - Stock IN for finished goods
   - Production cost JE posting (Dr WIP / Cr Raw Materials)
   - Delivery receipt creation
   - Client order status update

**Test Scenarios:**
- BOM version changed after order created -> order uses snapshotted version
- Partial production: produce 300 of 500 -> order stays in_progress
- QC failure on released order -> order put on_hold
- Rework: QC fails at OQC -> rework production order created
- Material shortage -> insufficient stock blocks MRQ fulfillment

---

## 10. QC (Quality Control)

### Inspection Workflow
```
open -> passed | failed | on_hold | voided
on_hold -> passed | failed
```

### CAPA (Corrective/Preventive Action)
```
draft -> assigned -> in_progress -> completed -> verified -> closed
```

**Inspection Types:**
- **IQC (Incoming)**: On goods receipt, before stock enters warehouse
- **In-Process**: During production at checkpoints
- **OQC (Outgoing)**: After production completion, before delivery

**Test Scenarios:**
- IQC fails -> stock quarantined, GR blocked from confirmation
- OQC fails -> production order put on_hold, rework order created
- NCR (Non-Conformance Report) raised on failure -> CAPA created
- Quarantine release: QC passes -> stock transferred back to main location
- Quarantine reject: scrap disposition -> stock issued OUT via StockService

---

## 11. Delivery

### Delivery Receipt Workflow
```
draft -> confirmed -> shipped -> delivered
      -> cancelled
```

**Happy Path:**
1. DR auto-created from production completion or OQC pass
2. Assign vehicle, driver, route
3. Confirm DR -> stock OUT from warehouse
4. Create shipment -> track in-transit
5. Shipment delivered -> triggers:
   - Customer invoice creation (AR)
   - Client order status update
   - Proof of delivery capture (photo, GPS, signature)

---

## 12. AP (Accounts Payable)

### Vendor Invoice Workflow
```
draft -> pending_approval -> approved -> paid | deleted
```

**Happy Path:**
1. Invoice auto-drafted from GR three-way match
2. Review invoice amounts vs PO vs GR (three-way match)
3. Approve invoice -> posts JE (Dr Expense / Cr AP)
4. Create payment -> posts JE (Dr AP / Cr Cash)
5. EWT (Expanded Withholding Tax) auto-computed for subject vendors

**Test Scenarios:**
- Three-way match variance -> flag for approval
- Partial payment against invoice
- Vendor credit note -> reduces outstanding AP
- Payment batch processing -> multiple vendors in one batch
- Debit memo for over-billing

---

## 13. AR (Accounts Receivable)

### Customer Invoice Workflow
```
draft -> approved -> sent -> paid | overdue | written_off
      -> cancelled
```

**Happy Path:**
1. Invoice auto-created on shipment delivery
2. Approve invoice -> posts JE (Dr AR / Cr Revenue) + VAT entries
3. Send to customer
4. Receive payment -> allocate to specific invoices
5. AR aging: overdue invoices flagged at 30/60/90/120+ days
6. Dunning: automated overdue notices sent to customers

**Test Scenarios:**
- Overpayment -> creates advance payment record
- Bad debt write-off -> posts to correct GL account
- Customer credit note -> reduces outstanding AR
- Payment applied to oldest invoice first

---

## 14. Accounting

### Journal Entry Workflow
```
draft -> submitted -> posted -> reversed
      -> cancelled (from draft)
```

**Core Functions:**
- Manual JE creation with balanced debit/credit lines
- Auto-posted JEs from: Payroll, AP, AR, Production, FixedAssets, Loans, Tax
- JE reversal (creates mirror entry)
- Fiscal period management (open/closed/locked)
- Trial Balance, Income Statement, Balance Sheet, Cash Flow reports
- Bank Reconciliation (match bank transactions to JE lines)
- Year-end closing (closes revenue/expense to retained earnings)
- Recurring JE templates (rent, depreciation, etc.)

**Test Scenarios:**
- Unbalanced JE (debits != credits) -> UnbalancedJournalEntryException
- Post to closed period -> LockedPeriodException
- SoD: poster cannot be the drafter
- Auto-posted JEs cannot be manually edited
- Idempotency: posting same source twice -> blocked by source_type+source_id check

---

## 15. Tax

### VAT Ledger
- Input VAT tracked from vendor invoices
- Output VAT tracked from customer invoices
- Monthly/quarterly VAT closing -> posts JE (Dr Output VAT / Cr VAT Remittable)

### BIR Forms
- BIR 2307 (Certificate of Creditable Tax Withheld) auto-populated
- BIR filing records tracked with status

---

## 16. Fixed Assets

### Asset Lifecycle
```
active -> impaired -> disposed | fully_depreciated
```

**Happy Path:**
1. Create asset (acquisition cost, useful life, depreciation method, category)
2. Asset code auto-generated by PostgreSQL trigger
3. Run monthly depreciation -> posts JE (Dr Depreciation Expense / Cr Accumulated Depreciation)
   - Straight-line or double-declining balance methods
   - Idempotent: unique constraint on (asset_id, fiscal_period_id)
4. Dispose asset -> posts disposal JE with gain/loss calculation

**Test Scenarios:**
- Depreciation without GL accounts configured -> DomainException (FA_GL_NOT_CONFIGURED)
- Double-run depreciation -> unique constraint prevents duplicate
- Fully depreciated asset -> status changes to fully_depreciated
- Disposal with proceeds > book value -> gain posted
- Disposal with proceeds < book value -> loss posted

---

## 17. Budget

### Budget Workflow
```
draft -> submitted -> reviewed -> approved -> active
      -> rejected
```

**Happy Path:**
1. Create cost center for department
2. Create annual budget lines per account per cost center
3. Submit for approval -> review -> approve
4. Budget enforcement: PO creation checks available budget
5. Budget vs actual reporting from posted JE lines

---

## 18. Maintenance

### Work Order Workflow
```
open -> in_progress -> completed -> closed
     -> cancelled
```

**Happy Path:**
1. Create work order (corrective or preventive)
2. Assign parts needed (from inventory)
3. Start work -> in_progress
4. Complete work -> parts consumed from inventory
5. Close work order

### Preventive Maintenance
- PM schedules with configurable intervals
- Auto-generates work orders when due

---

## 19. Mold

### Mold Management
- Track mold shot count (incremented per production run)
- Alert when shot count approaches max life threshold
- Schedule preventive maintenance at N-shot intervals
- Block production when mold is under maintenance

---

## 20. CRM (Client Orders)

### Client Order Workflow
```
pending -> negotiating -> client_responded -> vp_pending -> approved -> in_production -> delivered
        -> rejected | cancelled
```

**Happy Path:**
1. Client submits order (items, quantities, delivery date)
2. Sales reviews -> may negotiate (counter-proposals back and forth)
3. VP approves high-value orders
4. Approved -> triggers production order creation
5. Production completes -> delivery -> order fulfilled

### Support Tickets
```
open -> in_progress -> resolved -> closed
     -> reopened (from resolved)
```

---

## Cross-Module Integration Test Chains

### Chain 1: Procure-to-Pay
```
PR -> PO -> Vendor ships -> GR -> QC IQC -> GR Confirm -> 
  Stock IN + Inventory JE + Three-Way Match -> AP Invoice -> AP Payment -> GL Posted
```

### Chain 2: Order-to-Cash
```
Client Order -> SO -> Production Order -> Material Issue -> 
  Production Complete -> OQC -> Delivery -> Customer Invoice -> AR Payment -> GL Posted
```

### Chain 3: Hire-to-Retire
```
Job Requisition -> Posting -> Application -> Interview -> Offer -> Hiring ->
  Employee Activated -> Leave Balances Created -> Payroll Enrolled ->
  Monthly Payroll Runs -> Loan Applications -> Separation -> 
  Final Pay + Loan Settlement
```

### Chain 4: Month-End Close
```
All transactions posted -> Run depreciation -> Close VAT period ->
  Trial Balance -> Income Statement -> Balance Sheet -> 
  Lock fiscal period -> Year-end closing (Dec)
```
