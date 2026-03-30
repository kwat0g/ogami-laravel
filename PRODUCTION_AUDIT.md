# Ogami ERP — Production-Grade Audit Report
**Date:** March 31, 2026
**Audited by:** 3 parallel agents covering backend workflows, frontend, DB/security/tests
**Verdict: NOT PRODUCTION-READY — 11 CRITICAL issues must be fixed before go-live**

---

## CRITICAL ISSUES (11) — Must fix before production

### C1 — Material Requisition has NO state machine
**Domain:** Inventory
**File:** `app/Domains/Inventory/Services/MaterialRequisitionService.php`
**Problem:** MR supports a 7-stage approval workflow (draft → submitted → noted → checked → reviewed → approved → fulfilled) but there is **no StateMachine** enforcing valid transitions. Status is checked ad-hoc with `assertStatus()` in individual methods. Any stage can be skipped. Approval can be bypassed entirely.
**Risk:** Inventory can be issued without proper approval chain.
**Fix:** Create `app/Domains/Inventory/StateMachines/MaterialRequisitionStateMachine.php` with a TRANSITIONS map and use it throughout the service.

---

### C2 — Goods Receipt has NO state machine
**Domain:** Procurement
**File:** `app/Domains/Procurement/Services/GoodsReceiptService.php` (~lines 170–315)
**Problem:** GR has 8 statuses but no StateMachine file. Six+ service methods each do their own `if ($gr->status !== 'x')` guards. Nothing prevents calling methods out of order. The AP invoice auto-creation listener fires on `ThreeWayMatchPassed` event, but GR status validity is never proven by a machine — it's just assumed.
**Risk:** GR can reach AP invoice creation in a corrupt state. QC workflow stages are skippable.
**Fix:** Create `app/Domains/Procurement/StateMachines/GoodsReceiptStateMachine.php`.

---

### C3 — Payroll GL posting has no atomic transaction guard
**Domain:** Payroll
**File:** `app/Domains/Payroll/Services/PayrollPostingService.php` (lines 37–281)
**Problem:** GL entries are constructed in-memory then posted. If `FiscalPeriod` lookup fails (line 122) or a GL account is missing (lines 111–115), a `DomainException` is thrown **after partial DB writes may have occurred**. The `PayrollRun.status` can be marked DISBURSED before the GL post is confirmed complete.
**Risk:** Payroll run marked disbursed with no GL entry (money paid, books unaffected), OR GL entries written but run not marked disbursed — causing duplicate posting on retry.
**Fix:** Wrap ALL precondition checks AND GL writes in a single `DB::transaction()`. Validate FiscalPeriod and all 3 GL accounts BEFORE writing anything. Only update `PayrollRun.status` inside the same transaction after GL post succeeds.

---

### C4 — Goods Receipt return leaves inventory desync on retry
**Domain:** Procurement
**File:** `app/Domains/Procurement/Services/GoodsReceiptService.php` (lines 494–571)
**Problem:** The `returnToSupplier()` method decrements `PO.quantity_received` before the stock issuance (reversal) call. If stock service throws, the entire `DB::transaction()` rolls back — but if a prior call already partially succeeded, retrying the operation can double-reverse stock.
**Risk:** Inventory quantity permanently out of sync with physical count.
**Fix:** Test stock availability BEFORE entering the transaction. Make the stock reversal idempotent. Mark GR as 'returned' only AFTER all inventory operations succeed, within one atomic transaction.

---

### C5 — Production rework transition has no NCR guard
**Domain:** Production
**File:** `app/Domains/Production/StateMachines/ProductionOrderStateMachine.php`
**Problem:** The `completed → in_progress` transition (rework) is allowed but there is **no `rework()` method** in `ProductionOrderService`. Documentation states NCR reference is required, but this is unenforced — no method exists to validate it.
**Risk:** Rework orders created without traceability. QC integrity violated. NCR audit trail missing.
**Fix:** Add `ProductionOrderService::rework(ProductionOrder $order, string $ncrUlid)` that validates NCR exists and links it. Add controller request validation requiring `ncr_ulid` on the rework endpoint.

---

### C6 — Admin/super_admin bypasses ALL SoD policies
**Domain:** Cross-cutting
**Files:** `app/Domains/Payroll/Policies/PayrollRunPolicy.php`, `app/Domains/Procurement/Policies/PurchaseRequestPolicy.php`
**Problem:** Both policies have a `before()` method that returns `true` for `admin` and `super_admin` roles unconditionally. This means an admin can approve their own payroll run or their own purchase request — directly violating SOD-005, SOD-006, SOD-007.
**The comment says it's "needed for test infra admin users"** — this is not an acceptable production justification.
**Risk:** Financial fraud, audit failure, internal control bypass.
**Fix:** Remove the admin bypass from production policies. Use a seeded test-only role (e.g., `test_admin`) or a `APP_ENV=testing` flag for test bypass only.

---

### C7 — Procurement uses `decimal(15,2)` for money, not centavos
**Domain:** Procurement
**Files:** `database/migrations/2026_03_05_000007_create_purchase_requests_table.php` (line 75, 109), `*_create_purchase_orders_table.php` (lines 66–67)
**Problem:** `estimated_unit_cost`, `estimated_total`, `total_cost`, `agreed_unit_cost` are all `decimal(15,2)`. Payroll, Inventory, and AP use `unsignedBigInteger` centavos. These systems must reconcile with each other.
**Risk:** Floating-point rounding errors in procurement totals. Systematic discrepancy when procurement values flow into AP or GL. `₱1,234.565` rounds differently in PHP vs PostgreSQL.
**Fix:** Migrate all procurement money columns to `unsignedBigInteger` (centavos). Create a data migration to convert existing rows (`× 100`). Update all service layer arithmetic.

---

### C8 — HR domain has zero test coverage
**Domain:** HR
**Files:** `tests/Feature/HR/` — **does not exist**
**Problem:** HR is the foundational domain — every other domain (Payroll, Leave, Attendance, Loan) depends on `Employee` records. There are zero feature tests for: employee creation, state transitions (draft → active), document validation enforcement (EMP-003: activation blocked without complete documents), termination flow, or re-hire scenarios.
**Risk:** A regression in employee state logic silently breaks payroll, leave balances, and attendance calculations. No safety net exists.
**Fix:** Write `tests/Feature/HR/` with minimum coverage: employee CRUD, onboarding state machine (blocked → active on doc completion), SoD (manager cannot approve their own subordinate's status change), termination cascade.

---

### C9 — 401 interceptor has race condition
**Domain:** Frontend
**File:** `frontend/src/lib/api.ts` (lines 104–152)
**Problem:** Multiple in-flight requests can simultaneously trigger `recheckAuth()`. The `authRecheckEpoch` guard doesn't fully prevent a request that started at epoch A from proceeding after epoch flips to B. Two concurrent requests can both pass the recheck, one then hits another 401, creating an infinite loop or auth loss with no recovery UI.
**Risk:** Users silently lose their session mid-operation. Data submitted during the window is lost. Admin-level action (hard reload) required to recover.
**Fix:** Use a proper singleton promise for recheck. Reject all queued requests immediately if recheck fails. Add a visible "Session expired — please log in" toast with redirect.

---

### C10 — 250+ mutations have zero error handlers
**Domain:** Frontend
**Files:** Nearly all hook files in `frontend/src/hooks/` — only 8 of 405+ mutations have `onError` callbacks
**Evidence:** `useDunning.ts` (lines 24–46): 3 mutations, 0 error handlers. `usePurchaseOrders.ts`: 7 mutations, 1 with error output. `useInventory.ts`: 18 mutations, none.
**Risk:** Users get no feedback when critical operations fail. Leave approvals, PO rejections, dunning notices, payroll mutations — all fail silently. Users assume success and move on.
**Fix:** Global `onError` in QueryClient default options that calls a toast. Then add specific `onError` only where a different message is needed. Enforce via ESLint rule.

---

### C11 — QC override modal is wired but never rendered
**Domain:** Frontend / Production
**File:** `frontend/src/pages/production/ProductionOrderDetailPage.tsx` (lines 70–146)
**Problem:** State variables `showQcOverrideModal` and setter exist. Logic to `setShowQcOverrideModal(true)` exists. But the modal component is **never rendered** in JSX. The feature reference `PROD-002: Force Release (QC Override)` exists in comments but the UI is absent.
**Risk:** Production managers cannot override non-conforming orders. Workflow stalls. Users resort to direct API calls, bypassing all validation.
**Fix:** Implement and render the QC override modal component in this page.

---

## HIGH SEVERITY ISSUES (12) — Fix this sprint

### H1 — AR customer invoices have no approval workflow
`app/Domains/AR/StateMachines/CustomerInvoiceStateMachine.php`
Transitions: `draft → approved` with no human approval step. AP vendor invoices have 5-stage approval with SoD. AR has none. Anyone can mark a customer invoice approved. **Revenue recognition and financial control gap.**

### H2 — Listener chain failures are silent (GR → AP invoice)
`app/Listeners/Procurement/CreateApInvoiceOnThreeWayMatch.php`
If this listener throws, the GR is already confirmed, the event is fired-and-forgotten. No retry, no dead-letter queue, no alert. AP invoice is never created. PO transitions to `fully_received` with an orphaned receipt. **Stale GR check job does not exist.**

### H3 — Sales → Production → Delivery → AR invoice chain has no validation
Multiple domains. Listeners `CreateDeliveryReceiptOnProductionComplete` and `CreateCustomerInvoiceOnShipmentDelivered` exist but if either fails silently, the chain breaks with no alert. Sales can complete without invoicing the customer. **Revenue not recognized.**

### H4 — Payroll batch success rate not validated before state transition
`app/Jobs/Payroll/ProcessPayrollBatch.php` (lines 50–88)
If all employees fail computation (e.g., missing attendance data), the run still transitions to `COMPUTED`. Zero computed employees can proceed to HR approval. **Payroll with 0 payslips can be approved and disbursed.**

### H5 — Payroll posting service has no status guard
`app/Domains/Payroll/Services/PayrollPostingService.php` (lines 37–46)
Checks for idempotency (existing JE) but does NOT validate that `PayrollRun.status` is `ACCTG_APPROVED` or `VP_APPROVED`. Service can be called on a DRAFT run, posting incomplete payroll to GL.

### H6 — Three-way match lacks item-level correlation
`app/Domains/Procurement/Services/ThreeWayMatchService.php`
Match checks quantity totals but not that invoice line items correspond to the same `ItemMaster` ULIDs as received. Vendor can invoice for different items as long as quantities match. **Invoice fraud vector.**

### H7 — Production cost posting is not auto-triggered on order completion
`app/Domains/Production/Services/ProductionCostPostingService.php`
Service exists. Listener `AutoPostProductionCostOnComplete.php` may exist but registration is unverified. If not registered, finance must manually trigger cost posting. **COGS not recorded for completed production orders.**

### H8 — VAT ledger not reversed when AP invoice is rejected/deleted
`app/Domains/AP/Services/VendorInvoiceService.php` (lines 286–291)
Input VAT accumulated on approval is never reversed if the invoice is later rejected or deleted. **Overstates deductible input VAT — tax compliance risk.**

### H9 — Budget domain has no enforcement in Procurement
No service or middleware validates that a PR/PO stays within departmental budget before approval. Budget model exists in isolation. **Budget controls are decorative, not enforced.**

### H10 — Fiscal period close does not validate balanced entries
`app/Domains/Accounting/Services/FiscalPeriodService.php`
No check that total debits = total credits for the period before closing. A period with unbalanced journal entries can be closed. **Corrupts balance sheet.**

### H11 — Payroll approval mutations don't invalidate breakdown/detail queries
`frontend/src/hooks/usePayroll.ts` (lines 231–244, 713–741)
`useLockPayrollRun`, `useApprovePayrollRun`, `useVpApprovePayrollRun` all set run-level query data but do NOT invalidate `['payroll-details', runId]` or `['payroll-breakdown', runId]`. **Accountants see stale payslip numbers after approval.**

### H12 — Leave approval doesn't invalidate leave balances or team calendar
`frontend/src/hooks/useLeave.ts` (lines 176–192)
Head approval invalidates leave queries but not employee leave balances or team leave view. Manager approves leave, calendar still shows employee as available. **Scheduling conflicts.**

---

## MEDIUM SEVERITY ISSUES (14) — Fix within 2 sprints

| # | Area | Issue |
|---|------|-------|
| M1 | Payroll | Legacy lowercase + new uppercase states mixed in PayrollRunStateMachine (19 statuses, ~40 transitions). Easy to end up in invalid hybrid state during migration. |
| M2 | Procurement | Listener double-registration risk — `NotifyOnGrQcEvents` manually registered in `AppServiceProvider` while auto-discovery is also active. Could fire twice. |
| M3 | Procurement | GR item auto-resolution uses name-based `LOWER()` match without normalization. Creates duplicate ItemMasters on extra spaces or case variants. |
| M4 | Inventory | Stock listener missing guard: `quantity_accepted` is never validated `≤ quantity_received`. QC data corruption can create inventory from thin air. |
| M5 | HR/Leave | Leave balance accrual has no proration for mid-year hires. New hire on June 15 gets full annual leave allocation. Overpays leave liability. |
| M6 | Delivery | DR state machine only has `draft → confirmed → delivered`. No partial delivery state, no back-order support. Forces multiple DRs for partial shipments. |
| M7 | DB | No index on `purchase_requests.budget_verified` or intermediate status values used in approval queries. Full table scans at scale. |
| M8 | DB | PO table lacks database-level CHECK constraint ensuring `created_by_id ≠ pr.reviewed_by_id`. SoD enforced only in service layer — bypassable. |
| M9 | Tests | Only happy-path tests. Missing: invalid state transition tests, authorization denial tests, financial calculation precision tests, concurrent request race conditions. |
| M10 | Frontend | `any` types throughout `recruitment/ApplicationDetailPage.tsx` (lines 327–336) and `RecruitmentPage.tsx` (15+ casts). Recruitment domain is type-unsafe. |
| M11 | Frontend | `orderAction()` generic wrapper in `useProduction.ts` sends patch with no pre-flight state validation. Backend errors arrive as generic API errors with no UI feedback. |
| M12 | Frontend | Login page casts `unknown` error to `{ message?: string }` instead of the actual `ApiError` type. Mismatched format causes silent field loss. |
| M13 | Frontend | Payroll status polling has no timeout — `refetchInterval: 3000` when status is PROCESSING runs indefinitely. A stalled job produces infinite polling. |
| M14 | Frontend | Leave detail page (`/hr/leave/:id`) missing from router. Supervisors cannot view leave request details before approving. |

---

## COMPLETE CROSS-DOMAIN CHAIN RISK MATRIX

The following ERP chains have at least one broken or unvalidated link:

| Chain | Broken Link | Risk |
|-------|-------------|------|
| PO → GR → AP Invoice → GL | GR→AP listener no error recovery (H2) | Orphaned receipts, missing payables |
| Production Order → QC → Delivery → AR Invoice → GL | Listener failures silent (H3) | Revenue not recognized |
| Payroll Run → GL Posting | No atomic transaction (C3) | Disbursed run with no GL entry |
| MR → PR → PO | MR has no state machine (C1) | Inventory approved without proper chain |
| Employee → Payroll | HR has zero tests (C8) | Regressions undetected |
| Budget → PR/PO | No enforcement (H9) | Budget controls bypassed |
| AP Invoice → VAT Ledger | No reversal on rejection (H8) | Overstated input VAT |
| Production Complete → COGS GL | Auto-posting unverified (H7) | COGS not recorded |

---

## WHAT IS ACTUALLY GOOD

To be fair, the following are solid and above average for a custom ERP:
- PostgreSQL CHECK constraints enforced at DB level across most domains
- SoD enforcement in Payroll and Procurement policies (except admin bypass)
- Parameterized queries throughout — zero SQL injection risk found
- `Money` value object (centavos) used correctly in Payroll, AR, AP, Inventory
- State machines exist and are well-designed for: PayrollRun, PurchaseRequest, VendorInvoice, SalesOrder, ProductionOrder, LeaveRequest, LoanRequest
- TanStack Query architecture is correct; problems are in execution (missing invalidations, missing error handlers)

---

## PRIORITIZED REMEDIATION PLAN

### Week 1 — Stop the bleeding (C1–C11)
1. `C6` Admin SoD bypass — 2h. Highest fraud risk. Remove `before()` bypass.
2. `C3` Payroll GL atomic transaction — 4h. Wrap in single transaction.
3. `C9` 401 race condition — 4h. Singleton recheck promise + session toast.
4. `C10` Global mutation error handler — 4h. Add `onError` default in QueryClient.
5. `C1` MR StateMachine — 8h. Create file with TRANSITIONS.
6. `C2` GR StateMachine — 8h. Create file with TRANSITIONS.
7. `C4` GR return stock desync — 4h. Pre-flight check + idempotent reversal.
8. `C5` Production rework NCR guard — 4h. Add `rework()` method + request validation.
9. `C11` QC override modal — 6h. Render the existing modal state.
10. `C8` HR test suite — 12h. Minimum viable coverage for employee state machine.

### Week 2 — High severity process gaps
11. `H1` AR approval workflow — 8h. Add 2–3 stage approval mirroring AP.
12. `H2/H3` Listener failure recovery — 8h. Add dead-letter queue + stale record jobs.
13. `H4` Payroll batch success rate check — 4h. Block COMPUTED if <90% employees computed.
14. `H5` Payroll posting status guard — 2h. One-line guard at top of method.
15. `H9` Budget enforcement in Procurement — 8h. Add pre-approval budget check.
16. `H10` Fiscal period balance validation — 4h. Check debits = credits before close.
17. `C7` Procurement money columns — 8h. Migration + service layer conversion.

### Week 3 — Medium issues + test coverage expansion
18. `M1` Payroll state machine cleanup — deprecate legacy statuses.
19. `M5` Leave proration for mid-year hires.
20. `M14` Add leave detail page to router.
21. `H8` VAT reversal on AP rejection.
22. `M9` Add invalid-transition and auth-denial tests across all domains.
23. `H6` Three-way match item-level correlation.

---

## FILES TO CREATE/MODIFY (Critical Path Only)

| Action | File |
|--------|------|
| CREATE | `app/Domains/Inventory/StateMachines/MaterialRequisitionStateMachine.php` |
| CREATE | `app/Domains/Procurement/StateMachines/GoodsReceiptStateMachine.php` |
| CREATE | `app/Domains/Production/Services/ProductionOrderService.php::rework()` |
| CREATE | `tests/Feature/HR/EmployeeWorkflowTest.php` |
| CREATE | `database/migrations/xxxx_fix_procurement_money_columns.php` |
| MODIFY | `app/Domains/Payroll/Services/PayrollPostingService.php` — wrap in single transaction |
| MODIFY | `app/Domains/Payroll/Policies/PayrollRunPolicy.php` — remove `before()` admin bypass |
| MODIFY | `app/Domains/Procurement/Policies/PurchaseRequestPolicy.php` — remove `before()` admin bypass |
| MODIFY | `app/Domains/Procurement/Services/GoodsReceiptService.php` — fix return stock desync |
| MODIFY | `app/Domains/Payroll/Services/PayrollPostingService.php` — add status guard |
| MODIFY | `frontend/src/lib/api.ts` — fix 401 race condition |
| MODIFY | `frontend/src/lib/queryClient.ts` (or equivalent) — add global `onError` handler |
| MODIFY | `frontend/src/pages/production/ProductionOrderDetailPage.tsx` — render QC override modal |
