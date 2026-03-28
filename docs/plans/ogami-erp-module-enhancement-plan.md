# Ogami ERP -- Comprehensive Module Enhancement Plan

## Current State Summary

| Metric | Count |
|--------|-------|
| Domain modules | 20 |
| Eloquent models | 108 |
| Domain services | 95 (7 missing ServiceContract) |
| Controllers | 60+ |
| Form Requests | 82 |
| API Resources | 63 |
| Policies | 37 |
| Notifications | 54 |
| Events/Listeners | 16/17 |
| State Machines | 2 (HR + Payroll only) |
| Migrations | 201 |
| Frontend hooks | 51 |
| Frontend Zod schemas | 22 (all 20 domains covered) |
| Frontend tests | 9 |
| Backend tests | ~50 feature test files |
| Queue Jobs | 6 |
| Exports | 6 (payroll/gov only) |

---

## TIER 1 -- Critical Gaps (Thesis-blocking)

### 1. Activity Log / Audit Trail (ALL modules)
**Gap:** Zero audit trail. No `activity_log` table, no `LogsActivity` trait. For a real ERP and thesis defense, auditors need to see who changed what and when.

**Enhancement:**
- Install `spatie/laravel-activitylog`
- Add `LogsActivity` trait to all 108 models
- Create `AuditLogController` + frontend page at `/admin/audit-log`
- Log all create/update/delete + status transitions
- Include `causer` (user), `properties` (old/new values), `description`
- Add `caused_by` filter, date range filter, domain filter

### 2. State Machines for ALL Stateful Domains (10 missing)
**Gap:** Only HR and Payroll have formal state machines. CRM, Procurement (PR/PO), AP, AR, Leave, Loan, Delivery, Maintenance, QC, ISO all have ad-hoc status transitions scattered in services.

**Enhancement:**
- Create `StateMachine` classes for: `PurchaseRequest`, `PurchaseOrder`, `VendorInvoice`, `CustomerInvoice`, `LeaveRequest`, `Loan`, `DeliveryReceipt`, `WorkOrder`, `Inspection`, `ClientOrder`
- Each holds a `TRANSITIONS` constant defining valid from->to pairs
- Centralize `canTransitionTo()` and `transitionTo()` logic
- Enforce transitions in services -- reject invalid state changes with `InvalidStateTransitionException`

### 3. Missing API Resources for CRM + Budget
**Gap:** CRM and Budget have zero API Resources -- controllers return raw model JSON, exposing all `$fillable` columns including internal IDs.

**Enhancement:**
- Create `ClientOrderResource`, `ClientOrderCollection`, `TicketResource`, `TicketCollection`
- Create `BudgetLineResource`, `BudgetLineCollection`, `CostCenterResource`
- Ensure all controllers use Resources (no `->toArray()` or raw model returns)

### 4. ServiceContract Compliance (7 services missing)
**Gap:** 7 services don't implement `ServiceContract` -- violates ARCH-002.

**Fix:**
- `AP\InvoiceAutoDraftService`
- `AR\InvoiceAutoDraftService`
- `Attendance\AnomalyResolutionService`
- `Budget\BudgetEnforcementService`
- `HR\OnboardingChecklistService`
- `Inventory\LowStockReorderService`
- `Production\OrderAutomationService`

---

## TIER 2 -- Production Readiness

### 5. Approval Workflow Engine (Shared)
**Gap:** Each module reimplements approval logic independently. There's no shared approval framework.

**Enhancement:**
- Create `Shared\Concerns\HasApprovalWorkflow` trait
- Define approval steps per model via a `approvalSteps()` method
- Shared `ApprovalLog` model (polymorphic: `approvable_type`, `approvable_id`)
- Records who approved, when, at what stage, with what remarks
- Frontend: reusable `<ApprovalTimeline>` component (already exists, wire to backend)

### 6. Dashboard KPIs per Module
**Gap:** Only Executive Dashboard exists. Department heads need their own KPI dashboards.

**Enhancement:**
- **Procurement:** Open PRs by urgency, PO cycle time, vendor performance
- **Inventory:** Stock turnover rate, dead stock, reorder alerts
- **Production:** OEE (Overall Equipment Effectiveness), yield rate, throughput
- **QC:** Defect rate, inspection pass rate, open NCRs/CAPAs
- **HR:** Headcount by department, turnover rate, onboarding pipeline
- **Accounting:** Cash position, aging receivables/payables, GL health

### 7. Export/Import for All Major Modules
**Gap:** Only payroll/government reports have exports. No CSV import for inventory, HR, etc.

**Enhancement:**
- Add Excel/CSV export to: Inventory items, Employees, Customers, Vendors, Fixed Assets, Chart of Accounts
- Add CSV import for: Employee master data, Item master data, Opening balances
- Use `maatwebsite/excel` (already installed for payroll exports)
- Frontend: `<ImportButton>` component matching existing `<ExportButton>` pattern

### 8. Scheduled Task Coverage
**Gap:** Only 2 scheduled commands visible. Missing critical automated tasks.

**Enhancement:**
- Daily: AP due date alerts, Attendance anomaly detection, Leave balance accrual
- Weekly: Inventory reorder check, Overdue work orders alert
- Monthly: Fixed asset depreciation run, Budget utilization report
- Add `php artisan schedule:list` visibility and health check endpoint

---

## TIER 3 -- Polish & Differentiation

### 9. Frontend Test Coverage
**Gap:** Only 9 frontend tests. For thesis grade, need at least 30+.

**Enhancement:**
- Test all batch operation flows (Leave, PR, OT, AP)
- Test form validation (PR create, Leave file, Invoice create)
- Test permission-gated UI (buttons hidden/shown correctly)
- Test pagination, filtering, search behavior
- Test dark mode toggle persistence

### 10. PDF Report Generation
**Gap:** PDF exists for some modules but missing for key documents.

**Enhancement:**
- Delivery Receipt PDF (partially done)
- Purchase Request PDF (for printing/signing)
- Budget Variance Report PDF
- Aging Report PDF (AR/AP)
- Employee 201 File summary PDF
- QC Inspection Certificate PDF

### 11. Real-time Notifications (WebSocket)
**Gap:** Notifications exist but are poll-based. No real-time push.

**Enhancement:**
- Wire Laravel Reverb (already in `dev:full` script)
- Broadcast approval notifications in real-time
- Show notification bell count update without page refresh
- Toast notifications for batch operation results across tabs

### 12. Data Validation Tightening
**Gap:** Some endpoints lack proper validation or have loose rules.

**Enhancement:**
- Ensure all monetary inputs validated as `integer|min:0` (centavos)
- Add cross-field validation (e.g., `date_to >= date_from`)
- Add business rule validation in Form Requests (e.g., leave balance check)
- Wire Zod `safeParse` in dev mode for frontend API response validation

---

## TIER 4 -- Nice-to-Have (Thesis Bonus Points)

### 13. Role-Based Dashboard Routing
Different dashboards per role -- already partially done but incomplete.

### 14. Document Attachment System
File uploads for POs, invoices, delivery receipts, inspection evidence.

### 15. Multi-Currency Support
For international vendors -- store exchange rate, convert to PHP.

### 16. Configurable Approval Chains
Admin UI to configure approval levels per document type per department.

### 17. API Rate Limiting & Throttling
Per-user rate limits for API endpoints.

### 18. Comprehensive API Documentation
OpenAPI/Swagger spec auto-generated from Form Requests + Resources.

---

## Recommended Implementation Order

| Priority | Item | Effort | Impact |
|----------|------|--------|--------|
| **P0** | Fix 7 ServiceContract violations | 1h | Arch compliance |
| **P0** | State machines for 10 domains | 4h | Data integrity |
| **P1** | Activity log / audit trail | 3h | Thesis requirement |
| **P1** | CRM + Budget API Resources | 2h | API consistency |
| **P1** | Approval workflow trait + ApprovalLog | 4h | Code dedup |
| **P2** | Dashboard KPIs per module | 6h | UX completeness |
| **P2** | Export/Import expansion | 4h | Usability |
| **P2** | Frontend test coverage (30+) | 4h | Quality proof |
| **P3** | PDF reports for all modules | 4h | Print capability |
| **P3** | Scheduled tasks expansion | 2h | Automation |
| **P3** | Real-time notifications | 3h | Modern UX |
| **P4** | Document attachments | 3h | Bonus feature |
| **P4** | API documentation | 2h | Professional polish |
