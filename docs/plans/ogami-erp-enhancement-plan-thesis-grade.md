# Ogami ERP Enhancement Plan -- Thesis-Grade Improvements

## Goal
Transform the existing 20-module ERP from a functional prototype into a thesis-grade / real-world application by improving cross-module automation, reducing manual work, and strengthening the standard ERP workflows.

---

## Priority 1: Cross-Module Automation (Biggest Impact)

These improvements connect modules that currently require manual bridging, eliminating redundant data entry.

### 1.1 Client Order → Production → Delivery Auto-Chain
**Current:** Client order approved in CRM, but production order and delivery schedule must be created manually.
**Enhancement:** When a Client Order status changes to `approved`:
- Auto-create Production Order(s) from order line items + BOM
- Auto-create Delivery Schedule from order delivery dates
- Auto-create Material Requisition from BOM components
- Show order tracking timeline on Client Portal (order → production → QC → delivery)

**Modules connected:** CRM → Production → Inventory → Delivery
**Manual work eliminated:** 3-4 separate manual creation steps per order

### 1.2 Goods Receipt → AP Invoice Auto-Draft
**Current:** After confirming a Goods Receipt, the AP clerk must manually create the vendor invoice.
**Enhancement:** When GR is confirmed:
- Auto-draft AP Invoice pre-populated from PO + GR data (quantities, prices, vendor)
- 3-way match automatically calculated and displayed
- Discrepancies flagged with specific mismatch details

**Modules connected:** Procurement → AP → Accounting
**Manual work eliminated:** Invoice data entry, manual 3-way match calculation

### 1.3 Delivery Receipt → AR Invoice Auto-Draft
**Current:** After delivery is confirmed, AR clerk must manually create customer invoice.
**Enhancement:** When DR status becomes `delivered`:
- Auto-draft AR Invoice from DR line items + agreed prices from Client Order
- Link back to Client Order for price verification
- Customer notified via portal that invoice is ready

**Modules connected:** Delivery → AR → CRM
**Manual work eliminated:** Invoice creation, price lookup

### 1.4 Payroll → GL Auto-Posting (Strengthen)
**Current:** Payroll disbursement triggers GL posting, but the mapping may need manual adjustment.
**Enhancement:**
- Pre-configured GL account mapping per earning/deduction type (salary expense, SSS payable, etc.)
- Automatic department-level cost allocation in GL entries
- One-click reversal if payroll is returned after posting

**Modules connected:** Payroll → Accounting → Budget
**Manual work eliminated:** GL account selection, department allocation

---

## Priority 2: Workflow Intelligence (Reduce Decision Friction)

### 2.1 Smart Notification System
**Current:** Users must check dashboards to see pending items.
**Enhancement:**
- In-app notification bell with unread count
- Notification types: approval_needed, status_changed, overdue_alert, system_alert
- Click-to-navigate: clicking a notification goes directly to the relevant page
- Auto-dismiss when actioned
- Daily digest email (optional) for pending approvals

**Impact:** Every module with approval workflows (Leave, Loan, PR, PO, Payroll, AP, AR)

### 2.2 Approval Queue Consolidation
**Current:** VP/Manager must navigate to different pages to approve different types of requests.
**Enhancement:**
- Unified approval inbox at `/approvals/pending` (already partially exists)
- Shows ALL pending items across modules in one list: leaves, loans, PRs, MRQs, payroll, invoices
- Batch approve/reject with comments
- Filter by type, department, urgency, date

**Impact:** VP, Manager, Head, Officer -- all approvers

### 2.3 Low Stock Auto-Reorder
**Current:** When stock falls below reorder point, only an alert fires. Someone must manually create a PR.
**Enhancement:**
- When stock balance drops below `reorder_point`:
  - Auto-draft Purchase Request with suggested quantities (EOQ formula)
  - Pre-fill preferred vendor from last 3 POs
  - Flag as "system-generated" for fast-track approval
- Configurable per item: auto-draft vs. alert-only

**Modules connected:** Inventory → Procurement
**Manual work eliminated:** PR creation for routine reorders

---

## Priority 3: Data Quality & Validation (Thesis-Grade Rigor)

### 3.1 Attendance Anomaly Auto-Resolution
**Current:** Anomalies (missing clock-out, duplicate entries) flagged but must be resolved manually one by one.
**Enhancement:**
- Common patterns auto-resolved with rules:
  - Missing clock-out after 10+ hours → auto-set to shift end time
  - Duplicate entries → keep biometric, discard manual
- Bulk resolution tool for HR with preview
- Anomaly dashboard with trend chart (is it getting better or worse?)

### 3.2 Budget Enforcement on All Spending
**Current:** Budget check only on Purchase Requests.
**Enhancement:** Extend budget checking to:
- Overtime approval (OT cost estimated against department budget)
- Maintenance Work Orders (parts + labor cost vs. maintenance budget)
- Travel/expense claims (if added later)
- Real-time budget burn rate on Manager Dashboard

### 3.3 Employee Onboarding Checklist
**Current:** Employee created as `draft`, but no structured checklist for required documents/steps.
**Enhancement:**
- Configurable onboarding checklist per position type
- Required items: government IDs, medical certificate, NBI clearance, contracts signed
- Progress bar on employee detail page
- Auto-transition from `draft` to `active` when all items checked
- Notification to HR when items are overdue

---

## Priority 4: Reporting & Audit Trail (Thesis Differentiator)

### 4.1 PDF Export for Key Documents
**Current:** Most data is view-only in the browser.
**Enhancement:** Add PDF generation for:
- Payslip (already exists via DTR blade template)
- Purchase Order (send to vendor)
- Delivery Receipt (customer signature copy)
- Statement of Account (already exists)
- Employee 201 File summary
- Leave balance certificate

### 4.2 Activity Timeline on Detail Pages
**Current:** Audit logs exist but are only in the admin section.
**Enhancement:** Add activity timeline sidebar on key detail pages:
- Production Order: created → released → in_progress → completed + who did what
- Leave Request: submitted → head_approved → manager_checked → ga_processed → approved
- Purchase Request: draft → reviewed → budget_verified → approved → converted_to_po
- Loan: pending → noted → checked → reviewed → approved → active → paid

Shows: timestamp, actor name, action, comment (if any)

### 4.3 Export to Excel
**Current:** Limited export capability.
**Enhancement:** Add "Export to CSV/Excel" button on all list pages:
- Employee list, attendance summary, payroll register
- AP/AR aging, trial balance, budget vs actual
- Production order list, inventory stock report
- Configurable columns (user picks which fields to export)

---

## Priority 5: UX Polish (Real-World Feel)

### 5.1 Global Search Enhancement
**Current:** GlobalSearchPage exists but may be limited.
**Enhancement:**
- Search across: employees, vendors, customers, POs, invoices, production orders
- Show entity type icon + status badge in results
- Recent searches remembered
- Keyboard shortcut (Cmd+K / Ctrl+K)

### 5.2 Status Timeline Component
**Current:** Status shown as a single badge.
**Enhancement:** Visual timeline/stepper showing:
- All possible states in the workflow
- Current state highlighted
- Past states with timestamps and actors
- Future states greyed out
- Reusable across all modules with state machines

### 5.3 Form Auto-Save
**Current:** Losing unsaved form data on navigation.
**Enhancement:**
- Auto-save draft forms to localStorage every 30 seconds
- "Restore draft?" prompt when returning to a form
- Apply to: PR creation, JE creation, employee form, production order form

---

## Priority 6: Module-Specific Improvements

### 6.1 Payroll
- **Pay slip comparison:** Show current vs. previous period side-by-side
- **Bulk exclusion tool:** Select multiple employees to exclude from a run (sick leave, suspension)
- **Computation trace:** Click any amount to see exactly which pipeline step produced it

### 6.2 Leave
- **Team leave calendar:** Visual calendar showing who is on leave this month (for managers)
- **Auto-calculate business days:** Exclude weekends and holidays from leave day count automatically
- **Conflict detection:** Warn if approving a leave would leave department below minimum staffing

### 6.3 Procurement
- **Vendor performance tracking:** Track on-time delivery rate, quality pass rate per vendor (from GR + QC data)
- **PO follow-up reminders:** Auto-remind purchasing when PO is past expected delivery date
- **PR to PO conversion preview:** Show what the PO will look like before confirming conversion

### 6.4 Production
- **Gantt chart view:** Visual timeline of production orders with overlapping schedules
- **Material availability check:** Before releasing a production order, verify all BOM components are in stock
- **Yield tracking:** Compare actual output vs. BOM expected output to calculate yield percentage

### 6.5 Inventory
- **ABC classification badge:** Show A/B/C class on item master list (already have the analytics service)
- **Stock movement history:** Timeline view of all ins/outs for a specific item
- **Reorder point suggestions:** Based on average daily consumption and lead time

---

## Implementation Roadmap

### Phase 1 (High Impact, Lower Complexity)
1. Smart notification system (2.1)
2. Activity timeline on detail pages (4.2)
3. PDF export for PO and DR (4.1)
4. Attendance anomaly auto-resolution (3.1)
5. Status timeline component (5.2)

### Phase 2 (Cross-Module Automation)
6. Client Order → Production auto-chain (1.1)
7. GR → AP Invoice auto-draft (1.2)
8. DR → AR Invoice auto-draft (1.3)
9. Low stock auto-reorder (2.3)
10. Approval queue consolidation (2.2)

### Phase 3 (Polish & Differentiation)
11. Export to Excel on all list pages (4.3)
12. Employee onboarding checklist (3.3)
13. Budget enforcement expansion (3.2)
14. Leave team calendar + conflict detection (6.2)
15. Production material availability check (6.4)

---

## What Makes This Thesis-Grade

| Criteria | How Ogami Addresses It |
|----------|----------------------|
| **Multi-module integration** | 20 interconnected domains with 4 major cross-module flows (P2P, O2C, Payroll, Inventory) |
| **Role-based access control** | 9 roles, department-scoped, SoD enforcement on every approval chain |
| **Real-world compliance** | Philippine BIR tax forms, SSS/PhilHealth/PagIBIG tables, TRAIN law brackets |
| **Audit trail** | Every model auditable, stock ledger entries, approval history |
| **Multi-level approval workflows** | 4-step leave approval, 5-step loan approval, 3-step payroll approval, 4-step PR approval |
| **Financial accuracy** | Integer centavos (never float), double-entry GL, 3-way match, budget enforcement |
| **Automation** | 18-step payroll pipeline, auto-GL posting, auto-depreciation, auto-MRQ from BOM |
| **Scalability** | Queue-based computation, Redis caching, Horizon job management |
| **Security** | Session-cookie auth, encrypted gov IDs, SHA-256 hashes, SoD at DB level |
