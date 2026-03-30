# Ogami ERP - Comprehensive Module Functional Gap Analysis

> **Purpose:** Identify all functional gaps in existing modules where backend capabilities exist but frontend UI is missing, workflows are incomplete, or cross-module integration is broken.
> **Date:** 2026-03-30

---

## Executive Summary

After cross-referencing all 22 backend domains (controllers, services, routes) against frontend pages, hooks, and router entries, I identified **47 functional gaps** across 3 severity levels:

| Severity | Count | Description |
|----------|-------|-------------|
| CRITICAL | 8 | Module has backend but NO frontend pages at all |
| HIGH | 19 | Specific workflow steps missing frontend UI |
| MEDIUM | 20 | Backend features exist but are not exposed in UI |

---

## CRITICAL GAPS: Modules With No Frontend Pages

### GAP-01: ISO Module -- Backend exists, frontend is EMPTY
- **Backend:** `ISOController` with full CRUD for controlled documents, revisions, distributions, internal audits, audit findings, improvement actions
- **Routes:** `routes/api/v1/iso.php` -- all endpoints defined
- **Frontend:** Zero pages. Dashboard links to `/iso/documents` and `/iso/audits` exist in PlantManagerDashboard and HeadDashboard but these routes lead to 404.
- **Impact:** ISO 9001 compliance module is completely unusable from the UI
- **Fix:** Create 6 pages: DocumentListPage, DocumentDetailPage, AuditListPage, AuditDetailPage, FindingDetailPage, ImprovementActionPage

### GAP-02: CRM Leads and Opportunities -- Backend exists, no list/detail pages
- **Backend:** `LeadController`, `OpportunityController` with full CRUD, lead scoring, conversion, disqualification
- **Frontend hooks:** `useCRM.ts` has all mutations (createLead, updateLead, convertLead, disqualifyLead, createOpportunity)
- **Frontend pages:** Only `CrmDashboardPage` exists. No LeadListPage, LeadDetailPage, OpportunityListPage, OpportunityDetailPage
- **Impact:** Sales team cannot manage leads or opportunities from the UI
- **Fix:** Create 4 pages: LeadListPage, LeadDetailPage, OpportunityListPage, OpportunityDetailPage

### GAP-03: Sales Client Orders Review -- Backend exists, frontend page incomplete
- **Backend:** `ClientOrderController` with negotiation, counter-proposals, VP approval
- **Frontend pages:** `ClientOrdersReviewPage` and `ClientOrderDetailPage` exist in `/pages/sales/` but are NOT in the router
- **Router:** No `/sales/client-orders` or `/sales/client-orders/:id` routes
- **Impact:** Staff-side client order review and negotiation is inaccessible
- **Fix:** Add routes for sales client order pages in router

### GAP-04: Tax Module -- Only 1 of 3 pages wired
- **Backend:** `BirFilingController` (filing tracking), `VatLedgerController` (VAT reconciliation), `GovernmentReportsController` (1601C, 2316, 2307)
- **Frontend pages:** `BirFormGeneratorPage` exists but VatLedgerPage and TaxPeriodSummaryPage are in accounting directory, not tax
- **Router:** VAT Ledger is under `/accounting/vat-ledger`, Tax Period under `/accounting/tax-period-summary`
- **Impact:** BIR filing tracking (create/track/mark-filed) has no dedicated UI
- **Fix:** Create BirFilingListPage + wire BirFormGeneratorPage properly

### GAP-05: HR Reports Page -- Backend services exist, page exists but limited
- **Backend:** `GovernmentReportsController` generates 1601C, 2316, 2307 forms; `EmployeeController` has DTR
- **Frontend:** `HRReportsPage` exists but may not expose all government report endpoints
- **Impact:** HR cannot generate BIR compliance reports from the UI
- **Fix:** Verify HRReportsPage covers all government report endpoints

---

## HIGH GAPS: Missing Workflow Steps in Frontend

### Supply Chain

#### GAP-06: Procurement -- No PO Edit page
- PurchaseOrderDetailPage has view + workflow actions but no dedicated edit form
- CreatePurchaseOrderPage exists for creation but PO items cannot be modified after creation
- **Fix:** Add edit capability to PO detail page or create EditPurchaseOrderPage

#### GAP-07: Procurement -- Vendor RFQ has no Create page
- VendorRfqListPage and VendorRfqDetailPage exist
- `useVendorRfqs.ts` has `useCreateVendorRfq` mutation
- No CreateVendorRfqPage exists -- must be created inline on the list page
- **Fix:** Verify RFQ creation works from list page or create dedicated form

#### GAP-08: Inventory -- No Edit page for Material Requisitions
- CreateMaterialRequisitionPage and MaterialRequisitionDetailPage exist
- No way to edit an MRQ after creation (add/remove items before submission)
- **Fix:** Add edit capability to MRQ detail (draft status only)

#### GAP-09: Production -- No MRP/Capacity Planning pages
- Backend: `MrpService`, `CapacityPlanningService` with full algorithms
- Frontend hooks: `useProduction.ts` does not expose MRP or capacity planning queries
- No pages for MRP results or capacity utilization
- **Fix:** Create MrpResultsPage, CapacityPlanningPage, and add hooks

#### GAP-10: Production -- No Work Center / Routing management pages
- Backend: `WorkCenterController`, `RoutingController` with CRUD
- No frontend pages for work center or routing management
- **Fix:** Create WorkCenterListPage, RoutingListPage

#### GAP-11: QC -- No SPC Dashboard is a stub
- Backend: `SpcService` with control chart calculations
- SpcDashboardPage is imported in router but may be a minimal stub
- **Fix:** Verify SPC dashboard actually renders charts with real data

#### GAP-12: Delivery -- Missing fleet management UI
- Backend: Vehicle model exists, route management exists
- Frontend: DeliveryRoutesPage exists but no VehicleListPage or fleet management
- **Fix:** Create VehicleListPage for fleet management

### HR and People

#### GAP-13: HR -- Employee Document Upload missing from form
- Backend: `EmployeeDocument` model, onboarding checklist service
- Frontend: EmployeeFormPage and EmployeeDetailPage exist but document upload UI is unclear
- **Fix:** Verify document upload works on employee detail page

#### GAP-14: HR -- Performance Appraisal has no dedicated pages
- Backend: `PerformanceAppraisalService` with 4-step workflow
- Frontend hooks: `useEnhancements.ts` has `useCreateAppraisal`, `useAppraisalAction`
- No dedicated AppraisalListPage or AppraisalDetailPage
- **Fix:** Create AppraisalListPage, AppraisalDetailPage

#### GAP-15: HR -- Employee Clearance has no frontend pages
- Backend: `EmployeeClearanceService` for offboarding
- No frontend pages for clearance workflow
- **Fix:** Create ClearanceListPage, ClearanceDetailPage

#### GAP-16: Attendance -- Shift Assignment management is limited
- Backend: Full shift schedule CRUD + employee assignment
- Frontend: ShiftsPage exists for schedule management
- Shift assignment to employees is done on EmployeeFormPage but bulk assignment is missing
- **Fix:** Add bulk shift assignment UI

#### GAP-17: Leave -- SIL Monetization has no frontend page
- Backend: `SilMonetizationService` for Service Incentive Leave payout
- No frontend page or action to trigger monetization
- **Fix:** Add SIL monetization action to leave balances page

#### GAP-18: Loan -- Loan Form page is lazy-loaded but prefixed with underscore
- Router imports `_LoanFormPage` (underscore prefix suggesting it may be disabled)
- Loan creation may only work from team management pages
- **Fix:** Verify loan creation workflow works end-to-end

### Finance

#### GAP-19: Accounting -- JE Template management page missing
- Backend: `RecurringJournalTemplateController` with full CRUD
- Frontend: `RecurringTemplatesPage` exists and is routed
- But `JournalEntryTemplate` (one-time templates, not recurring) has no dedicated management UI
- **Fix:** Add template selection to JE creation form

#### GAP-20: Accounting -- Account Mapping management not in UI
- Backend: `AccountMapping` model for GL account mapping configuration
- Admin reference table for `AccountMappingsTable` exists but may not be fully wired
- **Fix:** Verify account mappings are accessible from admin reference tables

#### GAP-21: AP -- Vendor Fulfillment Notes have no UI
- Backend: `VendorFulfillmentService`, `VendorFulfillmentNote` model
- No frontend page to manage fulfillment notes
- **Fix:** Add fulfillment notes section to vendor invoice or PO detail page

#### GAP-22: AP -- EWT Rate management not in UI
- Backend: `EwtService`, `EwtRate` model
- No admin page to manage EWT rates
- **Fix:** Add EWT rates table to admin reference tables

#### GAP-23: Budget -- Budget Amendment workflow has no dedicated page
- Backend: `BudgetAmendmentService` with full approval workflow
- Frontend hooks: `useEnhancements.ts` has `useCreateBudgetAmendment`, `useBudgetAmendmentAction`
- DepartmentBudgetsPage exists but may not surface amendment workflow
- **Fix:** Add budget amendment section to department budgets page

#### GAP-24: Fixed Assets -- Asset Transfer and Disposal have no dedicated forms
- Backend: `AssetTransfer`, `AssetDisposal` models with services
- FixedAssetDetailPage exists but transfer/disposal UI may be incomplete
- **Fix:** Verify transfer and disposal actions work from detail page

---

## MEDIUM GAPS: Backend Features Not Exposed in UI

| # | Module | Feature | Backend | Frontend Status |
|---|--------|---------|---------|----------------|
| GAP-25 | HR | Org Chart | `OrgChartService` | No OrgChartPage |
| GAP-26 | HR | Competency Matrix | Model exists | No UI |
| GAP-27 | HR | Training Records | Model exists | No UI |
| GAP-28 | Attendance | Timesheet Approval | `TimesheetApproval` model | No approval UI |
| GAP-29 | Attendance | Anomaly Resolution | `AnomalyResolutionService` | No dedicated UI |
| GAP-30 | Leave | Leave Conflict Detection | `LeaveConflictDetectionService` | Not surfaced in leave form |
| GAP-31 | Payroll | 13th Month Accrual tracking | `ThirteenthMonthAccrualService` | No dedicated report |
| GAP-32 | Payroll | Final Pay computation | `FinalPayService` | No dedicated page |
| GAP-33 | Inventory | Stock Reservation management | `StockReservationService` | No reservation UI |
| GAP-34 | Inventory | Lot/Batch tracking | `LotBatch` model | No lot management UI |
| GAP-35 | Production | Where-Used report | `BomService::whereUsed()` | No page |
| GAP-36 | Production | Production Report | `ProductionReportService` | No report page |
| GAP-37 | QC | Supplier Quality scoring | `SupplierQualityService` | SupplierQualityPage exists but verify data |
| GAP-38 | QC | Quarantine management | `QuarantineService` | QuarantineManagementPage exists -- verify |
| GAP-39 | Delivery | Proof of Delivery | `ProofOfDeliveryService` | No upload/capture UI |
| GAP-40 | Delivery | ImpEx Documents | `ImpexDocument` model | No management UI |
| GAP-41 | Sales | Price List management | `PricingService`, `PriceList` model | No PriceListPage |
| GAP-42 | CRM | Order Tracking | `OrderTrackingService` | No tracking page |
| GAP-43 | CRM | Sales Analytics | `SalesAnalyticsService` | In CrmDashboardPage -- verify |
| GAP-44 | Maintenance | PM Schedule management | `PmSchedule` model | No dedicated PM calendar |

---

## Cross-Module Integration Gaps

| # | Chain | Gap |
|---|-------|-----|
| GAP-45 | Client Order -> Production | Auto-creation works via listener but no UI shows the link. Client order detail page should show linked production order. |
| GAP-46 | Production -> Delivery | Auto-DR creation works but delivery receipt list doesn't filter by production order. |
| GAP-47 | Fixed Assets -> Accounting | Monthly depreciation auto-posting works but no report shows depreciation schedule by asset. |

---

## Prioritized Fix Plan

### Sprint 1: Critical (Must-have for basic functionality)
1. **GAP-01** -- Create ISO module frontend (6 pages)
2. **GAP-02** -- Create CRM Lead + Opportunity pages (4 pages)
3. **GAP-03** -- Wire sales client order review routes
4. **GAP-09** -- Create MRP/Capacity Planning pages
5. **GAP-10** -- Create Work Center/Routing pages

### Sprint 2: High (Complete workflow gaps)
6. **GAP-04** -- Create BIR Filing tracking pages
7. **GAP-14** -- Create Performance Appraisal pages
8. **GAP-15** -- Create Employee Clearance pages
9. **GAP-17** -- Add SIL Monetization action
10. **GAP-25** -- Create Org Chart page

### Sprint 3: Medium (Polish and completeness)
11. **GAP-32** -- Create Final Pay page
12. **GAP-35** -- Create Where-Used report page
13. **GAP-39** -- Add Proof of Delivery capture UI
14. **GAP-40** -- Create ImpEx Document management
15. **GAP-41** -- Create Price List management page

### Sprint 4: Integration and Reports
16. **GAP-45/46** -- Add cross-module document chain links
17. **GAP-31** -- Add 13th Month Accrual report
18. **GAP-36** -- Create Production Report page
19. **GAP-47** -- Create Depreciation Schedule report
20. All remaining medium gaps

---

## Files Reference

### Modules with ZERO frontend pages
- ISO: `app/Http/Controllers/ISO/ISOController.php` -- needs 6+ pages
- CRM Leads/Opportunities: `app/Http/Controllers/CRM/LeadController.php`, `OpportunityController.php` -- needs 4 pages

### Pages that exist but are NOT routed
- `frontend/src/pages/sales/ClientOrdersReviewPage.tsx` -- not in router
- `frontend/src/pages/sales/ClientOrderDetailPage.tsx` -- not in router

### Hooks that exist but have no pages consuming them
- `useAnalytics.ts` -- used in dashboards but no dedicated analytics pages
- `useHRReports.ts` -- HRReportsPage exists but verify coverage
- `useMaintenanceAnalytics.ts` -- MaintenanceAnalyticsPage exists -- verify
- `useBirForms.ts` -- BirFormGeneratorPage exists -- verify wiring
