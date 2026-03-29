# Ogami ERP -- Comprehensive Thesis Flowcharts Plan

> **Deliverable:** One Markdown file (`docs/architecture/THESIS_FLOWCHARTS.md`) containing **26 Mermaid flowcharts** -- 22 module swimlane diagrams (A1-A22) + 4 cross-module integration diagrams (B1-B4). All charts use standard flowchart notation and role-based swimlanes.

---

## Notation Standards

All flowcharts will follow these conventions consistently:

| Symbol | Mermaid Syntax | Usage |
|--------|---------------|-------|
| Oval / Stadium | `([text])` | Start and End nodes |
| Rectangle | `[text]` | Process / Action steps |
| Diamond | `{text}` | Decision points -- Yes/No branches |
| Parallelogram | `[/text/]` | Data Input/Output |
| Cylinder | `[(text)]` | Database / Data Store |
| Subgraph | `subgraph name` | Swimlanes per Role/Actor |

### Swimlane Convention
Each flowchart uses `subgraph` blocks to represent **role-based swimlanes**. Arrows cross swimlane boundaries to show handoffs between actors.

### Color/Style Convention
- Green nodes for successful terminal states
- Red nodes for rejection/error terminal states  
- Yellow/orange for warning/hold states
- SoD enforcement marked with a note icon

---

## Part A: 20 Module Swimlane Flowcharts

### A1. HR -- Employee Lifecycle

**Swimlanes:** Employee, HR Manager, Department Head, System

**Flow:**
1. HR Manager creates employee record -- status: draft
2. HR Manager completes onboarding -- personal info, gov IDs encrypted, salary grade, dept, shift
3. Department Head activates employee -- SoD: creator != activator -- status: active
4. Decision: Leave of Absence? --> on_leave --> can return to active or separate
5. Decision: Suspension? --> suspended --> can return to active or separate
6. Decision: Resignation? --> resigned --> offboarding + final pay
7. Decision: Termination? --> terminated --> offboarding + final pay
8. System: generated columns daily_rate, hourly_rate computed by PostgreSQL

**Outputs to:** Payroll, Attendance, Leave, Loan

---

### A2. Payroll -- 17-Step Pipeline & Approval

**Swimlanes:** Payroll Admin, System Pipeline, HR Manager, Accounting Manager, VP, General Ledger

**Flow:**
1. Payroll Admin creates run -- DRAFT
2. Payroll Admin sets scope -- departments, positions, employment types -- SCOPE_SET
3. System runs pre-run checks -- attendance, loans, leave, rates -- PRE_RUN_CHECKED
4. Decision: Warnings? --> Acknowledge or Fix
5. System computes via 17-step pipeline -- PROCESSING --> COMPUTED
6. Payroll Admin reviews breakdown, flags exceptions -- REVIEW
7. Payroll Admin submits for HR -- SUBMITTED
8. HR Manager approves -- SoD != initiator -- HR_APPROVED
9. Decision: Return? --> back to DRAFT
10. Accounting Manager approves -- GL preview checked -- ACCTG_APPROVED
11. Decision: Reject? --> back to DRAFT
12. System disburses -- bank file generated, GL journal posted -- DISBURSED
13. System publishes -- payslips released -- PUBLISHED
14. Output: GL Journal Entry, Payroll Register, Disbursement File

**Sub-diagram:** 17-step pipeline detail (S01-S17) as nested subgraph

---

### A3. Accounting -- Journal Entry & Fiscal Periods

**Swimlanes:** Accounting Clerk, Accounting Manager, System, General Ledger

**Flow:**
1. Clerk creates JE -- debit/credit lines, must balance -- draft
2. Clerk submits JE -- submitted
3. Manager posts JE -- SoD enforced -- posted; JE number assigned
4. Decision: Fiscal period open? --> Yes: post; No: reject
5. System updates GL balances
6. Decision: Need reversal? --> auto-create reversing JE
7. Output: Trial Balance, Balance Sheet, Income Statement, Cash Flow

**Sub-flow:** Bank Reconciliation (import statement --> match transactions --> certify -- SoD)

---

### A4. AP -- Accounts Payable

**Swimlanes:** AP Clerk, Department Head, Manager, Accounting Officer, System, General Ledger

**Flow:**
1. AP Clerk creates invoice linked to PO and GR -- draft
2. AP Clerk submits -- submitted
3. System performs 3-Way Match: PO vs GR vs Invoice
4. Decision: Match? --> No: flag discrepancy; Yes: continue
5. Department Head notes -- SoD
6. Manager checks -- SoD
7. Officer reviews -- SoD
8. Approval --> approved; GL entry auto-posted
9. Decision: Reject at any step? --> rejected
10. Payment recorded -- partially_paid or paid
11. Output: GL posting, EWT/VAT data to Tax module

---

### A5. AR -- Accounts Receivable

**Swimlanes:** Sales Clerk, Accounting Manager, VP, System, General Ledger

**Flow:**
1. Clerk creates invoice linked to Delivery Receipt -- draft
2. Decision: DR exists? --> No: block; Yes: continue
3. Manager approves -- SoD -- INV number assigned -- approved
4. System auto-posts JE (AR DR, Revenue CR, VAT CR)
5. Customer payment recorded
6. Decision: Excess payment? --> create advance payment credit
7. Decision: Fully paid? --> Yes: paid; No: partially_paid, loop
8. Decision: Bad debt? --> VP approves write-off --> GL reversal
9. Output: GL posting, Output VAT to Tax

---

### A6. Tax -- BIR Filing & VAT Ledger

**Swimlanes:** System, Tax Officer, Accounting Manager

**Flow:**
1. System aggregates: WHT from Payroll, EWT + Input VAT from AP, Output VAT from AR
2. Tax Officer creates BIR filing record for form + period
3. Decision: Filed on time? --> filed; Late? --> late; Amendment? --> amended
4. VAT Ledger: Input VAT vs Output VAT reconciliation
5. Decision: Net VAT positive? --> VAT payable to BIR; Negative? --> carry forward
6. Output: BIR forms data (1601C, 0619E, 2550M/Q, 1702Q/RT, alphalists)

---

### A7. Procurement -- Purchase Request to Goods Receipt

**Swimlanes:** Department Staff, Department Head, Plant Manager, Purchasing Officer, VP, Vendor, System

**Flow:**
1. Staff creates Purchase Request -- draft
2. Decision: Budget available? --> No: hard block; Yes: continue
3. Head notes --> Manager checks --> Officer reviews --> VP approves
4. Decision: Reject at any step? --> rejected, back to draft
5. System creates Purchase Order from approved PR
6. PO sent to Vendor
7. Vendor delivers goods
8. Receiving Clerk creates Goods Receipt linked to PO
9. Decision: Goods match PO? --> No: discrepancy; Yes: GR confirmed
10. System performs 3-Way Match
11. Output: Stock-in to Inventory, AP Invoice trigger

---

### A8. Inventory -- Stock Management & Material Requisition

**Swimlanes:** Warehouse Manager, Department Staff, Department Head, Manager, Officer, VP, System

**Flow:**
1. Input: Goods Receipt from Procurement --> stock ledger entry goods_receipt --> balance +
2. Input: Production output --> stock ledger entry production_output --> balance +
3. Staff creates Material Requisition -- draft
4. Submit --> Head notes --> Manager checks --> Officer reviews --> VP approves
5. Warehouse fulfills -- picks items from location -- fulfilled
6. Stock ledger entry issued --> balance -
7. Decision: Low stock? --> alert event fired --> may trigger new PR
8. Adjustments: cycle counts, scraps posted as adjustment entries
9. Output: Material to Production, COGS to Accounting

---

### A9. Production -- Manufacturing Order

**Swimlanes:** Production Planner, Shop Floor Supervisor, QC Inspector, System, Inventory

**Flow:**
1. Input: Client order or delivery schedule triggers Production Order -- draft
2. BOM attached; System auto-calculates standard cost
3. Material Requisition auto-created for required components
4. Decision: Materials available? --> No: trigger MR to Inventory; Yes: continue
5. Release order -- released; capacity check performed
6. Start production -- in_progress; machine + labor assigned
7. Mold shots logged --> Mold module; Equipment used --> Maintenance module
8. Complete production -- completed; log output (qty produced, scrap qty)
9. Trigger QC Inspection
10. Finished goods stock-in to Inventory
11. Decision: QC passed? --> Yes: dispatch to Delivery; No: NCR raised, quarantine/rework

---

### A10. QC -- Quality Control & NCR/CAPA

**Swimlanes:** QC Inspector, QC Manager, Production Manager, System

**Flow:**
1. Input: Inspection triggered on GR receipt or production output
2. Inspector selects template, fills checklist -- pending --> in_progress
3. Records results per checklist item -- completed
4. Decision: Overall verdict? --> passed: goods cleared; failed: NCR created
5. NCR raised -- defect description, affected qty
6. Root cause investigation -- under_investigation
7. CAPA issued -- corrective + preventive action assigned
8. CAPA implemented by responsible team
9. CAPA completed --> NCR closed
10. Re-inspection triggered --> loop back to verdict check
11. Output: Released goods to Delivery/Inventory

---

### A11. Maintenance -- Equipment & Work Orders

**Swimlanes:** Equipment Operator, Maintenance Supervisor, Technician, System

**Flow:**
1. Input triggers: Machine breakdown from Production, PM due from schedule, Mold shot limit from Mold module, Manual request
2. Work Order created -- pending; priority assigned (critical/high/normal/low)
3. Supervisor assigns technician -- assigned
4. Technician starts work -- in_progress; actual start time logged
5. Technician performs repair/PM/calibration; spare parts issued from Inventory
6. Complete work order -- completed; duration, parts used, findings recorded
7. Equipment returned to production -- ready
8. Decision: Critical priority? --> flag production impact
9. Output: Equipment availability to Production, spare parts consumption to Inventory

---

### A12. Mold -- Shot Tracking & EOL

**Swimlanes:** Production Manager, Mold Engineer, System, Maintenance Module

**Flow:**
1. Mold registered -- code, cavity count, max shot life, material
2. Mold assigned to production order
3. Production run uses mold; System auto-logs shots (qty / cavity_count)
4. Decision: Shot count vs threshold? 
   - Below: continue production
   - Approaching 80%: warning alert in dashboard
   - At/above limit: trigger maintenance WO, pull mold from production
5. Mold serviced in Maintenance module
6. Shot counter reset after maintenance
7. Mold returned to active pool
8. Decision: End of life? --> retired

---

### A13. Delivery -- Shipment & Receipt

**Swimlanes:** Sales Admin, Logistics Manager, Delivery Driver, Customer, System

**Flow:**
1. Input: QC-passed goods ready for dispatch
2. Sales Admin creates Delivery Receipt -- draft; linked to customer invoice items
3. Goods packed -- ready_for_pickup
4. Logistics assigns vehicle from fleet
5. Shipment dispatched -- in_transit
6. Driver delivers to customer
7. Customer confirms receipt -- delivered
8. Decision: Customer acknowledges? --> Yes: DR confirmed; No: returned
9. System: DR feeds AR as proof of delivery --> AR Invoice can be created
10. Output: Delivery confirmation to AR, stock deduction from Inventory

---

### A14. ISO -- Document Control & Internal Audit

**Swimlanes:** Document Author, QMS Manager, Internal Auditor, Department Owner

**Flow:**
1. Author creates controlled document -- draft
2. Submit for review -- under_review
3. QMS Manager approves -- SoD: reviewer != creator -- effective; version number issued
4. Decision: Revision needed? --> update triggers new version cycle
5. Effective documents govern procedures for all departments
6. QMS Manager plans internal audit -- planned
7. Auditor starts audit -- in_progress
8. Auditor adds findings (Major NC / Minor NC / OFI)
9. Complete audit -- completed; report issued
10. Decision: Findings raised? --> No: clean audit; Yes: close findings with evidence
11. NC findings may trigger CAPA in QC module
12. Document findings trigger document revision

---

### A15. CRM -- Client Orders & Negotiation

**Swimlanes:** Customer, Sales Rep, Sales Manager, VP, System

**Flow:**
1. Customer submits order via Client Portal -- pending
2. Sales Rep reviews order -- check availability, delivery capacity
3. Decision: Can fulfill? --> Approve: approved; Need changes: negotiating; Cannot: rejected
4. Negotiation loop: Sales proposes --> Customer responds (accept/counter/cancel) -- max 5 rounds
5. Decision: Agreement reached? --> Yes: approved; No: cancelled
6. System auto-creates Delivery Schedule + triggers Production Order
7. Customer raises support tickets -- open --> in_progress --> resolved/closed
8. Decision: SLA deadline? --> enforce response time
9. Output: Production Order, Delivery Schedule, AR Invoice link

---

### A16. Fixed Assets -- Depreciation & Disposal

**Swimlanes:** Asset Manager, Finance Controller, System, General Ledger

**Flow:**
1. Asset acquired and registered -- code auto-generated by PostgreSQL trigger
2. Depreciation method selected: straight-line, double-declining, or units-of-production
3. System monthly scheduler auto-posts depreciation JE
4. Decision: Fully depreciated? --> Yes: may continue in use at zero book value; No: continue monthly
5. Decision: Dispose? --> book value vs sale price --> gain/loss calculated
6. System posts disposal GL entry (debit Accum Dep + Gain/Loss, credit Asset)
7. Decision: Impairment? --> impairment test --> GL posting
8. Output: Monthly depreciation JE, disposal JE to Accounting

---

### A17. Budget -- Annual Departmental Budgets

**Swimlanes:** Department Manager, Finance Controller, Budget Analyst, System

**Flow:**
1. Manager drafts budget per GL account for fiscal year -- draft
2. Manager submits -- submitted
3. Finance Controller reviews
4. Decision: Approve? --> Yes: approved; No: rejected, back to draft
5. System tracks budget vs actual (live query against GL balances)
6. Decision: PR created? --> System checks department budget
7. Decision: Budget exceeded? --> Yes: hard block PR; No: allow
8. Budget amendment workflow: reallocation, increase, decrease
9. Output: Budget enforcement on Procurement, budget vs actual reports

---

### A18. Attendance -- Time Logs & Overtime

**Swimlanes:** Employee, Supervisor, Manager, HR Officer, VP, System

**Flow:**
1. Employee clocks in/out (biometric/manual/CSV import)
2. System calculates: worked minutes, late/undertime, absences
3. Decision: Anomaly detected? --> Late/absent: flagged; OT worked: OT request needed; Clean: feed to payroll
4. Manual correction by HR for flagged records
5. OT Request filed by employee
6. Supervisor endorses --> Manager approves --> (if manager filed: Executive approves) --> HR Officer reviews --> VP final approval
7. Decision: Rejected at any step? --> OT rejected
8. Approved OT hours added to payroll computation
9. Output: Attendance summary to Payroll (Steps 3, 6, 8)

---

### A19. Leave -- Request & Approval

**Swimlanes:** Employee, Department Head, Plant Manager, GA Officer, VP, System

**Flow:**
1. Employee files leave request -- type, dates, reason
2. Decision: Sufficient leave balance? --> No: InsufficientLeaveBalanceException; Yes: pending
3. Department Head decision --> head_approved or rejected
4. Plant Manager decision --> manager_checked or rejected
5. GA Officer processes -- sets action: with pay, without pay, or disapproved; records balance snapshot
6. VP notes -- final approval
7. Decision: Approved with pay? --> deduct balance; Without pay? --> no deduction
8. System records leave in attendance (absent flag)
9. Output: Leave days to Payroll, absent flag to Attendance

**Sub-check:** Team conflict detection (min staffing, position overlap, team cap)

---

### A20. Loan -- Application & Amortization

**Swimlanes:** Employee, Department Head, Manager, Officer, VP, System, Payroll

**Flow:**
1. Employee applies for loan -- type, amount, terms
2. Decision: Credit limit check --> exceeds: CreditLimitExceededException; within: pending
3. Department Head notes --> Manager checks --> Officer reviews --> VP approves
4. Decision: Rejected at any step? --> rejected
5. System generates amortization schedule
6. Loan disbursed -- funds released -- active
7. Each payroll run deducts monthly amortization (Pipeline Step 15)
8. Decision: Manual payment? --> record payment
9. Decision: All installments paid? --> Yes: fully_paid; No: continue next period
10. Decision: Forgiven? --> written_off + GL reversal
11. Output: Monthly deduction to Payroll, disbursement + write-off GL to Accounting

---

### A21. Sales -- Quotation to Sales Order Fulfillment

**Swimlanes:** Customer, Sales Rep, Sales Manager, VP, System, Production, Delivery

**Flow:**
1. Sales Rep creates Quotation with line items and prices -- draft
2. System checks profit margin per line item (ProfitMarginService uses BOM standard costs)
3. Decision: Below-cost flagged? --> Yes: warning to Sales Manager; No: continue
4. Decision: Discount above threshold? --> Yes: needs VP approval; No: continue
5. Quotation sent to Customer -- sent
6. Decision: Customer response? --> Accepted: accepted; Rejected: rejected; No response: expired
7. Accepted quotation auto-converts to Sales Order -- converted_to_order
8. Sales Order created -- draft; Sales Manager confirms -- confirmed
9. Decision: Credit limit check (soft/hard modes) --> Exceeded: block or warn
10. Decision: Fulfillment type? --> Make-to-order: trigger Production Order; Make-to-stock: check inventory
11. Production completes --> in_production --> partially_delivered --> delivered
12. All items delivered --> invoiced (AR Invoice created)
13. Decision: Cancel at any point? --> cancelled (terminal)

**States (Quotation):** `draft --> sent --> accepted --> converted_to_order | rejected | expired`
**States (Sales Order):** `draft --> confirmed --> in_production --> partially_delivered --> delivered --> invoiced | cancelled`

**Outputs to:** Production (make-to-order), Inventory (stock check), Delivery (fulfillment), AR (invoicing)

**Sub-flow:** Price List Management (create/update price lists with effective dates)

---

### A22. Dashboard -- Role-Based KPIs

**Swimlanes:** System, Executive, Department Manager, Employee

**Flow:**
1. System aggregates KPI data from all modules
2. Decision: User role? --> routes to appropriate dashboard type
3. Executive Dashboard: company-wide KPIs (revenue, expenses, headcount, production output)
4. Department Manager Dashboard: department-scoped metrics (attendance, budget utilization, production targets)
5. Employee Dashboard: personal data (payslips, leave balance, loan status, attendance)
6. Production Dashboard: order status, machine utilization, QC pass rates
7. HR Dashboard: headcount, turnover, recruitment pipeline
8. Accounting Dashboard: GL balances, AP/AR aging, cash position
9. Warehouse Dashboard: stock levels, low-stock alerts, pending MRQs
10. Output: Department-scoped data (middleware enforced), only admin/executive/VP see all departments

**7 Dashboard Types:** Executive, Production, HR, Accounting, Warehouse, Sales, Employee

---

## Part B: 4 Cross-Module Integration Flowcharts

### B1. Purchase-to-Pay (P2P) -- End-to-End

**Swimlanes:** Department, Procurement, Vendor, Warehouse/Inventory, AP, Accounting, Budget

**Flow:**
1. Department identifies need
2. Budget module checks annual allocation --> Decision: within budget?
3. PR created and goes through 4-step approval (Head -> Manager -> Officer -> VP)
4. PO auto-created from approved PR
5. PO sent to Vendor
6. Vendor delivers goods
7. Warehouse creates Goods Receipt
8. Decision: Goods match PO? 
9. GR confirmed --> Inventory stock-in (stock ledger +)
10. AP Clerk creates invoice --> 3-Way Match (PO vs GR vs Invoice)
11. AP Invoice goes through 4-step approval
12. GL entry auto-posted on approval
13. Payment processed --> partially_paid or paid
14. Tax module receives EWT/VAT data

---

### B2. Order-to-Cash (O2C) -- End-to-End

**Swimlanes:** Customer/CRM, Sales, Production, QC, Inventory, Delivery, AR, Accounting

**Flow:**
1. Customer submits order via Client Portal
2. Sales reviews and negotiates
3. Order approved --> Delivery Schedule auto-created
4. Decision: Production needed? --> Yes: Production Order created; No: stock fulfillment
5. Production: BOM attached --> MR created --> materials issued from Inventory
6. Production completes --> output logged
7. QC inspects --> Decision: passed? --> Yes: release; No: NCR/rework
8. Finished goods to Inventory
9. Delivery: DR created --> dispatched --> delivered
10. Customer acknowledges receipt
11. AR Invoice created (linked to DR) --> approved --> JE auto-posted
12. Customer payment --> partially_paid or paid
13. GL updated, Output VAT to Tax

---

### B3. Payroll Cycle -- End-to-End

**Swimlanes:** HR, Attendance, Leave, Loan, Payroll Admin, System Pipeline, HR Manager, Accounting, VP, Tax

**Flow:**
1. HR provides employee master data and rates (snapshot)
2. Attendance provides: worked days, OT hours, absences, night diff hours
3. Leave provides: leave days taken, balance updates
4. Loan provides: active loan amortization amounts
5. Payroll Admin creates run, sets scope, runs pre-checks
6. System Pipeline executes 17 steps (S01-S17)
7. Computed results reviewed
8. HR Manager approves (SoD)
9. Accounting Manager approves (GL preview)
10. System disburses -- bank file + GL journal posted
11. System publishes -- payslips released
12. Accounting receives GL entry (salaries expense, deductions)
13. Tax receives WHT data for BIR 1601C filing

---

### B4. Inventory Flow -- End-to-End

**Swimlanes:** Procurement, Warehouse, Production, QC, Delivery, System/Ledger

**Flow:**
1. Procurement GR confirmed --> stock ledger: goods_receipt (+)
2. Production MR fulfilled --> stock ledger: issued (-)
3. Production output completed --> stock ledger: production_output (+)
4. QC inspection --> Decision: passed? --> release; failed? --> quarantine
5. Delivery dispatched --> stock ledger: delivery (-)
6. Manual adjustments (cycle count, scrap, correction) --> stock ledger: adjustment (+/-)
7. Decision: Balance below reorder point? --> low-stock alert --> may trigger PR
8. Stock reservations block inventory for open production orders
9. Lot/batch tracking for FIFO costing and traceability

---

## Implementation Notes

### File Structure
- **Single file:** `docs/architecture/THESIS_FLOWCHARTS.md`
- **Table of Contents** at top with anchor links to each of the 24 flowcharts
- Each flowchart has: Title, Description, Actor/Swimlane legend, Mermaid diagram, Key Rules summary

### Mermaid Compatibility
- Avoid double quotes inside square brackets -- use single quotes or no quotes
- Avoid parentheses inside square brackets
- Use `---` separators between sections
- Test rendering compatibility with GitHub Markdown preview

### Swimlane Implementation
Mermaid `flowchart TD` with `subgraph` blocks per role. Arrows crossing subgraph boundaries show handoffs. Example structure:

```
flowchart TD
    subgraph EMPLOYEE[Employee]
        ...
    end
    subgraph DEPT_HEAD[Department Head]
        ...
    end
    subgraph SYSTEM[System]
        ...
    end
    %% Cross-swimlane arrows
    EMPLOYEE_NODE --> DEPT_HEAD_NODE
```

### Estimated Scope
- 20 module flowcharts (A1-A20)
- 4 cross-module flowcharts (B1-B4)
- 1 role/approval matrix summary table
- 1 module interconnection overview diagram (already exists, will be enhanced)
- Total: ~25 Mermaid diagrams in one document
