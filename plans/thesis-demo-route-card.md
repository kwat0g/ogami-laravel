# Thesis Demo Route Card

## Goal
Use this as a fast, module-wide navigation guide during live presentation.

## Demo Baseline
- Login role: `superadmin@ogamierp.local`
- Primary browser route after login: `/dashboard`
- If session expires: go to `/login`, sign in again, then reopen last module route.

## Module Paths (Primary + Fallback)
| Module | Primary Route | Fallback Route | What to Show Quickly |
|---|---|---|---|
| Auth | `/login` | `/dashboard` | Valid login and redirect to dashboard |
| HR | `/hr/employees` | `/hr/attendance` | Employee list and attendance page load |
| Payroll | `/payroll/runs` | `/payroll/periods` | Payroll runs table and period records |
| Accounting | `/accounting/journal-entries` | `/accounting/vat-ledger` | Journal list and VAT ledger cards |
| Procurement | `/procurement/purchase-requests` | `/procurement/purchase-orders` | PR and PO workflow pages |
| Inventory | `/inventory/items` | `/inventory/stock` | Item master and stock balance |
| Production | `/production/orders` | `/production/delivery-schedules` | Production orders and schedules |
| QC | `/qc/inspections` | `/qc/ncrs` | Inspection list and NCR list |
| Mold | `/mold/masters` | `/mold/lifecycle` | Mold registry and lifecycle view |
| CRM | `/crm/tickets` | `/crm/dashboard` | Ticket list (dashboard optional) |
| Delivery | `/delivery/receipts` | `/delivery/vehicles` | Delivery receipts and fleet vehicles |
| RBAC | `/admin/users` | `/approvals/pending` | Role-sensitive visibility and guarded routes |

## If a Page Looks Blocked
Check in this order:
1. URL accidentally moved to `/login`.
2. Page is a strict permission route for current account.
3. Temporary API/session issue (refresh once).

Quick recovery:
1. Go to `/dashboard`.
2. Retry the module primary route.
3. If still blocked, use module fallback route.

## Live Demo Sequence (Recommended)
1. Auth -> HR -> Payroll
2. Accounting -> Procurement -> Inventory
3. Production -> QC -> Delivery
4. CRM -> Mold -> RBAC

## Presenter Notes
- Avoid deep edits during live demo; prefer list/detail and status transitions.
- If any deep action is unavailable due to data state, show fallback route immediately.
- For VAT report action, open ledgers only show action buttons when period is open.

## Role-Based Demo Packs
Use these when the panel asks to validate role restrictions live.

### Pack A — Executive / Super Admin Coverage
- Account: `superadmin@ogamierp.local`
- Purpose: fastest full-system sweep without permission friction.
- Suggested sequence:
1. `/dashboard`
2. `/accounting/journal-entries`
3. `/procurement/purchase-requests`
4. `/inventory/items`
5. `/production/orders`
6. `/qc/inspections`
7. `/delivery/receipts`

### Pack B — Operations Manager Coverage
- Account: `prod.manager@ogamierp.local`
- Purpose: demonstrate manufacturing operations with realistic role scope.
- Suggested sequence:
1. `/production/orders`
2. `/production/delivery-schedules`
3. `/inventory/requisitions`
4. `/qc/inspections`
5. `/maintenance/work-orders`

### Pack C — Accounting Officer Coverage
- Account: `accounting@ogamierp.local`
- Purpose: show finance workflows and approval boundaries.
- Suggested sequence:
1. `/accounting/journal-entries`
2. `/accounting/ap/invoices`
3. `/ar/invoices`
4. `/accounting/vat-ledger`
5. `/banking/accounts`

### Pack D — HR Manager Coverage
- Account: `hr.manager@ogamierp.local`
- Purpose: demonstrate people operations and payroll pipeline entry points.
- Suggested sequence:
1. `/hr/employees`
2. `/hr/attendance`
3. `/hr/leave`
4. `/payroll/runs`

## Account Switch Flow (Fast)
When switching roles mid-demo:
1. Open `/login` in the same tab.
2. Sign in as next role account.
3. Jump directly to that role's first route from the pack above.
4. If blocked on a route, show fallback route and continue the script.

## High-Value Fallbacks by Theme
- Finance fallback: `/accounting/journal-entries`
- Operations fallback: `/production/orders`
- Supply chain fallback: `/procurement/purchase-orders`
- Warehouse fallback: `/inventory/stock`
- Quality fallback: `/qc/ncrs`
- Delivery fallback: `/delivery/receipts`