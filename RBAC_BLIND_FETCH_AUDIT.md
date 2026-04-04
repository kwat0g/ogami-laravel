# Ogami ERP — RBAC & Blind Fetching Audit
**Date:** 2026-04-04
**Auditor:** Claude Code (automated)
**Scope:** Full RBAC permission matrix vs policy checks + frontend blind-fetching patterns

---

## Executive Summary

Two classes of issues were found across the codebase:

1. **Backend RBAC Mismatches** — Permissions granted in `ModulePermissionSeeder` do not align with what backend policies actually check. Because `ModulePermissionSeeder` runs after `RolePermissionSeeder` and calls `syncPermissions()` (overwriting prior grants), several workflow steps are permanently broken in production after a full seed.

2. **Frontend Blind Fetching** — 43+ hooks fire API requests unconditionally on mount with no `enabled: hasPermission(...)` guard. Several multi-domain dashboard pages fire 5–14 cross-domain queries with zero permission checks, producing console floods of 403 errors for unauthorized roles.

**Severity counts:**

| Severity | Backend | Frontend |
|----------|---------|----------|
| Critical | 3 | 4 |
| High | 4 | 10+ |
| Medium | 3 | 30+ |
| Low | 1 | — |

---

## Part 1 — Backend RBAC

### Architecture Note

Two seeders write to the Spatie `role_has_permissions` table:

- **`RolePermissionSeeder`** — runs first; sets the initial permission matrix.
- **`ModulePermissionSeeder`** — runs second; merges per-module permission blocks then calls `syncPermissions()` per role, **overwriting** whatever `RolePermissionSeeder` set.

Any permission in `RolePermissionSeeder` that is absent from `ModulePermissionSeeder`'s merged output is silently dropped in production after a full seed.

---

### Critical Issues

#### C-1 · VP Cannot Approve Purchase Requests

**Severity:** Critical — broken workflow step
**File:** `database/seeders/ModulePermissionSeeder.php` (executive › vice_president block, ~line 727)

`PurchaseRequestPolicy::vpApprove()` checks the permission `approvals.vp.approve`.
This permission exists in `RolePermissionSeeder` for the `vice_president` role but is **absent** from the VP block in `ModulePermissionSeeder`. After the sync, the `vice_president` Spatie role loses this permission.

The VP block has `procurement.purchase-request.view` (correct for viewing) but not `approvals.vp.approve` (required to actually approve). Every VP approval attempt on a `budget_verified` PR returns 403.

**Fix:** Add `'approvals.vp.approve'` to the `vice_president` block in `ModulePermissionSeeder`.

```php
// executive › vice_president block — add:
'approvals.vp.approve',
```

---

#### C-2 · Leave Workflow Steps 3 & 4 Permanently Broken

**Severity:** Critical — two workflow steps unreachable
**File:** `database/seeders/ModulePermissionSeeder.php`

The leave approval chain is: `submitted → head_approved → manager_checked → ga_processed → vp_noted`.

| Step | Permission Checked (Policy) | In ModulePermissionSeeder? |
|------|-----------------------------|---------------------------|
| Step 2 | `leaves.head_approve` | ✅ `hr.head` line ~130 |
| Step 3 | `leaves.manager_check` | ❌ **Absent from all blocks** |
| Step 4 | `leaves.ga_process` | ❌ **Absent from all blocks** |
| Step 5 | `leaves.vp_note` | ✅ `executive.vice_president` line ~735 |

Both permissions exist in `RolePermissionSeeder` but are absent from `ModulePermissionSeeder`. After a full seed, every leave request gets permanently stuck at `manager_checked`. The GA processing step has never been executable in production after `ModulePermissionSeeder` was introduced.

**Fix:**
```php
// hr › manager block — add:
'leaves.manager_check',
'leaves.ga_process',
```

---

#### C-3 · SoD Violation: `loans.vp_approve` Granted to `hr.manager`

**Severity:** Critical — SoD-014 violated
**File:** `database/seeders/ModulePermissionSeeder.php` line ~69

`RolePermissionSeeder` contains an explicit comment: *"loans.vp_approve is EXCLUSIVE to vice_president role (SoD-014)"*. Despite this, the `hr › manager` block in `ModulePermissionSeeder` grants `loans.vp_approve` at line 69.

After the merge, the `manager` Spatie role holds VP-level loan approval authority, allowing HR managers to self-approve the final stage of loan applications they reviewed earlier in the chain.

**Fix:** Remove `'loans.vp_approve'` from the `hr › manager` block. It belongs only in `executive › vice_president`.

```php
// hr › manager block — REMOVE:
'loans.vp_approve',
```

---

### High Issues

#### H-1 · Duplicate Permission Entries in `hr.head` Block

**Severity:** High — seeder correctness / maintenance hazard
**File:** `database/seeders/ModulePermissionSeeder.php` lines ~130–154

The following permissions are declared twice in the `hr › head` block (copy-paste error):

- `overtime.approve`, `overtime.reject`
- `employees.upload_documents`, `employees.download_documents`
- `attendance.import_csv`, `attendance.view_anomalies`, `attendance.resolve_anomalies`, `attendance.manage_shifts`
- `loans.head_note`, `loans.view_department`

Functionally harmless today (PHP deduplicates during the Spatie sync), but masks intent and makes future edits error-prone.

**Fix:** Remove the duplicate declarations from lines ~146–154.

---

#### H-2 · `loans.disburse` is a Dead Permission

**Severity:** High — granted but never enforced
**File:** `database/seeders/ModulePermissionSeeder.php`

`loans.disburse` is granted to `accounting.officer` and `hr.officer`. However, `LoanPolicy::disburse()` checks `loans.accounting_approve OR loans.hr_approve` — it never checks `loans.disburse`. The permission string is therefore:
- **Granted** to two roles
- **Never evaluated** by any policy

This creates false confidence that disbursement is permission-controlled when the actual gate is a different string.

**Fix:** Either remove `loans.disburse` from the seeder (if it's truly unused), or update `LoanPolicy::disburse()` to check it consistently.

---

#### H-3 · `purchasing.staff` Permissions Silently Discarded

**Severity:** High — intended capability silently missing
**File:** `database/seeders/ModulePermissionSeeder.php`

The `purchasing › staff` block explicitly grants:
- `procurement.purchase-request.create`
- `vendors.view`

The comment implies purchasing staff should be able to raise PRs. However, the `syncPermissionsToRoles()` method applies the `STAFF_SELF_SERVICE_PERMISSIONS` hard override after merging all staff blocks, which resets staff to the fixed self-service list. `procurement.purchase-request.create` and `vendors.view` are not in that constant, so they are silently discarded.

**Fix:** Add the intended purchasing-staff permissions to `STAFF_SELF_SERVICE_PERMISSIONS`, or add a `purchasing_staff` sub-role that bypasses the staff override.

---

#### H-4 · `inventory.mrq.check` + `.review` Restricted to `production.manager` Only

**Severity:** High — cross-department MRQ workflow broken
**File:** `database/seeders/ModulePermissionSeeder.php`

The MRQ workflow chain: `create → note → check → review → vp_approve → fulfill`.

| Permission | Granted to |
|-----------|-----------|
| `inventory.mrq.check` | `production.manager` only |
| `inventory.mrq.review` | `production.manager` only |
| `inventory.mrq.fulfill` | `production.manager`, `warehouse.manager`, `warehouse.officer`, `warehouse.head` |

Warehouse managers can fulfill MRQs but cannot check or review them. For inter-department requisitions (e.g., a Warehouse MRQ for PROD materials), the check/review steps are blocked.

**Fix:** Add `inventory.mrq.check` and `inventory.mrq.review` to the `warehouse › manager` block.

---

### Medium Issues

#### M-1 · `payroll.initiate` Duplication Bug in `PayrollRunPolicy::create()`

**Severity:** Medium — dead code
**File:** `app/Domains/Payroll/Policies/PayrollRunPolicy.php`

`create()` contains: `$user->hasAnyPermission(['payroll.initiate', 'payroll.initiate'])` — the same string listed twice. Functionally harmless but indicates a maintenance gap.

---

#### M-2 · `hr.full_access` Sentinel Given to Non-HR Heads

**Severity:** Medium — overly broad sidebar access
**File:** `database/seeders/ModulePermissionSeeder.php`

`hr.full_access` is a sidebar-access sentinel used by the frontend to show the full HR module nav. It is granted to `accounting.head` entries (via the merge), giving accounting heads full HR sidebar visibility despite the intent being HR-department-only.

---

#### M-3 · `manager` Spatie Role is a Merged Super-Role

**Severity:** Medium — architectural concern
**File:** `database/seeders/ModulePermissionSeeder.php`

`syncPermissionsToRoles()` merges permissions from every module's `manager` block into a single `manager` Spatie role. A user assigned the generic `manager` role therefore has the union of: HR, Accounting, Production, Sales, Warehouse, Purchasing, Operations manager permissions. Department middleware is the only real access control separating them. A misconfigured department assignment grants cross-domain authority instantly.

---

## Part 2 — Frontend Blind Fetching

### Architecture Note

**Reference implementation:** `VpApprovalsDashboardPage.tsx` lines 156–253 shows the correct pattern:

```ts
// 1. Derive permission booleans
const canLoanApprove = hasPermission('loans.vp_approve')

// 2. Pass as enabled flag to each hook
const loanQuery = useVpLoans({ ... }, canLoanApprove)

// 3. Hooks default to false — safe by default
export function useVpLoans(filters = {}, enabled = true) {  // ← should be false for multi-domain hooks
  return useQuery({ ..., enabled })
}
```

`useVpPendingCounts` was the last hook in this file with `enabled.pr ?? true` — now fixed to `?? false`.

---

### Critical — Multi-Domain Aggregation Hooks (No `enabled` at All)

#### FC-1 · `useEnhancements.ts` — 14 Ungated Cross-Domain Hooks

**File:** `frontend/src/hooks/useEnhancements.ts`
**Impact:** Worst single file — 14 hooks spanning 7+ domains, none have an `enabled` parameter.

| Hook | Endpoint | Domain |
|------|----------|--------|
| `usePerformanceAppraisals` | `GET /hr/appraisals` | HR |
| `useDepartmentPerformanceSummary` | `GET /hr/appraisals/department-summary` | HR |
| `useBudgetAmendments` | `GET /budget/amendments` | Budget |
| `useLeadScores` | `GET /crm/leads/scores` | CRM |
| `useFinancialRatios` | `GET /accounting/financial-ratios` | Accounting |
| `useCapacityUtilization` | `GET /production/capacity` | Production |
| `useTimePhasedMrp` | `GET /production/mrp/time-phased` | Production |
| `usePaymentOptimization` | `GET /ap/payment-optimization` | AP |
| `useDiscountSummary` | `GET /ap/discount-summary` | AP |
| `useQuarantine` | `GET /qc/quarantine` | QC |
| `usePendingAcknowledgments` | `GET /iso/pending-acknowledgments` | ISO |
| `useValuationByMethod` | `GET /inventory/valuation-by-method` | Inventory |
| `useBlanketPOs` | `GET /procurement/blanket-pos` | Procurement |
| `useAlphalist2316/2307` | `GET /tax/alphalist/*` | Tax |

---

#### FC-2 · `useAnalytics.ts` — Executive Analytics with Zero Gating

**File:** `frontend/src/hooks/useAnalytics.ts`

| Hook | Endpoint | Issue |
|------|----------|-------|
| `useExecutiveDashboard` line 161 | `GET /dashboard/executive-analytics` | 7-domain aggregate, no `enabled` |
| `useArAging` | `GET /ar/aging` | AR-only, no `enabled` |
| `useBudgetVariance` | `GET /budget/variance` | Budget-only, no `enabled` |
| `useInventoryAbc` | `GET /inventory/abc` | Inventory-only, no `enabled` |
| `useVendorScores` | `GET /ap/vendor-scores` | AP-only, no `enabled` |

`ExecutiveAnalyticsDashboard.tsx` calls `useExecutiveDashboard()` with no permission check. A VP (not executive role) visiting this page triggers the 7-domain aggregation endpoint.

---

#### FC-3 · `useSupplementaryKpis.ts` — Cash + AP + Inventory + Payroll

**File:** `frontend/src/hooks/useSupplementaryKpis.ts` line 11

`useSupplementaryKpis` hits `GET /dashboard/kpis/supplementary` which aggregates cash position, AP aging, inventory health, and payroll trend in one request. No `enabled` parameter. Any non-executive role that mounts a component using this fires a 4-domain cross-authorization request.

---

#### FC-4 · `useDashboard.ts` — All 8 Role-Dashboard Hooks Ungated

**File:** `frontend/src/hooks/useDashboard.ts`

| Hook | Line | Endpoint |
|------|------|----------|
| `useHrDashboardStats` | 278 | `GET /dashboard/hr` |
| `useAccountingDashboardStats` | 289 | `GET /dashboard/accounting` |
| `useAdminDashboardStats` | 300 | `GET /dashboard/admin` |
| `useStaffDashboardStats` | 313 | `GET /dashboard/staff` |
| `useExecutiveDashboardStats` | 324 | `GET /dashboard/executive` |
| `useHeadDashboardStats` | 335 | `GET /dashboard/head` |
| `useVicePresidentDashboardStats` | 377 | `GET /dashboard/vp` |
| `useOfficerDashboardStats` | 415 | `GET /dashboard/officer` |

All 8 rely solely on the router directing users to the correct dashboard component. A misrouted user or a future route refactor produces 403s.

---

### High — Single-Domain List Hooks Without `enabled`

| Hook | File | Line | Endpoint | Required Gate |
|------|------|------|----------|---------------|
| `useLeaveRequests` | `useLeave.ts` | 17 | `GET /leave/requests` | `leaves.view \|\| leaves.view_team` |
| `useLeaveBalances` | `useLeave.ts` | 108 | `GET /leave/balances` | `leaves.view` |
| `useLeaveCalendar` | `useLeave.ts` | 402 | `GET /leave/calendar` | `leaves.view` |
| `useLeaveTypes` | `useLeave.ts` | 364 | `GET /hr/leave-types` | `leaves.view` |
| `useLoans` | `useLoans.ts` | 14 | `GET /loans` | `loans.view` |
| `useTeamLoans` | `useLoans.ts` | 28 | `GET /loans/team` | `loans.view_team` |
| `useOvertimeRequests` | `useOvertime.ts` | 7 | `GET /attendance/overtime-requests` | `overtime.view` |
| `useTeamOvertimeRequests` | `useOvertime.ts` | 24 | `GET /attendance/overtime-requests/team` | `overtime.view_team` |
| `useAttendanceLogs` | `useAttendance.ts` | 15 | `GET /attendance/logs` | `attendance.view` |
| `useTeamAttendanceLogs` | `useAttendance.ts` | 29 | `GET /attendance/logs/team` | `attendance.view_team` |
| `useAttendanceDashboard` | `useAttendance.ts` | 336 | `GET /attendance/dashboard` | `attendance.view` |
| `useAttendanceSummary` | `useAttendance.ts` | 366 | `GET /attendance/summary` | `attendance.view` |
| `useCorrectionRequests` | `useAttendance.ts` | 469 | `GET /attendance/correction-requests` | `attendance.view` |
| `useWorkLocations` | `useAttendance.ts` | 547 | `GET /attendance/work-locations` | `attendance.view` |
| `useGeofenceSettings` | `useAttendance.ts` | 523 | `GET /attendance/geofence-settings` | `system.edit_settings` |
| `usePayrollRuns` | `usePayroll.ts` | 70 | `GET /payroll/runs` | `payroll.view_runs` |
| `usePurchaseRequests` | `usePurchaseRequests.ts` | 19 | `GET /procurement/purchase-requests` | `procurement.purchase-request.view` |
| `useEmployees` | `useHr.ts`, `useEmployees.ts` | 57 / 27 | `GET /hr/employees` | `employees.view` |
| `useTeamEmployees` | `useHr.ts`, `useEmployees.ts` | 68 / 40 | `GET /hr/employees/team` | `employees.view_team` |
| `useRecruitmentDashboard` | `useRecruitment.ts` | 34 | `GET /recruitment/dashboard` | `recruitment.requisitions.view` |
| `useRequisitions` | `useRecruitment.ts` | 46 | `GET /recruitment/requisitions` | `recruitment.requisitions.view` |
| `useDashboardStats` | `useAdmin.ts` | 142 | `GET /admin/dashboard/stats` | `system.manage_users` |
| `useAdminUsers` | `useAdmin.ts` | 158 | `GET /admin/users` | `system.manage_users` |
| `useSystemSettings` | `useSettings.ts` | 29 | `GET /admin/settings` | `system.edit_settings` |
| `useHeadcountReport` | `useHRReports.ts` | — | `GET /hr/reports/headcount` | `reports.view` |
| `useTurnoverReport` | `useHRReports.ts` | — | `GET /hr/reports/turnover` | `reports.view` |
| `useBirthdayReport` | `useHRReports.ts` | — | `GET /hr/reports/birthdays` | `reports.view` |

---

### Multi-Domain Dashboard Pages With No Permission Gates at Call Site

| Page | File | Domains Fetched | Issue |
|------|------|----------------|-------|
| `PurchasingOfficerDashboard` | `pages/dashboard/PurchasingOfficerDashboard.tsx` | Procurement × 3 | 7 queries across Procurement/AP/Inventory, no per-query `enabled` |
| `ProductionManagerDashboard` | `pages/dashboard/ProductionManagerDashboard.tsx` | Production + QC | 7 queries, no gates |
| `PlantManagerDashboard` | `pages/dashboard/PlantManagerDashboard.tsx` | Production + QC + Inventory + Mold | 5 queries, no gates |
| `ExecutiveAnalyticsDashboard` | `pages/dashboard/ExecutiveAnalyticsDashboard.tsx` | 7 domains | Calls `useExecutiveDashboard()` with no permission check |
| `GaOfficerDashboard` | `pages/dashboard/GaOfficerDashboard.tsx` | HR | Calls `useHeadDashboardStats()` — wrong hook; GA officers are not department heads, will 403 |
| `LeaveListPage` | `pages/hr/leave/LeaveListPage.tsx` | HR Leave | `useLeaveRequests` called with no `enabled` |
| `LoanListPage` | `pages/hr/loans/LoanListPage.tsx` | HR Loans | `useLoans` called with no `enabled` |
| `OvertimeListPage` | `pages/hr/attendance/OvertimeListPage.tsx` | HR Attendance | `useOvertimeRequests` + `useDepartments` both ungated |
| `ImpexOfficerDashboard` | `pages/dashboard/ImpexOfficerDashboard.tsx` | Delivery | `useDeliveryReceipts` twice, no `enabled` |

---

## Part 3 — Remediation Roadmap

### Backend — Ordered by Impact

| # | File | Change | Severity |
|---|------|--------|----------|
| 1 | `ModulePermissionSeeder.php` | Add `'approvals.vp.approve'` to `executive › vice_president` block | Critical |
| 2 | `ModulePermissionSeeder.php` | Add `'leaves.manager_check'` and `'leaves.ga_process'` to `hr › manager` block | Critical |
| 3 | `ModulePermissionSeeder.php` | Remove `'loans.vp_approve'` from `hr › manager` block | Critical |
| 4 | `ModulePermissionSeeder.php` | Remove duplicate entries from `hr › head` block (lines ~146–154) | High |
| 5 | `ModulePermissionSeeder.php` | Decide on `loans.disburse`: remove it or update `LoanPolicy` to check it | High |
| 6 | `ModulePermissionSeeder.php` | Fix `purchasing.staff` — move intended permissions out of the staff-overridden block | High |
| 7 | `ModulePermissionSeeder.php` | Add `inventory.mrq.check` + `inventory.mrq.review` to `warehouse › manager` | High |
| 8 | `PayrollRunPolicy.php` | Fix duplicate `'payroll.initiate'` in `hasAnyPermission()` array | Medium |

### Frontend — Ordered by Impact

| # | Change | Severity |
|---|--------|----------|
| 1 | Add `enabled` param to all 14 hooks in `useEnhancements.ts`; gate each in callers | Critical |
| 2 | Add `enabled` param to all 8 hooks in `useDashboard.ts`; gate in Dashboard router component | Critical |
| 3 | Gate `useExecutiveDashboard` and analytics hooks in `ExecutiveAnalyticsDashboard` | Critical |
| 4 | Gate `useSupplementaryKpis` with `hasPermission('reports.financial_statements')` | Critical |
| 5 | Add `enabled` to `useLeaveRequests`, `useLoans`, `useOvertimeRequests` and update their list pages | High |
| 6 | Add `enabled` to all 6 attendance hooks in `useAttendance.ts` | High |
| 7 | Add `enabled` to `usePayrollRuns`, `usePurchaseRequests`, `useEmployees` | High |
| 8 | Gate all queries in `PurchasingOfficerDashboard`, `ProductionManagerDashboard`, `PlantManagerDashboard` | High |
| 9 | Fix `GaOfficerDashboard` — replace `useHeadDashboardStats` with a GA-scoped query | High |
| 10 | Add `enabled` to all 5 admin/settings hooks (`useAdmin.ts`, `useSettings.ts`) | Medium |

---

## Appendix — Reference: The Correct Pattern

```ts
// hooks/useVpApprovals.ts — correct implementation (post-fix)

export function useVpPendingCounts(
  enabled: { pr?: boolean; loan?: boolean; mrq?: boolean; payroll?: boolean } = {}
) {
  const loan = useQuery({
    queryKey: [...],
    queryFn: ...,
    enabled: enabled.loan ?? false,   // ← false default: fail-closed
  })
  // ...
}

// pages/approvals/VpApprovalsDashboardPage.tsx — correct call site
const canLoanApprove = hasPermission('loans.vp_approve')
const loanQuery = useVpLoans({ ... }, canLoanApprove)
const pendingCounts = useVpPendingCounts({
  pr:      canPrApprove,
  loan:    canLoanApprove,
  mrq:     canMrqApprove,
  payroll: canPayrollApprove,
})
```

**Rule:** Any hook that calls an endpoint that is not universally accessible to all authenticated users must accept an `enabled` parameter and default it to `false`. The caller derives the boolean from `hasPermission('...')` and passes it explicitly.

---

*Generated by automated audit — verify each finding against current code before applying fixes.*
