# Ogami ERP - Core Module Functional Gap Fix Plan

> **Scope:** 15 core modules only (HR, Payroll, Attendance, Leave, Loan, Procurement, Inventory, Production, QC, Delivery, Accounting, AP, AR, Tax, Budget)
> **Excluded:** ISO, CRM Leads/Opportunities, Sales, Maintenance, Mold, Recruitment, Fixed Assets
> **Goal:** Make all 15 core modules fully functional end-to-end for thesis presentation

---

## Filtered Gaps (22 actionable items)

### CRITICAL -- Broken workflows users will hit

| # | Module | Gap | What to Build |
|---|--------|-----|---------------|
| 1 | Production | MRP and Capacity Planning have backend services but no frontend pages | Create MrpResultsPage + CapacityPlanningPage + hooks |
| 2 | Production | Work Center and Routing management have controllers but no pages | Create WorkCenterListPage + RoutingListPage |
| 3 | Tax | BIR Filing tracking has full backend but no dedicated filing list/management UI | Create BirFilingListPage or verify BirFormGeneratorPage covers filing lifecycle |
| 4 | Delivery | Fleet management (vehicles) has no frontend page | Create VehicleListPage or add vehicles tab to delivery |

### HIGH -- Missing workflow steps

| # | Module | Gap | What to Build |
|---|--------|-----|---------------|
| 5 | HR | Performance Appraisal has backend service + hooks but no dedicated pages | Create AppraisalListPage + AppraisalDetailPage |
| 6 | HR | Employee Clearance (offboarding) has service but no pages | Create ClearanceListPage + ClearanceDetailPage |
| 7 | HR | Org Chart has service but no page | Create OrgChartPage |
| 8 | Leave | SIL Monetization has service but no UI action | Add monetization button/action to LeaveBalancesPage |
| 9 | Inventory | MRQ edit capability missing (cannot modify items after creation in draft) | Add edit items capability to MRQ detail page for draft status |
| 10 | Inventory | Stock Reservation has service but no management UI | Add reservations tab to StockBalancePage |
| 11 | Procurement | Vendor RFQ creation flow - verify it works from list page | Test and fix if inline creation is broken |
| 12 | QC | SPC Dashboard imported in router but may be a stub | Verify SpcDashboardPage renders real chart data |
| 13 | Payroll | Final Pay computation has service but no dedicated page | Create FinalPayPage or add to employee termination flow |
| 14 | Payroll | 13th Month accrual tracking has no report page | Create ThirteenthMonthReportPage |
| 15 | Budget | Budget Amendment workflow has no visible UI | Add amendment section to DepartmentBudgetsPage |

### MEDIUM -- Backend features not surfaced

| # | Module | Gap | What to Build |
|---|--------|-----|---------------|
| 16 | Attendance | Timesheet Approval model has no approval UI | Add timesheet approval section to attendance dashboard |
| 17 | Attendance | Anomaly Resolution service has no dedicated UI | Add anomaly list + resolution actions to attendance page |
| 18 | Leave | Conflict Detection service not surfaced in leave form | Show conflict warnings when filing leave |
| 19 | Inventory | Lot/Batch tracking has model but no management UI | Add lot/batch column to stock ledger page |
| 20 | Production | Where-Used report has service but no page | Create WhereUsedReportPage or add to BOM detail |
| 21 | Production | Production Report service has no report page | Create ProductionReportPage |
| 22 | Delivery | Proof of Delivery has service but no upload/capture UI | Add POD section to delivery receipt detail |

---

## Implementation Priority by Demo Chain

### Chain 1: HR -> Attendance -> Leave -> Loan -> Payroll -> GL

**Already working:** Employee CRUD, attendance logs/import, leave requests with 4-step approval, loan applications, payroll 18-step pipeline, GL auto-posting

**Gaps to fix (in order):**
1. Item 7 -- Org Chart page (visual wow factor for thesis demo)
2. Item 5 -- Performance Appraisal pages (shows HR depth)
3. Item 8 -- SIL Monetization action (shows leave-payroll integration)
4. Item 13 -- Final Pay page (shows termination-payroll integration)
5. Item 14 -- 13th Month report (shows Philippine compliance)
6. Item 17 -- Anomaly resolution UI (shows attendance completeness)
7. Item 18 -- Leave conflict detection in form (UX polish)

### Chain 2: PR -> PO -> GR -> Inventory -> Production -> QC -> Delivery

**Already working:** PR 4-stage approval, PO creation, GR with QC, stock management, production orders with BOM, QC inspections/NCR, delivery receipts

**Gaps to fix (in order):**
1. Item 1 -- MRP/Capacity Planning pages (thesis differentiator -- shows planning capability)
2. Item 2 -- Work Center/Routing pages (needed for MRP to make sense)
3. Item 4 -- Vehicle/Fleet page (completes delivery chain)
4. Item 9 -- MRQ edit for draft items
5. Item 12 -- Verify SPC Dashboard works
6. Item 20 -- Where-Used report (shows BOM traceability)
7. Item 21 -- Production Report page
8. Item 22 -- Proof of Delivery capture

### Chain 3: AP -> AR -> Accounting -> Tax -> Budget

**Already working:** Vendor invoices with 5-stage approval, customer invoices with 2-stage approval, GL double-entry, bank reconciliation, financial reports, VAT ledger, budget management

**Gaps to fix (in order):**
1. Item 3 -- BIR Filing tracking (shows tax compliance)
2. Item 15 -- Budget Amendment UI (shows financial control depth)
3. Item 10 -- Stock Reservation visibility (shows inventory-finance link)

---

## Suggested Execution Order (for maximum thesis impact)

### Batch 1 -- High visual impact pages (demo wow factor)
- Org Chart page (tree visualization)
- MRP Results page (demand/supply planning table)
- Capacity Planning page (work center utilization chart)
- Work Center + Routing pages (production setup)

### Batch 2 -- Complete core workflows
- Performance Appraisal list + detail
- BIR Filing tracking page
- Final Pay page
- Budget Amendment UI
- SIL Monetization button

### Batch 3 -- Polish and edge cases
- Leave conflict detection warnings
- Attendance anomaly resolution
- MRQ draft editing
- Stock reservation visibility
- Lot/batch in stock ledger
- Where-Used report
- Production report
- Proof of Delivery capture
- Vehicle/fleet page
- 13th Month report
- Timesheet approval
- SPC dashboard verification

---

## What NOT to touch (keep backend only, no frontend needed for thesis)

- ISO module (removed from frontend)
- CRM Leads/Opportunities (removed from frontend)
- Sales module (quotations, sales orders, price lists)
- Maintenance module (equipment, work orders)
- Mold module (shot tracking)
- Recruitment module (job postings, applications)
- Fixed Assets module (depreciation)
- Vendor Portal (supplementary)
- Client Portal (supplementary)

These all have working backends and can be demonstrated via API if asked, but building frontend pages for them is not worth the time before thesis.
