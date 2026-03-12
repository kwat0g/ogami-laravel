# Plan: Ogami ERP — needs.md Feature Roadmap

## What Already Exists

| Feature | Status |
|---|---|
| PR 5-stage approval (Staff→Head→Manager→Officer→VP) | ✅ Complete |
| PO creation (manual, from approved PR) | ✅ Complete |
| GoodsReceipt + ThreeWayMatch | ✅ Complete |
| Vendor CRUD + accreditation | ✅ Complete |
| VendorInvoice approval chain | ✅ Complete |
| AR: Customer, CustomerInvoice, CustomerPayment | ✅ Complete |
| barryvdh/dompdf + maatwebsite/excel installed | ✅ Available |
| Roles: manager, officer, head, staff, vp, purchasing_officer | ✅ Complete |
| Vendor item catalog (`vendor_items`) | ❌ Missing |
| Vendor portal (vendor login + order management) | ❌ Missing |
| PR PDF export | ❌ Missing |
| Accounting budget-check step in PR chain | ❌ Missing |
| Auto-create PO draft on VP approval | ❌ Missing |
| CRM domain (tickets, client portal) | ❌ Missing |
| Role-based dashboards | ❌ Missing |
| Accounting sidebar simplification | ❌ Missing |

## Decisions

- **Vendor auth**: `vendor` role in existing `users` table; add `vendor_id` FK column to `users`
- **Accounting step**: Insert between `reviewed` (Officer) and `approved` (VP) — new statuses `budget_checked` (pass) and `returned` (needs revision)
- **Returned PR flow**: PR → `returned` → creator edits draft → resubmits → restarts full chain
- **Auto PO**: VP approval fires auto-create of PO draft (status `draft`, no vendor); Purchasing Officer assigns vendor → picks items from `vendor_items` catalog → finalizes
- **Accounting simplify**: COA, FiscalPeriods, JE (manual), BankAccounts, BankRecon → System Settings sidebar group; Accounting main role: AP Invoices + PR Budget Approval
- **CRM**: Full domain with `client` role in `users` table, `client_id` FK to AR `customers`

## Phase 1: Vendor Item Catalog (prerequisite for Portal and PO)

1. Migration: `create_vendor_items_table` — ULID PK, `vendor_id` FK, `item_code`, `item_name`, `description`, `unit_of_measure`, `unit_price` (decimal centavos), `is_active`, timestamps, soft deletes
2. Model: `app/Domains/AP/Models/VendorItem.php` — `final class`, `HasPublicUlid`, `SoftDeletes`; `belongsTo(Vendor::class)`; `Vendor` gains `hasMany(VendorItem::class)`
3. Service method: `VendorService::importItems(Vendor $vendor, array $rows)` — wraps in `DB::transaction()`; supports upsert by `item_code`
4. Controller: `VendorItemController` — `index()`, `store()`, `update()`, `destroy()`, `import()` (Excel/CSV via `maatwebsite/excel`)
5. Route: new `vendor-items` resource under `/finance/vendors/{vendor}/items` in `routes/api/v1/accounting.php`
6. Frontend: `frontend/src/hooks/useVendorItems.ts` + `VendorItemsTab` inside `VendorDetailPage` (tab component); CSV import modal

**Parallel with Phase 1**: Phase 3 (PDF) — no dependencies

## Phase 2: PR Workflow — Accounting Budget Check

*Depends on nothing; independent of Phase 1*

1. Migration: add columns to `purchase_requests`: `budget_checked_by_id`, `budget_checked_at`, `budget_checked_comments`, `returned_by_id`, `returned_at`, `return_reason`
2. Migration: alter `status` CHECK constraint to include `budget_checked | returned` values (ALTER TABLE ... DROP CONSTRAINT ... ADD CONSTRAINT)
3. `PurchaseRequestService`: add `budgetCheck(PurchaseRequest $pr, User $actor, string $comments)` and `returnForRevision(PurchaseRequest $pr, User $actor, string $reason)` methods
   - `budgetCheck`: asserts status = `reviewed`; transitions → `budget_checked`; notifies VP
   - `returnForRevision`: asserts status = `reviewed`; transitions → `returned`; notifies requester
   - When PR status = `returned` → `update()` and `submit()` must allow re-edit (treat like draft)
4. `PurchaseRequestPolicy`: add `budgetCheck()` and `returnForRevision()` methods — requires `vendor_invoices.approve` permission (Accounting Officer)
5. `PurchaseRequestController`: add `budgetCheck()` and `returnForRevision()` action methods
6. Update `vpApprove()` — now asserts status = `budget_checked` (not `reviewed`)
7. Routes: add `POST /{purchaseRequest}/budget-check` and `POST /{purchaseRequest}/return` to procurement.php
8. Add permission: `procurement.purchase-request.budget-check` → assign to `officer` role in RolePermissionSeeder
9. Frontend: update `PurchaseRequestDetailPage.tsx` — add Budget Check button (Accounting Officer visible), Return button, return reason modal; update status badge map and approval timeline

## Phase 3: PR PDF Export

*Can run in parallel with Phase 2*

1. Blade view: `resources/views/procurement/purchase-request-pdf.blade.php` — company header, PR details table, items table with totals, approval signatures section
2. `PurchaseRequestController::pdf(PurchaseRequest $pr)` — uses `barryvdh/dompdf` via `Pdf::loadView()`; returns PDF stream with `Content-Disposition: inline`
3. Route: `GET /purchase-requests/{purchaseRequest}/pdf` in `procurement.php`
4. Frontend: "Print / Export PDF" button on `PurchaseRequestDetailPage.tsx` — opens `/api/v1/procurement/purchase-requests/{ulid}/pdf` in new tab

## Phase 4: Auto-Create PO Draft on VP Approval

*Depends on Phase 2 (VP now approves from `budget_checked` status)*

1. In `PurchaseRequestService::vpApprove()`: after setting PR to `approved`, call `PurchaseOrderService::autoCreateFromPr(PurchaseRequest $pr): PurchaseOrder`
2. `PurchaseOrderService::autoCreateFromPr()` — creates PO with:
   - `status = draft`, `vendor_id = null` (pending assignment)
   - Items cloned from PR items: `item_description`, `unit_of_measure`, `quantity_ordered = quantity`, `agreed_unit_cost = estimated_unit_cost`
   - `item_master_id = null` initially (Purchasing Officer maps via vendor catalog)
3. Frontend: `PurchaseOrderDetailPage.tsx` — when `vendor_id` is null, show "Assign Vendor & Items" mode:
   - Vendor dropdown (accredited vendors)
   - On vendor select: load `vendor_items` for that vendor; show mapping UI to link each PO line item to a vendor catalog item
   - "Finalize PO" button saves vendor + item links + pricing

## Phase 5: Vendor Portal

*Depends on Phase 1 (vendor_items) and Phase 4 (PO auto-create)*

**Backend:**
1. Migration: add `vendor_id` (nullable FK → vendors) to `users` table
2. `RolePermissionSeeder`: add `vendor` role with permissions: `vendor_portal.view_orders`, `vendor_portal.update_fulfillment`, `vendor_portal.manage_items`, `vendor_portal.view_receipts`
3. Migration: `create_vendor_fulfillment_notes_table` — `purchase_order_id`, `vendor_user_id`, `note_type` (`in_transit|delivered|partial`), `notes`, `items` (JSON: po_item_id, qty_delivered), timestamps
4. `VendorFulfillmentService implements ServiceContract`:
   - `markInTransit(PurchaseOrder $po, User $vendorUser)`
   - `markDelivered(PurchaseOrder $po, User $vendorUser, array $items)`: creates `VendorFulfillmentNote`; if qty_delivered < qty_ordered for any item, records partial; auto-creates GR draft for receiving staff to confirm
   - `adjustPOForPartial(PurchaseOrder $po, array $unfulfilledItems)`: reduces `quantity_ordered` on PO items, recalculates `total_po_amount`
5. Vendor-scoped middleware: `vendor.scope` — ensures authenticated user has `vendor` role and all data is scoped to `users.vendor_id`
6. API routes: `routes/api/v1/vendor-portal.php` — `GET /vendor-portal/orders`, `POST /vendor-portal/orders/{po}/in-transit`, `POST /vendor-portal/orders/{po}/deliver`, `GET/POST /vendor-portal/items`

**Frontend:**
7. Separate route group in `router/index.tsx`: `/vendor-portal/*`
8. `VendorPortalLayout.tsx` — simplified sidebar: Dashboard, Orders, Items
9. Pages: `VendorPortalDashboardPage`, `VendorOrdersPage`, `VendorOrderDetailPage` (fulfillment actions), `VendorItemsPage`
10. `frontend/src/hooks/useVendorPortal.ts`

## Phase 6: Accounting Sidebar Simplification

*Independent; frontend-only for most of it*

1. Frontend: update `frontend/src/router/index.tsx` — move Chart of Accounts, Fiscal Periods, Journal Entries (new), Bank Accounts, Bank Reconciliation under `/settings/accounting/*` (System Settings sidebar group)
2. Accounting sidebar (officer-visible) keeps: AP Invoices, PR Budget Approval queue, Vendors, Financial Reports
3. Add "Pending Budget Approvals" to the accounting dashboard: query PRs with status = `reviewed` (awaiting officer's budget check)
4. Backend: no model/service changes needed; confirm all moved routes still work under the new path if needed (likely just frontend navigation changes)

## Phase 7: Role-based Dashboards

*Depends on no other phase; can be parallel*

1. Backend: `GET /dashboard` (already in `routes/api/v1/`) — extend to return role-appropriate widget data:
   - `staff`: my PRs, leave balance, attendance summary
   - `head`: team pending approvals (leave, OT, PR notes)
   - `manager`: full HR stats (headcount, payroll run status)
   - `officer` (Accounting): AP aging, pending budget approvals, open JEs
   - `purchasing_officer`: pending PO drafts, open GRs
   - `plant_manager`: production orders, QC NCRs open, maintenance work orders
   - `executive/vp`: financial summary, pending VP approvals
2. Frontend: `DashboardPage.tsx` — role-based widget grid using `authStore.hasRole()`; separate widget components per domain area

## Phase 8: CRM Module

*Independent – can be built in parallel with other phases*

**Backend:**
1. Migration: `create_crm_tickets_table` — ULID PK, `customer_id` FK (→ AR customers), `client_user_id` FK (→ users), `subject`, `description`, `type` (`complaint|inquiry|request`), `priority` (`low|normal|high|critical`), `status` (`open|in_progress|pending_client|resolved|closed`), `assigned_to_id` FK (→ users), `resolved_at`, timestamps, soft deletes
2. Migration: `create_crm_ticket_messages_table` — `ticket_id`, `author_id` FK (→ users), `body`, `is_internal` (boolean — hides from client view), timestamps
3. Migration: add `client_id` (nullable FK → AR customers) to `users` table
4. `app/Domains/CRM/Models/Ticket.php` + `TicketMessage.php`
5. `app/Domains/CRM/Services/TicketService implements ServiceContract`: `open()`, `reply()`, `assign()`, `resolve()`, `close()`, `reopen()`
6. `app/Domains/CRM/Policies/TicketPolicy`: permissions `crm.tickets.view | create | manage | assign | close`
7. Controller: `app/Http/Controllers/CRM/TicketController.php`
8. Add `client` role to `RolePermissionSeeder` with: `crm.tickets.view | create | reply`; add `crm_manager` and `crm_head` roles (or assign to existing officer/head roles with CRM permissions)
9. Routes: `routes/api/v1/crm.php` — standard CRUD + `POST /{ticket}/reply`, `PATCH /{ticket}/assign`, `PATCH /{ticket}/resolve`, `PATCH /{ticket}/close`
10. Register route file in `routes/api.php`

**Frontend:**
11. `frontend/src/hooks/useCRM.ts`
12. Client portal pages under `/client-portal/*` in router
13. CRM staff pages under `/crm/*`: TicketListPage, TicketDetailPage (with thread view), assign modal
14. `types/crm.ts`, `schemas/crm.ts`

## Relevant Files to Create or Modify

| File | Action |
|---|---|
| `database/migrations/*_create_vendor_items_table.php` | Create |
| `database/migrations/*_add_budget_check_to_purchase_requests.php` | Create |
| `database/migrations/*_add_vendor_id_to_users.php` | Create |
| `database/migrations/*_create_vendor_fulfillment_notes_table.php` | Create |
| `database/migrations/*_create_crm_tickets_table.php` | Create |
| `database/migrations/*_create_crm_ticket_messages_table.php` | Create |
| `database/migrations/*_add_client_id_to_users.php` | Create |
| `app/Domains/AP/Models/VendorItem.php` | Create |
| `app/Domains/AP/Models/Vendor.php` | Modify (add hasMany vendorItems) |
| `app/Domains/AP/Services/VendorService.php` | Modify (add importItems) |
| `app/Domains/AP/Services/VendorItemService.php` | Create |
| `app/Http/Controllers/AP/VendorItemController.php` | Create |
| `app/Domains/Procurement/Services/PurchaseRequestService.php` | Modify (budgetCheck, returnForRevision; update vpApprove trigger) |
| `app/Domains/Procurement/Services/PurchaseOrderService.php` | Modify (add autoCreateFromPr) |
| `app/Domains/Procurement/Policies/PurchaseRequestPolicy.php` | Modify (add budgetCheck, returnForRevision) |
| `app/Http/Controllers/Procurement/PurchaseRequestController.php` | Modify (add budgetCheck, returnForRevision, pdf actions) |
| `app/Http/Controllers/Procurement/PurchaseOrderController.php` | Modify (add vendor assignment finalize action) |
| `resources/views/procurement/purchase-request-pdf.blade.php` | Create |
| `app/Domains/AP/Models/VendorFulfillmentNote.php` | Create |
| `app/Domains/AP/Services/VendorFulfillmentService.php` | Create |
| `app/Http/Controllers/VendorPortal/VendorPortalController.php` | Create |
| `routes/api/v1/vendor-portal.php` | Create |
| `routes/api/v1/crm.php` | Create |
| `app/Domains/CRM/Models/Ticket.php` | Create |
| `app/Domains/CRM/Models/TicketMessage.php` | Create |
| `app/Domains/CRM/Services/TicketService.php` | Create |
| `app/Domains/CRM/Policies/TicketPolicy.php` | Create |
| `app/Http/Controllers/CRM/TicketController.php` | Create |
| `database/seeders/RolePermissionSeeder.php` | Modify (vendor, client, crm_manager roles + new permissions) |
| `frontend/src/hooks/useVendorItems.ts` | Create |
| `frontend/src/hooks/useVendorPortal.ts` | Create |
| `frontend/src/hooks/useCRM.ts` | Create |
| `frontend/src/pages/accounting/VendorItemsTab.tsx` | Create |
| `frontend/src/pages/procurement/PurchaseRequestDetailPage.tsx` | Modify |
| `frontend/src/pages/procurement/CreatePurchaseOrderPage.tsx` | Modify |
| `frontend/src/pages/vendor-portal/*.tsx` | Create (4 pages) |
| `frontend/src/pages/crm/*.tsx` | Create (3 pages) |
| `frontend/src/pages/client-portal/*.tsx` | Create (2 pages) |
| `frontend/src/router/index.tsx` | Modify (new portal routes + settings route migration) |
| `frontend/src/types/crm.ts` | Create |
| `frontend/src/types/vendor.ts` | Modify (add VendorItem) |

## Verification

1. Run `./vendor/bin/pest --testsuite=Feature` after each phase — ensure no regressions
2. Phase 2: test PR chain — submit → note → check → review → budget_check (officer) → vp_approve; also test return flow; also test rejection at budget_check stage
3. Phase 3: hit `GET /api/v1/procurement/purchase-requests/{ulid}/pdf` — verify PDF renders with items
4. Phase 4: VP approve triggers auto-PO creation; check PO exists with `vendor_id = null` and items cloned
5. Phase 5: Create vendor user (role=vendor, vendor_id=X); log in; mark PO as in-transit; mark delivered (partial); verify GR draft auto-created; internal user confirms GR; check PO qty adjusted
6. Phase 7: Log in as `officer` role; verify `/dashboard` shows AP aging + pending budget PRs
7. Phase 8: Create `client` user; log in via `/client-portal`; open ticket; CRM staff replies; client sees reply (non-internal only)
8. Run `cd frontend && pnpm typecheck && pnpm lint` after all frontend changes
9. Run `./vendor/bin/phpstan analyse` — ensure level 5 passes
