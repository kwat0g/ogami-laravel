# ogamiPHP Production Audit Report
Generated: 2026-03-28

---

## Executive Summary

This is a **mature, architecturally sound ERP system** with 21 domain modules, 120+ service classes, 100+ frontend pages, and a comprehensive test suite. The core modules (HR, Payroll, Procurement, Accounting, Inventory, Production) are production-grade with proper service layer patterns, policy-based authorization, state machines, and deep test coverage. However, the system has a **critical demo-breaking defect**: multiple "Phase 1-4 Enhancement" features were wired into route files referencing model and service classes that **do not exist** in the codebase -- these routes will crash on access. Additionally, the Sales module has zero authorization checks, and the ISO domain layer is completely missing. Before defense, the team must either remove or implement the phantom enhancement classes, add authorization to Sales, and decide whether to demo or hide ISO.

---

## Discovery Summary

| Area | Count | Notes |
|---|---|---|
| Domain Modules | 21 | Accounting, AP, AR, Attendance, Budget, CRM, Dashboard, Delivery, FixedAssets, HR, Inventory, Leave, Loan, Maintenance, Mold, Payroll, Procurement, Production, QC, Sales, Tax |
| Eloquent Models | ~110 | Well-distributed across domains; some enhancement-phase models missing |
| Service Classes | 123 | All `final class` implementing `ServiceContract` -- excellent conformance |
| API Route Groups | 28 | v1 prefix, properly namespaced per domain |
| React Pages | ~140+ | Covers all core domains; some enhancement pages missing |
| Migrations | ~160 | Chronological; includes phase 1-4 enhancement tables |
| Tests | ~300+ test cases | Pest-based; strong payroll coverage (24 golden suite), integration tests, RBAC |
| Roles | 10 | super_admin, admin, executive, vice_president, manager, officer, head, staff, vendor, client |
| Permissions | ~200+ | Granular, module-scoped with SoD enforcement |
| Policies | 40 | Domain-level; Sales has ZERO policies |

---

## Critical Findings (fix before defense)

### C-01: Phantom Enhancement Classes -- Routes Reference Non-Existent Code
**Severity: CRITICAL -- Demo will crash**

Multiple route files and controllers reference service classes and models that do not exist anywhere in the codebase. Hitting any of these routes will produce a `Class not found` fatal error.

| Missing Class | Referenced In |
|---|---|
| `App\Domains\ISO\Services\ISOService` | [`ISOController.php`](app/Http/Controllers/ISO/ISOController.php:10) |
| `App\Domains\ISO\Models\ControlledDocument` | [`ISOController.php`](app/Http/Controllers/ISO/ISOController.php:8) |
| `App\Domains\ISO\Models\InternalAudit` | [`ISOController.php`](app/Http/Controllers/ISO/ISOController.php:9) |
| `App\Domains\ISO\Models\AuditFinding` | [`ISOController.php`](app/Http/Controllers/ISO/ISOController.php:7) |
| `App\Domains\CRM\Models\Lead` | [`LeadController.php`](app/Http/Controllers/CRM/LeadController.php:7) |
| `App\Domains\CRM\Services\LeadService` | [`LeadController.php`](app/Http/Controllers/CRM/LeadController.php:8) |
| `App\Domains\CRM\Services\LeadScoringService` | [`crm.php`](routes/api/v1/crm.php:32) |
| Opportunity model + service | [`OpportunityController.php`](app/Http/Controllers/CRM/OpportunityController.php:12) |
| `App\Domains\Production\Services\CapacityPlanningService` | [`production.php`](routes/api/v1/production.php:83) |
| `App\Domains\Production\Services\MrpService` | [`production.php`](routes/api/v1/production.php:92) |
| `App\Domains\ISO\Services\DocumentAcknowledgmentService` | [`enhancements.php`](routes/api/v1/enhancements.php:376) |
| `App\Domains\FixedAssets\Models\AssetTransfer` | [`fixed_assets.php`](routes/api/v1/fixed_assets.php:82) |
| `App\Domains\HR\Services\PerformanceAppraisalService` | [`enhancements.php`](routes/api/v1/enhancements.php:166) (referenced in tests) |
| `BudgetAmendment` model | Migration exists but no Eloquent model class |
| `PerformanceAppraisal` model | Migration exists but no Eloquent model class |

**Fix:** Either create stub implementations for all missing classes, or remove/comment-out the routes referencing them. The fastest fix is to wrap all enhancement routes in a feature flag or remove them from the route registration.

---

### C-02: Sales Module Has ZERO Authorization Checks
**Severity: CRITICAL -- Any authenticated user can create/modify quotes and orders**

[`SalesOrderController.php`](app/Http/Controllers/Sales/SalesOrderController.php:13) and [`QuotationController.php`](app/Http/Controllers/Sales/QuotationController.php:14) have:
- No `$this->authorize()` calls on any method
- No Policy classes in `app/Domains/Sales/Policies/` (directory does not exist)
- Inline validation in controllers instead of FormRequest classes
- Raw model JSON responses instead of Resource classes

The route file [`sales.php`](routes/api/v1/sales.php:18) only has `module_access:sales` middleware, but no per-action permission checks. Any user with sales module access can create, modify, send, accept, reject quotations and create/confirm/cancel sales orders.

**Fix:** Create `QuotationPolicy`, `SalesOrderPolicy`, `PriceListPolicy`; add `$this->authorize()` to every controller method; create FormRequest and Resource classes.

---

### C-03: Enhancement Route Closures Lack Authorization
**Severity: CRITICAL -- Authorization bypass on financial operations**

[`enhancements.php`](routes/api/v1/enhancements.php:17) wraps all routes in only `auth:sanctum` -- no `module_access` middleware, no permission checks. This means ANY authenticated user (including staff, vendor, client portal users) can:
- Execute AP payment optimization
- View financial ratios
- Release QC quarantine items
- Perform fixed asset revaluation/impairment
- Execute loan payoff and restructure
- View payroll final pay calculations

**Fix:** Add appropriate `module_access` middleware and permission checks to each enhancement route group.

---

## High Findings (fix if time allows)

### H-01: ARCH-001 Violations -- DB:: Calls in Controllers
**14 occurrences** of `DB::` usage found in controllers, violating the architectural rule that controllers must have zero DB calls:

| Controller | Line | Usage |
|---|---|---|
| [`SystemSettingController.php`](app/Http/Controllers/Admin/SystemSettingController.php:33) | 33, 95, 130, 172, 192, 204, 249, 262, 308 | 9 raw DB calls for settings CRUD |
| [`EmployeeController.php`](app/Http/Controllers/HR/EmployeeController.php:83) | 83 | Role lookup query |
| [`EmployeeSelfServiceController.php`](app/Http/Controllers/Employee/EmployeeSelfServiceController.php:454) | 454 | Settings query |
| [`ChartOfAccountsController.php`](app/Http/Controllers/Admin/ChartOfAccountsController.php:42) | 42 | Subquery in controller |
| [`ArReportsController.php`](app/Http/Controllers/AR/ArReportsController.php:180) | 180 | Settings query |
| [`BackupController.php`](app/Http/Controllers/Admin/BackupController.php:371) | 371-376 | Raw DDL statements |

### H-02: Massive Business Logic in Route Files
**Severity: HIGH -- Maintenance nightmare, untestable**

Route files contain hundreds of inline closure routes with complex business logic:
- [`dashboard.php`](routes/api/v1/dashboard.php) -- **1900+ lines** of raw DB queries in closures
- [`admin.php`](routes/api/v1/admin.php) -- ~500 lines of user management logic in closures
- [`enhancements.php`](routes/api/v1/enhancements.php) -- ~500 lines across 30+ closures
- [`procurement.php`](routes/api/v1/procurement.php) -- payment batch logic in closures
- [`hr.php`](routes/api/v1/hr.php) -- department/position CRUD, reports in closures
- [`attendance.php`](routes/api/v1/attendance.php) -- shift CRUD, summary, DTR export in closures
- [`budget.php`](routes/api/v1/budget.php) -- variance analysis in closures
- [`ar.php`](routes/api/v1/ar.php) -- aging report, dunning in closures

These closures bypass the service layer, contain raw DB queries, have no unit test coverage, and violate ARCH-001 extensively.

### H-03: Sales Controllers Return Raw Models Instead of Resources
**Severity: HIGH -- Over-exposure of model attributes**

Both [`SalesOrderController.php`](app/Http/Controllers/Sales/SalesOrderController.php:48) and [`QuotationController.php`](app/Http/Controllers/Sales/QuotationController.php:49) return `response()->json(['data' => $model])` which exposes all `$fillable` attributes directly, potentially including internal IDs, timestamps, and fields not intended for the client.

### H-04: No ISO Domain Layer
**Severity: HIGH -- Entire module non-functional**

The ISO module has:
- Route file: [`routes/api/v1/enhancements.php`](routes/api/v1/enhancements.php:374) (references missing services)
- Controller: [`ISOController.php`](app/Http/Controllers/ISO/ISOController.php) (references missing models)
- FormRequests: 3 exist in [`app/Http/Requests/ISO/`](app/Http/Requests/ISO/)
- Resources: 2 exist in [`app/Http/Resources/ISO/`](app/Http/Resources/ISO/)
- Migration: [`create_iso_tables.php`](database/migrations/2026_03_05_000017_create_iso_tables.php) exists
- **Missing entirely:** Models directory, Services directory, Policies directory under `app/Domains/ISO/`

The database tables exist but there are no Eloquent models or service classes to interact with them.

### H-05: CRM Lead/Contact/Opportunity Domain Layer Missing
**Severity: HIGH -- CRM routes will crash**

The CRM module routes reference Lead and Opportunity controllers/models that were planned in Phase 1 enhancements but never implemented:
- [`LeadController.php`](app/Http/Controllers/CRM/LeadController.php) exists but references non-existent `Lead` model and `LeadService`
- [`OpportunityController.php`](app/Http/Controllers/CRM/OpportunityController.php) exists but references non-existent models
- Route file [`crm.php`](routes/api/v1/crm.php:26) registers routes for leads and opportunities

### H-06: No FormRequest Classes for Sales Module
**Severity: HIGH**

Both Sales controllers use inline `$request->validate()` instead of dedicated FormRequest classes. This violates the pattern used everywhere else in the codebase and means validation logic is not reusable or independently testable.

---

## Medium Findings (note for Q&A preparation)

### M-01: Enhancement Phase Migrations Without Corresponding Models
Several Phase 1-4 migrations created tables but no corresponding Eloquent model classes exist:
- `performance_appraisals` -- migration [`2026_03_28_000001`](database/migrations/2026_03_28_000001_create_performance_appraisals_table.php)
- `budget_amendments` -- migration [`2026_03_28_000002`](database/migrations/2026_03_28_000002_create_budget_amendments_table.php)
- `remaining_enhancement_tables` -- migration [`2026_03_28_000003`](database/migrations/2026_03_28_000003_create_remaining_enhancement_tables.php) (catch-all)
- `asset_transfers` -- migration [`phase4_asset_transfer`](database/migrations/2026_03_27_240000_phase4_asset_transfer.php)
- `timesheet_approvals` -- model exists (`TimesheetApproval`) but no service/controller

### M-02: Inline Route Closures Use `abort_unless()` Instead of Policies
Many closure-based routes use `abort_unless($request->user()->can(...), 403)` instead of proper Policy authorization. While functional, this:
- Bypasses the policy layer
- Does not log authorization attempts
- Cannot be tested with `$this->authorize()`

### M-03: Search Route Has Raw DB Queries Without Rate Limiting
[`search.php`](routes/api/v1/search.php:19) runs raw DB queries across 4+ tables with user-supplied `$pattern` using `ilike`. While parameterized (safe from SQL injection), there is no per-user rate limit on this expensive search endpoint beyond the global API throttle.

### M-04: Test Coverage Gaps

| Module | Test Status | Gap |
|---|---|---|
| Sales | Basic feature tests only | No workflow tests, no SoD tests, no authorization tests |
| ISO | Feature test exists but tests service resolution that will fail | Services dont exist |
| CRM Leads/Opportunities | No tests | Classes dont exist |
| Fixed Assets | Basic CRUD tests | No depreciation workflow, no disposal, no transfer tests |
| Delivery | Basic CRUD tests | No full DR lifecycle, no POD workflow |
| Budget | Variance tests exist | No amendment workflow tests |
| Maintenance | Basic CRUD tests | No PM schedule automation, no analytics tests |
| Mold | Basic CRUD tests | No cost amortization tests |
| Dashboard | Route access tests | No data correctness tests |

### M-05: No Frontend Pages for Several Enhancement Features
- No ISO document management pages
- No CRM Lead/Opportunity management pages (CRM dashboard exists but no dedicated Lead/Opportunity CRUD)
- No Performance Appraisal pages
- No Timesheet Approval pages
- No Asset Transfer pages

### M-06: Vendor Portal Returns Raw Model JSON
Per AGENTS.md: "Vendor portal `orderDetail` returns raw model JSON (no Resource) -- all `$fillable` exposed." This is a known issue but should be noted for security-conscious panelists.

### M-07: Job Queue Configuration
Jobs have basic configuration but:
- [`ProcessPayrollBatch`](app/Jobs/Payroll/ProcessPayrollBatch.php) -- verify `$tries` and `failed()` handler
- [`ProcessAttendanceCsvRow`](app/Jobs/Attendance/ProcessAttendanceCsvRow.php) -- CSV row processing should have retry limits
- Only 6 total queued jobs across the entire system -- some operations that should be queued likely run synchronously

---

## Architecture Coherence Map

| Module | Model | Migration | Service | Controller | API Route | Frontend | Policy | Tests | Seeder | Score |
|---|---|---|---|---|---|---|---|---|---|---|
| HR | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| Payroll | Yes | Yes | Yes (21) | Yes | Yes | Yes | Yes | Yes (24 GS + 40+) | Yes | Complete |
| Attendance | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| Leave | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| Loan | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| Accounting | Yes | Yes | Yes (13) | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| AP | Yes | Yes | Yes (11) | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| AR | Yes | Yes | Yes (7) | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| Procurement | Yes | Yes | Yes (7) | Yes | Yes | Yes | Yes | Yes (50+) | Yes | Complete |
| Inventory | Yes | Yes | Yes (10) | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| Production | Yes | Yes | Yes (8) | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| QC | Yes | Yes | Yes (7) | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| CRM (Tickets) | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| CRM (Orders) | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| Delivery | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Basic | Yes | Partial |
| Maintenance | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Basic | Yes | Partial |
| Mold | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Basic | Yes | Partial |
| Budget | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Complete |
| FixedAssets | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Basic | Yes | Partial |
| Tax | Yes | Yes | Yes (5) | Yes | Yes | Yes (1 page) | Yes | Basic | Yes | Partial |
| Sales | Yes | Yes | Yes | Yes | Yes | Yes | **NO** | Basic | Yes | **Partial** |
| Dashboard | No model | N/A | Yes | Yes | Yes | Yes (15) | N/A | Yes | N/A | Complete |
| **ISO** | **NO** | Yes | **NO** | Yes | Yes | **NO** | **NO** | Fail | No | **Broken** |
| **CRM Leads** | **NO** | Yes | **NO** | Yes | Yes | **NO** | **NO** | No | No | **Broken** |
| **Enhancements** | Partial | Yes | Partial | N/A | Yes | Partial | **NO** | Partial | No | **Broken** |

---

## Panelist Risk Register

| # | Risk | Domain | Severity | Likely Panelist Question |
|---|---|---|---|---|
| 1 | Route crashes from non-existent classes | ISO, CRM Leads, Enhancements | Critical | Can you show me the ISO document management? Can you demonstrate lead-to-opportunity conversion? |
| 2 | Sales module has zero authorization | Sales | Critical | Who can create a quotation? What stops a warehouse staff from creating sales orders? |
| 3 | Enhancement routes bypass all permission checks | Cross-cutting | Critical | What authorization controls exist on your financial ratio endpoints? |
| 4 | 1900 lines of business logic in dashboard route file | Dashboard | High | How do you unit test your dashboard KPI calculations? |
| 5 | DB:: calls in controllers violate own arch rules | Multiple | High | You have architecture tests -- do they pass? Show me ARCH-001. |
| 6 | No Resource classes in Sales -- raw model exposure | Sales | High | How do you control which fields are exposed in your API responses? |
| 7 | Inline route closures contain untestable logic | Multiple | High | What is your test coverage for procurement analytics? For budget variance? |
| 8 | Missing FormRequest classes for Sales | Sales | Medium | How do you validate sales order input? Is validation reusable? |
| 9 | No frontend for ISO, Leads, several enhancements | Multiple | Medium | Your ERD shows ISO tables -- where is the UI? |
| 10 | Limited test coverage on Delivery, Maintenance, Mold | Multiple | Medium | Can you demonstrate a complete delivery receipt lifecycle test? |
| 11 | Enhancement migrations created tables with no models | Multiple | Medium | I see a performance_appraisals table -- is it functional? |
| 12 | Only 6 queued jobs for 21 modules | Cross-cutting | Medium | What happens when 100 employees run payroll simultaneously? |

---

## Recommended Fix Priority Queue

### Priority 1: Remove or Guard Phantom Routes (prevents demo crashes)
1. **Comment out or feature-flag all enhancement routes** in [`enhancements.php`](routes/api/v1/enhancements.php) that reference non-existent services -- specifically: CapacityPlanningService, MrpService, DocumentAcknowledgmentService, PerformanceAppraisalService, AssetTransfer, BudgetAmendment routes
2. **Comment out CRM lead/opportunity routes** in [`crm.php`](routes/api/v1/crm.php:26-50) or create stub model/service classes
3. **Comment out ISO routes** or create the missing `app/Domains/ISO/` domain layer (Models, Services, Policies)
4. **Comment out AssetTransfer routes** in [`fixed_assets.php`](routes/api/v1/fixed_assets.php:80-110)

### Priority 2: Sales Module Authorization (critical security gap)
5. Create `QuotationPolicy` and `SalesOrderPolicy` in `app/Domains/Sales/Policies/`
6. Add `$this->authorize()` calls to every method in [`SalesOrderController.php`](app/Http/Controllers/Sales/SalesOrderController.php) and [`QuotationController.php`](app/Http/Controllers/Sales/QuotationController.php)
7. Create `SalesOrderResource` and `QuotationResource` to replace raw model returns

### Priority 3: Enhancement Route Authorization (security gap)
8. Add `module_access` middleware to enhancement route groups in [`enhancements.php`](routes/api/v1/enhancements.php)
9. Add permission checks to each enhancement sub-group (AP, QC quarantine, fixed assets, loans, payroll)

### Priority 4: Panelist Q&A Preparation (if time remains)
10. Prepare talking points for why dashboard logic is in route closures (acknowledge tech debt, explain planned refactor)
11. Prepare a list of which enhancement features are "implemented" vs "planned" for Phase 2
12. Run `./vendor/bin/pest --testsuite=Arch` to verify architecture tests still pass -- fix any failures
13. Run `./vendor/bin/phpstan analyse` and address any class-not-found errors from phantom references

### Priority 5: Polish (only if all above are done)
14. Extract SystemSettingController DB queries into a SystemSettingService
15. Create FormRequest classes for Sales controllers
16. Add basic feature tests for Sales authorization
17. Add Resource classes for Sales responses
