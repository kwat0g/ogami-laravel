Plan: Ogami ERP — Role Refactor + Procurement Module
The plan executes across 4 sprints (~8 weeks for core work, ~18 weeks total) split into two major tasks: Task 1 replaces the 6-role model with a 7-role hierarchy aligned to the actual organizational chart (supervisor→head, hr_manager→manager, accounting_manager→officer, new vice_president). Task 2 adds a full Procurement module (PR → PO → GR → AP auto-creation) with the same 5-stage SoD approval chain. All changes are non-breaking: existing role assignments migrate automatically via FK (role_id), existing v1 loans continue on the old chain via workflow_version discriminator, and existing AP vendors are extended — not replaced.

TASK 1 — Role Refactor
Sprint 1, Phase A — Database + Backend (Days 1–4)
DB snapshot before any migration: pg_dump ogami_erp to a timestamped file

Create migration 2026_03_05_000001_rename_roles_v2.php — renames supervisor→head, hr_manager→manager, accounting_manager→officer directly in the roles table via DB UPDATE; model_has_roles pivots survive untouched because they reference role_id (FK), not the name string

Create migration 2026_03_05_000002_add_vice_president_role.php — insertOrIgnore of a single vice_president row into roles

Create migration 2026_03_05_000002_rename_roles_in_dept_permission_profiles.php — three UPDATE statements on department_permission_profiles.role (varchar, not enum — no ALTER TYPE needed)

Update RolePermissionSeeder.php:

Replace all 6 Role::findOrCreate() calls with the 7-role set ($admin, $executive, $vicePresident, $manager, $officer, $head, $staff)
Remove the Role::where('name','manager')->delete() guard — manager is now a real role
Rename all $supervisor->syncPermissions, $hrManager->syncPermissions, $accountingManager->syncPermissions variable references
Add $vicePresident->syncPermissions(...) block with the permission list from 1B
Add 4 new permission slugs to PERMISSIONS constant: loans.head_note, loans.manager_check, loans.officer_review, loans.vp_approve
Update DepartmentPermissionProfileSeeder.php — replace all 'supervisor', 'hr_manager', 'accounting_manager' string literals; update the cache-flush foreach array (line ~547) to ['manager', 'officer', 'head', 'vice_president']

Update SampleDataSeeder.php line 302 — syncRoles(['supervisor']) → syncRoles(['head'])

Update DepartmentPermissionService.php line 195 — roles array: ['manager', 'hr_manager', 'accounting_manager', 'supervisor'] → ['manager', 'officer', 'head', 'vice_president']; same change in DepartmentPermissionServiceV3.php line 197

Update EmployeePolicy.php line 74 — hasAnyRole(['hr_manager','accounting_manager','supervisor']) → hasAnyRole(['manager','officer','head'])

Update LoanPolicy.php — update supervisorReview permission slug to loans.head_note; update status check from supervisor_approved to head_noted

Add migration for overtime_requests.requester_role CHECK constraint: drop + re-add with ('staff','head','manager','officer','vice_president')

Update OvertimeRequestService.php — replace old ternary for requester_role with a match(true) expression covering all 5 new role names

Audit with grep -rl "'supervisor'\|\"supervisor\"" app/ routes/ — fix all remaining references before proceeding

Sprint 1, Phase B — Loan Workflow v2 (Days 5–7)
Create migration 2026_03_05_000003_add_5stage_approval_to_loans.php:

Drop + re-add loans_status_check with the full status set (v1 + v2)
Add workflow_version smallint default 1 to loans
Add 12 new nullable columns: head_noted_by/at/remarks, manager_checked_by/at/remarks, officer_reviewed_by/at/remarks, vp_approved_by/at/remarks
Add 4 SoD DB CHECK constraints (chk_sod_loan_head through chk_sod_loan_vp)
Update LoanRequestService.php:

Set workflow_version = 2 in store() for all new loans
Add 4 new service methods: headNote(), managerCheck(), officerReview(), vpApprove()
Add private helpers assertWorkflowVersion() and assertStatus()
Add 4 new gate methods to LoanPolicy.php: headNote, managerCheck, officerReview, vpApprove — each checks permission slug + workflow_version + status + SoD actor ID

Add 4 new controller methods to LoanController.php: headNote, managerCheck, officerReview, vpApprove

Add 4 new PATCH routes to loans.php: {loan}/head-note, {loan}/manager-check, {loan}/officer-review, {loan}/vp-approve

Update LoanResource.php — expose new v2 actor/timestamp fields

Update Loan.php — add 4 new belongsTo relations: headNotedBy, managerCheckedBy, officerReviewedBy, vpApprovedBy

Sprint 1, Phase C — Frontend (Days 7–9)
Update api.ts — add AppRole union type (admin | executive | vice_president | manager | officer | head | staff); change AuthUser.roles from string[] to AppRole[]

Update hr.ts line 169 — requester_role union: replace 'supervisor' with 'head', add 'officer' and 'vice_president'

Update authStore.ts:

Rename isSupervisor() → isHead(), check 'head'
Update isManager() array → ['manager', 'officer', 'vice_president']
Add isOfficer() and isVicePresident() methods
Update hasDepartmentAccess() bypass → ['admin', 'executive', 'vice_president']
Update AuthState interface to reflect all renamed/added methods
Update usePermission.ts — rename useIsSupervisor export → useIsHead; add useIsOfficer, useIsVicePresident exports

Update UsersPage.tsx lines 24–31 — replace roleBadgeClass Record entries for the 3 renamed roles; add vice_president: 'bg-amber-100 text-amber-700'

Update AppLayout.tsx — add VP Pending Approvals nav section gated by loans.vp_approve permission

Update useDashboard.ts — rename SupervisorDashboardStats → HeadDashboardStats, useSupervisorDashboardStats → useHeadDashboardStats, URL /dashboard/supervisor → /dashboard/head

Update useLeave.ts line 175 — URL supervisor-approve → head-approve

Update useOvertime.ts line 120 — URL supervisor-endorse → head-endorse

Update auth.setup.ts — rename supervisorUser → headUser, add fixtures for officer and vice_president roles

Update useSodCheck.test.ts lines 19, 39, 46, 60, 67 — roles: ['supervisor'] → roles: ['head']

Sprint 1, Phase D — Backend Route Aliases (Day 9)
Add route aliases to attendance.php and leave.php: head-endorse / head-approve alongside old URLs (keep old aliases for 1 release cycle, then remove)

Add GET /api/v1/dashboard/head to dashboard.php, pointing to the same controller action as the old supervisor endpoint

TASK 2 — Procurement Module
Sprint 2, Phase A — Vendor Extension (Days 1–2)
Create migration 2026_03_05_000004_add_procurement_fields_to_vendors.php — add accreditation_status, accreditation_notes, bank_name, bank_account_no, bank_account_name, payment_terms to the existing vendors table (Vendor.php); do NOT create a new vendors table

Add accredit and suspend PATCH routes on the existing /api/v1/ap/vendors resource

Update the existing AP Vendors frontend page — add accreditation status column and bank fields to the vendor form; do not create a duplicate page

Sprint 2, Phase B — Purchase Request (Days 3–9)
Create app/Domains/Procurement/ domain folder following the same folder layout as AP

Create PurchaseMigration — purchase_requests, purchase_request_items tables; purchase_request_seq sequence; trg_pr_total trigger that updates total_estimated_cost; 4 SoD DB CHECK constraints (SOD-011 through SOD-014); status CHECK with all 8 states

Create PurchaseRequest, PurchaseRequestItem Eloquent models — HasPublicUlid trait (consistent with employee routing pattern), belongsTo relations, HasMany items

Create PurchaseRequestService implementing ServiceContract — methods: store(), submit(), note(), check(), review(), vpApprove(), reject(), cancel(); each asserts correct status + SoD actor checks; store() auto-generates pr_reference from sequence

Create PurchaseRequestPolicy — gate methods matching each service action; permission slugs: procurement.purchase-request.view, create, note, check, review; VP reuses approvals.vp.approve

Create PurchaseRequestController and PurchaseRequestItemController under app/Http/Controllers/Procurement/

Create StorePurchaseRequestRequest, UpdatePurchaseRequestRequest Form Requests with Zod-equivalent validation (min 20 chars justification, at least 1 item, qty > 0, cost > 0)

Create PurchaseRequestResource, PurchaseRequestItemResource API Resources

Add procurement route file routes/api/v1/procurement.php and include it in api.php — 11 endpoints as listed in the plan (GET list, POST create, GET detail, PATCH edit, POST submit/note/check/review/vp-approve/reject/cancel)

Register all new procurement permission slugs in RolePermissionSeeder.php and assign: staff gets create/submit, head gets note, manager gets check, officer gets review, vp gets vp_approve

Frontend — Create 3 new pages under frontend/src/pages/procurement/:

PurchaseRequestListPage — TanStack Table with status/department/urgency filters
CreatePurchaseRequestPage — React Hook Form with dynamic line items array (React Hook Form useFieldArray), Zod schema, total auto-sum
PurchaseRequestDetailPage — read-only items table, approval timeline component, SodActionButton at each stage
Sprint 3 — Purchase Order + Goods Receipt (Days 1–10)
Create purchase_orders, purchase_order_items tables — trg_po_total trigger; quantity_pending as GENERATED ALWAYS AS (quantity_ordered - quantity_received) STORED; status CHECK

Create PurchaseOrder, PurchaseOrderItem models, PurchaseOrderService, PurchaseOrderPolicy, PurchaseOrderController, Form Requests, Resources — same domain structure as PR; service's store() validates that the source PR is in approved status

Create goods_receipts, goods_receipt_items tables; goods_receipt_seq sequence

Create GoodsReceipt, GoodsReceiptItem models, GoodsReceiptService, GoodsReceiptPolicy, GoodsReceiptController — confirm() method calls three-way match logic, updates PO item quantity_received, transitions PO status

Create ThreeWayMatchService under app/Domains/Procurement/Services/ — validates PR approved + PO sent + quantities reconcile, marks three_way_match_passed = true, triggers AP invoice auto-creation

Frontend — PurchaseOrderListPage, CreatePurchaseOrderPage (selects from approved PRs, select vendor), GoodsReceiptCreatePage (linked to PO, dynamic items per PO line)

Sprint 4 — AP Auto-Creation + VP Dashboard (Days 1–10)
Add createFromPo(PurchaseOrder $po, GoodsReceipt $gr): ApInvoice method to existing app/Domains/AP/Services/ApInvoiceService.php — auto-creates draft AP invoice; sets source = 'auto_procurement'; existing SOD-009 still applies when Officer routes it for approval

Create frontend/src/pages/approvals/VpApprovalsDashboardPage.tsx — tabbed view aggregating items awaiting VP sign-off (Purchase Requests in reviewed status + Loans in officer_reviewed status); gated by approvals.vp.view

Add procurement notification events — fire Laravel Notification on each state transition (in-app + email); follow existing notification pattern in Notifications

Add Playwright E2E test: Staff creates PR → Head notes → Manager checks → Officer reviews → VP approves → Purchasing Officer creates PO → Warehouse Head confirms GR → AP invoice auto-created

Verification
After Sprint 1:


php artisan migratephp artisan db:seed --class=RolePermissionSeederphp artisan db:seed --class=DepartmentPermissionProfileSeeder./vendor/bin/pest --testsuite=Feature,Unitcd frontend && pnpm typecheck && pnpm testcd frontend && pnpm e2e
All 7 roles exist in DB. Spot-check 5 former-supervisor users now carry head. v1 loans still approve via PATCH /loans/{ulid}/approve.

After Sprint 2–4:


./vendor/bin/pest --testsuite=Integrationcd frontend && pnpm e2e
Full PR→PO→GR→AP invoice E2E passes. SOD-011–014 blocked at all 4 stages. VP dashboard shows pending items.

Static analysis after all changes:


./vendor/bin/phpstan analyse./vendor/bin/pintcd frontend && pnpm typecheck && pnpm e2e
Decisions
workflow_version discriminator: v1 loans keep their old 3-stage chain (pending→supervisor_approved→approved); only loans created after migration use the 5-stage v2 chain. This avoids mid-flight data corruption.
No duplicate vendors table: Procurement reuses Vendor.php — additive migration only
bank_account_no (not _number): matches existing codebase convention in the AP domain
SoD matrix unchanged: The sod_conflict_matrix in system_settings uses permission slugs, not role names — no update needed
Implied modules (Inventory, Production, QC, Mold, Maintenance, ISO/IATF, Delivery): Not implemented in this plan; scoped as Sprints 5–18 and require user interviews with domain owners before schema design begins