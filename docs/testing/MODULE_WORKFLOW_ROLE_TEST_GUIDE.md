# Ogami ERP Module Workflow And Role Test Guide

Verified on 2026-04-04 against:
- `routes/api/v1/*.php`
- domain state machines under `app/Domains/*/StateMachines/`
- approval services and policies in `app/Domains/**/Services` and `Policies`
- seeded role data in `database/seeders/*.php`
- current local test credentials in `storage/app/test-credentials.md`

This guide is meant for manual QA. It focuses on:
- what each module can do
- the actual workflow path in code
- who performs each step
- where approvals and SoD rules matter

## Role Cheat Sheet

Use these as the main personas when testing:

| Role | Typical account |
|---|---|
| Staff | `prod.staff@ogamierp.local` |
| Head | `warehouse.head@ogamierp.local`, `production.head@ogamierp.local` |
| Officer | `purchasing.officer@ogamierp.local`, `accounting@ogamierp.local`, `hr.officer@ogamierp.local`, `sales.officer@ogamierp.local` |
| Manager | `hr.manager@ogamierp.local`, `acctg.manager@ogamierp.local`, `prod.manager@ogamierp.local`, `qc.manager@ogamierp.local`, `sales.manager@ogamierp.local` |
| VP | `vp@ogamierp.local` |
| Executive | `chairman@ogamierp.local`, `president@ogamierp.local` |
| Vendor portal | `vendor@ogamierp.local` |
| Client portal | `client@ogamierp.local` |

Current local passwords are role-based and already generated in `storage/app/test-credentials.md`.

## Global Approval Rules

- `super_admin` bypasses SoD and scope. Do not use it for approval-chain validation.
- `admin` is for system settings and user management, not business approvals.
- `vice_president` is the final approver for the main financial chains.
- department `manager`, `officer`, `head`, and `staff` permissions change by department.
- many chains enforce “creator/requester cannot approve own record”.

## 1. HR

Features:
- employees
- departments
- positions
- salary grades
- leave type and loan type reference lists

Workflow:
1. HR Manager creates employee record in `draft`
2. A different authorized user transitions employee to `active`
3. Employee can later move to `on_leave`, `suspended`, `resigned`, or `terminated`
4. Resignation triggers clearance/onboarding side effects

Approval / role use:
- create/update: HR Manager
- activation: HR Manager, but creator must not activate own employee record
- self-service viewing: all employees

Manual test path:
1. Login as `hr.manager@ogamierp.local`
2. Create a new employee
3. Use a different approver account if you want to validate activation SoD
4. Transition to `active`
5. Transition to `resigned` and confirm downstream checklist/clearance behavior

## 2. Recruitment

Features:
- requisitions
- job postings
- candidates
- applications
- interviews
- offers
- pre-employment checklist
- hiring with VP approval
- recruitment dashboard and reports

Workflow:
1. Create requisition: `draft`
2. Submit requisition: `pending_approval`
3. Approve or reject requisition
4. Open approved requisition for hiring
5. Create and publish posting
6. Receive candidate/application
7. Review application: under review -> shortlisted or rejected
8. Schedule and complete interviews, submit evaluations
9. Prepare and send offer
10. Candidate accepts or rejects
11. Initialize and complete pre-employment requirements
12. Submit hiring request for VP approval
13. VP approves -> employee record is created and requisition may auto-close if headcount is filled

Approval / role use:
- requisition creation/submission: HR Officer, HR Head, HR Manager
- requisition approval/rejection: HR Manager or VP if that permission is assigned
- offer preparation/sending: HR team
- final hiring approval: VP

Manual test path:
1. `hr.officer@ogamierp.local` creates and submits requisition
2. `hr.manager@ogamierp.local` approves and opens it
3. HR creates posting and application
4. HR reviews, shortlists, interviews, and sends offer
5. Accept the offer
6. Complete pre-employment
7. Submit hiring request
8. `vp@ogamierp.local` approves final hire

## 3. Attendance

Features:
- attendance logs
- CSV import
- shifts and shift assignments
- time in / time out
- geofence/work locations
- overtime requests
- correction requests

Workflow:
- Logs and shifts are operational, not approval-driven
- Correction requests: `draft -> submitted -> approved|rejected`
- Overtime has two live paths in service code:
  - staff/head/supervisor path: `pending -> supervisor_approved -> manager_checked -> officer_reviewed -> approved`
  - manager-filed OT path: `pending_executive -> approved`

Approval / role use:
- logs/import/shifts: HR team
- OT submitter: employee
- OT first endorsement: department head/supervisor
- OT manager check: manager
- OT officer review: HR/Admin officer
- OT final approval: VP
- OT for managers: Executive approval path
- correction review: HR reviewer

Manual test path:
1. Use staff account to file OT
2. Head endorses
3. Manager checks
4. HR/Officer reviews
5. VP approves
6. Separately create an attendance correction request and have HR approve or reject it

## 4. Leave

Features:
- leave requests
- leave balances
- leave calendar
- overlap/calendar exports

Workflow:
1. Employee files leave -> `submitted`
2. Department Head approves -> `head_approved`
3. Manager checks -> `manager_checked`
4. GA Officer processes:
   - `approved_with_pay` or `approved_without_pay` -> `ga_processed`
   - `disapproved` -> `rejected`
5. VP notes final approved leave -> `approved`
6. Cancellation allowed only while still cancellable

Approval / role use:
- employee: file own leave
- head: first approval
- manager: second review
- GA / HR officer: payroll/balance processing step
- VP: final note
- reject can happen at any pending stage by the responsible approver

Manual test path:
1. Staff files leave
2. Head approves
3. Manager checks
4. HR/GA officer processes with pay or without pay
5. VP notes final approval
6. Repeat once with rejection at a mid-stage

## 5. Payroll

Features:
- pay periods
- payroll runs
- scope preview/exclusions
- pre-run validation
- compute pipeline
- breakdown/review
- adjustments
- GL preview/posting
- payslips and exports

Workflow:
1. Run starts in `DRAFT`
2. Confirm scope -> `SCOPE_SET`
3. Pre-run validation -> `PRE_RUN_CHECKED`
4. Compute -> `PROCESSING -> COMPUTED`
5. Review -> `REVIEW`
6. Submit -> `SUBMITTED`
7. HR approval -> `HR_APPROVED`
8. Accounting approval -> `ACCTG_APPROVED`
9. VP approval -> `VP_APPROVED`
10. Disburse -> `DISBURSED`
11. Publish payslips -> `PUBLISHED`
12. Return/reject paths reset back to `DRAFT`

Approval / role use:
- initiate, scope, compute, review: HR Manager
- HR approval: HR Manager who did not initiate the run
- accounting approval: Accounting Manager/Officer with `payroll.acctg_approve`, not the initiator
- VP approval: VP
- disburse/post/publish: Accounting/authorized payroll finance user

Manual test path:
1. `hr.manager@ogamierp.local` creates a payroll run
2. Set scope, pre-run validate, compute, review, submit
3. Use a different HR approver if testing strict SoD
4. `acctg.manager@ogamierp.local` or authorized accounting approver approves
5. `vp@ogamierp.local` approves
6. Disburse and publish

## 6. Loans

Features:
- employee loan application
- approval workflow
- amortization schedule
- accounting approval/disbursement
- payments
- SOA export

Workflow:
1. Employee applies -> `pending`
2. Head note -> `head_noted`
3. Manager check -> `manager_checked`
4. Officer review -> `officer_reviewed`
5. VP approval -> `ready_for_disbursement`
6. Accounting approval may also be required depending on flow/version
7. Disburse -> `active`
8. Pay down through schedule until `fully_paid` or mark `written_off`

Approval / role use:
- applicant: employee/staff
- head note: department head
- manager check: manager
- officer review: HR/Admin officer
- VP approve: VP
- accounting approve: accounting approver
- disburse: accounting/disbursement user, and must differ from accounting approver

Manual test path:
1. Staff applies
2. Head notes
3. Manager checks
4. Officer reviews
5. VP approves
6. Accounting approves if required in your flow
7. Disburse
8. Record payment and inspect amortization schedule

## 7. Accounting

Features:
- chart of accounts
- fiscal periods
- journal entries and templates
- recurring templates
- bank accounts
- bank reconciliations
- GL/trial balance/balance sheet/income statement/cash flow reports

Workflow:
- Journal Entry:
  1. create `draft`
  2. submit `submitted`
  3. post `posted`
  4. reverse if needed
- Bank Reconciliation:
  1. create `draft`
  2. import bank transactions
  3. match/unmatch lines
  4. certify `certified`

Approval / role use:
- JE create/submit: Accounting Officer or Manager
- JE post: Accounting Manager; poster must differ from draft creator for manual JEs
- bank reconciliation create: Accounting Officer/Manager
- bank reconciliation certify: Accounting Manager; certifier must differ from drafter

Manual test path:
1. Accounting Officer creates and submits a JE
2. Accounting Manager posts it
3. Create a bank reconciliation
4. Import statement lines and match them
5. Have a different approver certify it

## 8. Accounts Payable

Features:
- vendors
- vendor portal account provisioning
- vendor items
- AP dashboard / due soon
- vendor invoices
- vendor credit notes
- payments
- payment batches
- vendor scorecards

Workflow:
- Vendor lifecycle: create -> accredit/suspend -> provision portal account
- Vendor Invoice workflow:
  1. create `draft`
  2. submit -> `pending_approval`
  3. head note -> `head_noted`
  4. manager check -> `manager_checked`
  5. officer review -> `officer_reviewed`
  6. approve -> `approved`
  7. record payment -> `partially_paid|paid`
- Rejections return invoice to `draft`

Approval / role use:
- invoice creation/submission: Accounting Officer
- head note/check/review: department finance approvers in sequence
- final approval: Accounting Manager / VP-level approver depending permission assignment
- record payment: Accounting/AP

Manual test path:
1. Create vendor
2. Create or auto-draft AP invoice from PO/GR
3. Submit invoice
4. Run head note -> manager check -> officer review -> final approve
5. Record payment
6. Also test credit note posting

## 9. Accounts Receivable

Features:
- customers
- portal account provisioning
- customer invoices
- payments
- customer credit notes
- aging reports
- statements
- dunning notices

Workflow:
1. Create invoice in `draft`
2. Optional submit step -> `submitted`
3. Approve -> `approved`
4. Receive payment -> `partially_paid|paid`
5. Write off if needed
6. Dunning notices escalate overdue receivables

Approval / role use:
- create invoice: Sales/Accounting
- approve invoice: Accounting Manager or authorized AR approver
- approver must differ from creator, and for submitted invoices must also differ from submitter

Manual test path:
1. Create customer
2. Create AR invoice linked to delivered DR if available
3. Submit
4. Approve with different approver
5. Receive partial payment then full payment
6. Generate statement and aging report

## 10. Tax

Features:
- VAT ledger
- BIR filings
- overdue/calendar views
- VAT return / withholding / Form 2307 endpoints

Workflow:
1. VAT ledger accumulates from AP/AR activity
2. Review VAT ledger period
3. Close VAT period
4. Schedule BIR filing
5. Mark filing as filed or amended

Approval / role use:
- Accounting/Tax users manage the process
- no multi-step approval chain is exposed in routes

Manual test path:
1. Create AP and AR transactions first
2. Open VAT ledger and verify input/output VAT
3. Close the ledger period
4. Schedule a BIR filing and mark it filed

## 11. Budget

Features:
- cost centers
- annual budget lines
- department budgets
- utilisation
- variance and forecast views

Workflow:
1. Create cost center
2. Set budget line -> `draft`
3. Submit budget -> `submitted`
4. Approve -> `approved` or reject -> `rejected`
5. Use utilisation/variance views for control reporting

Approval / role use:
- create cost center / set budget: Accounting Manager
- submit: budget owner / finance user
- approve/reject: VP or authorized budget approver
- submitter cannot also approve the same budget line

Manual test path:
1. Create cost center
2. Add annual budget line
3. Submit it
4. Approve using VP or separate approver
5. Check utilisation against posted GL activity

## 12. Fixed Assets

Features:
- asset categories
- asset registration
- depreciation run
- disposal
- depreciation export

Workflow:
1. Create asset category with GL mappings
2. Register asset -> `active`
3. Run depreciation per fiscal period
4. Asset becomes `fully_depreciated` when exhausted
5. Dispose asset -> disposal record + JE

Approval / role use:
- Accounting Manager manages categories/assets
- no explicit multi-step approval route
- operational control is through finance permissions and GL setup

Manual test path:
1. Create category with GL accounts
2. Register asset
3. Run depreciation for an open period
4. Dispose asset and verify gain/loss JE

## 13. Procurement

Features:
- purchase requests
- purchase orders
- goods receipts
- vendor RFQs
- vendor scoring
- budget check endpoint
- payment batches

Workflow:
- Purchase Request:
  1. create `draft`
  2. submit -> `pending_review`
  3. purchasing review -> `reviewed`
  4. accounting budget check -> `budget_verified`
  5. VP approve -> `approved`
  6. approved PR auto-creates PO draft
- Purchase Order:
  1. draft
  2. send -> `sent`
  3. vendor negotiation -> `negotiating`
  4. acknowledge -> `acknowledged`
  5. in transit -> `in_transit`
  6. delivered -> `delivered`
  7. receiving moves it to `partially_received|fully_received`
  8. close -> `closed`
- Goods Receipt:
  1. draft/submitted
  2. if QC needed -> `pending_qc`
  3. `qc_passed` then confirm
  4. or `qc_failed` -> rework/return to supplier

Approval / role use:
- PR creator: head/officer/requesting department
- PR review: Purchasing Officer/Manager
- PR budget check: Accounting Officer
- PR final approval: VP
- PO management: Purchasing
- GR confirmation: Purchasing or Warehouse depending permission

Manual test path:
1. Create PR as department user
2. Submit
3. Purchasing reviews
4. Accounting budget-checks
5. VP approves
6. Open auto-created PO, assign vendor, send
7. Use vendor portal to acknowledge/in-transit/delivered
8. Create GR, route through QC if applicable, then confirm

## 14. Inventory

Features:
- item categories and item masters
- warehouse locations
- stock balances and ledger
- adjustments and transfers
- physical counts
- material requisitions
- low-stock analytics and reorder creation

Workflow:
- Physical Count:
  1. `draft`
  2. start -> `in_progress`
  3. submit -> `pending_approval`
  4. approve -> `approved` and stock variances post
- Material Requisition:
  1. create `draft`
  2. submit -> `submitted`
  3. head note -> `noted`
  4. manager check -> `checked`
  5. officer review -> `reviewed`
  6. VP approve -> `approved`
  7. warehouse fulfill -> `fulfilled`

Approval / role use:
- MRQ creator: production/warehouse/requesting user
- note: head
- check: manager
- review: officer
- final approve: VP
- fulfill: warehouse
- physical count approval: supervisor/manager inventory approver

Manual test path:
1. Create item and location
2. Create MRQ
3. Push it through note/check/review/VP approve
4. Fulfill and confirm stock ledger movement
5. Create a physical count and approve variance posting

## 15. Production

Features:
- BOMs
- delivery schedules and combined delivery schedules
- production orders
- output logging
- work centers
- routings
- MRP/cost analysis endpoints

Workflow:
- BOM: draft/manage -> activate -> use in production
- Production Order:
  1. create `draft`
  2. optional release approval if `requires_release_approval = true`
  3. release -> `released`
  4. auto-create MRQ draft from BOM
  5. start -> `in_progress`
  6. log output
  7. complete -> `completed`
  8. close -> `closed`
- Rework path can move `completed -> in_progress`

Approval / role use:
- create/release/start: Production Manager / Head depending permission
- approve-release: separate approver when release approval is required; creator cannot self-approve
- MRQ fulfillment depends on Inventory chain, not Production itself

Manual test path:
1. Create and activate BOM
2. Create production order
3. If it requires release approval, approve with a different user
4. Release the order
5. Complete MRQ approval/fulfillment
6. Start production
7. Log output
8. Complete and close

## 16. Quality Control

Features:
- inspection templates
- inspections
- NCRs
- CAPA
- SPC control chart
- supplier quality views

Workflow:
- Inspection: `open -> passed|failed|on_hold|voided`
- NCR:
  1. create NCR from failed inspection or issue
  2. issue CAPA
  3. CAPA moves `draft -> assigned -> in_progress -> verification -> closed`
  4. NCR closes after CAPA completion/verification

Approval / role use:
- QC team creates templates and inspections
- QC Manager closes NCRs / oversees CAPA
- no VP-style multi-step approval chain

Manual test path:
1. Create inspection template
2. Create inspection
3. Record failing result
4. Create NCR
5. Issue CAPA
6. Complete CAPA
7. Close NCR

## 17. Maintenance

Features:
- equipment
- PM schedules
- work orders
- work order parts
- equipment and cost analytics

Workflow:
1. Create equipment
2. Create PM schedule
3. Create work order -> `open`
4. Start -> `in_progress`
5. Add parts if needed
6. Complete -> `completed`
7. PM jobs can also be auto-created by schedules or mold shot thresholds

Approval / role use:
- Maintenance team manages end-to-end
- no explicit approval chain in routes

Manual test path:
1. Create equipment
2. Add PM schedule
3. Raise corrective work order
4. Start and complete it
5. Inspect linked parts and analytics

## 18. Mold

Features:
- mold master
- shot logging
- retire/restore
- cost amortization
- lifecycle analytics

Workflow:
1. Create mold record
2. Log shots
3. When current shots reach max shots, preventive maintenance WO is auto-created
4. Retire mold when no longer usable

Approval / role use:
- Mold/Production managers manage records
- no formal approval chain

Manual test path:
1. Create mold
2. Log shots until threshold is reached
3. Verify preventive maintenance work order auto-created
4. Retire mold

## 19. Delivery

Features:
- delivery receipts
- shipment prep and status
- vehicles
- routes
- delivery export

Workflow:
- Delivery Receipt:
  1. create `draft`
  2. confirm -> `confirmed`
  3. dispatch -> `dispatched`
  4. in transit / partial delivery / delivered
- Shipment:
  1. create shipment from receipt
  2. update shipment status through delivery

Approval / role use:
- warehouse/delivery team manages process
- QC gate blocks outbound confirmation if final inspections are still open/failed
- no VP approval chain

Manual test path:
1. Create outbound delivery receipt
2. Confirm it
3. Prepare shipment
4. Mark dispatched, partial-delivered if needed, then delivered
5. Verify stock issue and linked order progression

## 20. CRM

Features:
- support tickets
- client orders
- order tracking
- client-side order creation
- CRM dashboard

Workflow:
- Ticket:
  1. open
  2. assign/reply -> `in_progress`
  3. wait on client -> `pending_client`
  4. resolve -> `resolved`
  5. close -> `closed`
  6. reopen if needed
- Client Order:
  1. client submits -> `pending`
  2. sales may negotiate -> `negotiating`
  3. client responds -> `client_responded`
  4. sales approves or rejects
  5. high-value orders escalate to `vp_pending`
  6. VP approves -> `approved`
  7. approved order auto-creates delivery schedules and can auto-create draft production orders when stock is short
  8. order then moves through `in_production -> ready_for_delivery -> dispatched -> delivered -> fulfilled`

Approval / role use:
- ticket management: Sales/CRM staff
- client order review/approve/reject/negotiate: Sales Head/Manager
- high-value client order approval: VP
- submitter cannot approve own client order

Manual test path:
1. Use `client@ogamierp.local` to create order
2. Review with sales user
3. Negotiate once, then approve
4. If order value exceeds threshold, VP must approve
5. Track downstream production/delivery statuses

## 21. Sales

Features:
- pricing resolution
- quotations
- sales orders
- margin views

Workflow:
- Quotation:
  1. create `draft`
  2. send -> `sent`
  3. accept -> `accepted`
  4. convert to order -> `converted_to_order`
- Sales Order:
  1. create `draft`
  2. confirm -> `confirmed`
  3. in production -> `in_production`
  4. partial deliver -> `partially_delivered`
  5. deliver -> `delivered`
  6. invoiced -> `invoiced`

Approval / role use:
- Sales users manage this domain directly
- no extra VP approval chain in the Sales domain routes themselves
- CRM client-order approvals are separate and heavier than the internal Sales order flow

Manual test path:
1. Create quotation
2. Send and accept
3. Convert to order
4. Confirm, deliver, and mark invoiced

## 22. Vendor Portal

Features:
- vendor purchase order list/detail
- acknowledge PO
- propose changes
- mark in transit
- mark delivered
- manage vendor items
- view goods receipts
- submit vendor invoices

Workflow:
1. Internal team provisions vendor account
2. Vendor views assigned PO
3. Vendor acknowledges or proposes changes
4. Vendor marks order `in_transit`
5. Vendor marks order `delivered`
6. Internal team creates GR and AP invoice flow continues
7. Vendor can also submit invoice through portal

Approval / role use:
- vendor performs external fulfillment actions
- internal purchasing/accounting users accept/reject proposed changes and continue AP/GR processing

Manual test path:
1. Provision vendor account from AP vendor screen
2. Send PO
3. Login as vendor
4. Acknowledge, propose changes, or mark in transit/delivered
5. Return to internal users to continue GR and invoice approval chain

## 23. Administration / Approvals Queue

Features:
- user management
- roles
- department assignment
- system settings
- audit logs
- rate tables
- backup
- approvals dashboard

Workflow:
- Admin module is operational, not approval-chain driven
- Approvals dashboard is the cross-module queue for executive and VP approval work

Approval / role use:
- `admin`: system setup only
- `executive`: read-only oversight, selected executive approvals
- `vice_president`: final approval queue across PR, payroll, loans, MRQ, high-value orders, and budgets

Manual test path:
1. Use `admin@ogamierp.local` for users/settings
2. Use `vp@ogamierp.local` and executive accounts to validate approvals queue visibility

## Best End-To-End Test Flows

### Supply Chain Path
1. Procurement PR
2. PR review
3. Budget check
4. VP approval
5. PO send
6. Vendor portal acknowledge/deliver
7. GR + QC
8. AP invoice approval
9. Payment

### Make-To-Order Path
1. Client order
2. Sales approval or VP escalation
3. Delivery schedule / production order auto-creation
4. MRQ approval chain
5. MRQ fulfillment
6. Production complete
7. QC pass
8. Delivery
9. AR invoice
10. Payment

### People Operations Path
1. Recruitment requisition
2. Posting/application/interview/offer
3. Pre-employment
4. VP hire approval
5. Employee active
6. Attendance / overtime / leave
7. Payroll
8. Loan application and disbursement

## Most Important SoD Checks To Validate Manually

- employee creator must not activate own employee
- leave requester must not approve own leave
- OT requester must not approve own OT
- loan applicant must not approve or disburse own loan
- payroll initiator must not HR-approve, accounting-approve, or VP-approve own run
- JE drafter must not post own manual JE
- bank reconciliation drafter must not certify own recon
- AP invoice submitter must not final-approve own invoice
- AR invoice creator/submitter must not approve own invoice
- PR creator must not review/budget-check/VP-approve own PR
- MRQ creator must not note/check/review/VP-approve own MRQ
- client-order submitter must not approve own order

