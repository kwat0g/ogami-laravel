# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Full technical reference for AI agents:** See `AGENTS.md`. It contains domain tables, architecture patterns, code style rules, and common pitfalls in detail.

---

## What This Project Is

**Ogami ERP** — a manufacturing ERP for Philippine businesses. 20 domain modules: HR, Payroll, Accounting, AP, AR, Tax, Procurement, Inventory, Production, QC, Maintenance, Mold, Delivery, ISO, CRM, Fixed Assets, Budget, Attendance, Leave, Loan.

**Stack:** Laravel 11 (PHP 8.2+) + PostgreSQL 16 + Redis / React 18 + TypeScript + Vite 6 + pnpm 10.

---

## Commands

### Development

```bash
# Start everything (PG + Redis + Laravel:8000 + Vite:5173 + queue)
npm run dev              # or: bash dev.sh
npm run dev:minimal      # without queue/Reverb (faster)
npm run dev:full         # with Reverb WebSocket server

php artisan serve        # backend only
cd frontend && pnpm dev  # frontend only
```

### Database

```bash
php artisan migrate:fresh --seed   # full reset
php artisan migrate                # run new migrations only
php artisan db:seed                # seed only
```

### Backend Tests (Pest PHP)

```bash
./vendor/bin/pest                          # all suites
./vendor/bin/pest --testsuite=Unit         # value objects, payroll golden suite (no DB)
./vendor/bin/pest --testsuite=Feature      # HTTP endpoint tests
./vendor/bin/pest --testsuite=Integration  # cross-domain workflows
./vendor/bin/pest --testsuite=Arch         # structural constraint rules
./vendor/bin/pest tests/Feature/HR/        # single directory
./vendor/bin/pest --filter "test name"     # single test by name
```

### Frontend Tests & Quality

```bash
cd frontend
pnpm test        # Vitest unit tests
pnpm typecheck   # tsc --noEmit
pnpm lint        # ESLint
pnpm e2e         # Playwright tests
pnpm e2e:ui      # Playwright with UI
pnpm build       # production build
```

### Static Analysis & Code Style

```bash
./vendor/bin/phpstan analyse   # Larastan level 5
./vendor/bin/pint              # code style fixer (auto-fixes)
```

### Docker

```bash
docker-compose up --build
docker-compose exec app php artisan migrate:fresh --seed
```

---

## Architecture Overview

### Backend — Domain-Driven Structure

```
app/
  Domains/<Domain>/
    Models/           Eloquent models
    Services/         Business logic (final class, implements ServiceContract)
    Policies/         Laravel Gate policies (registered in AppServiceProvider)
    StateMachines/    Status transitions (hold TRANSITIONS constant)
    Pipeline/         Payroll computation steps (Step01–Step17)
  Http/
    Controllers/      Thin — only authorize() + delegate to service
    Requests/         FormRequest validation
    Resources/        API response transformers
  Shared/
    ValueObjects/     Money, Minutes, PayPeriod, DateRange, EmployeeCode
    Exceptions/       DomainException and subclasses
    Contracts/        Marker interfaces only (ServiceContract, BusinessRule)
routes/api/v1/        One file per domain (28 files)
```

**The flow:** Route → Controller → `$this->authorize()` → Service → DB::transaction → Resource.

Controllers must have **zero** business logic and **zero** DB calls (ARCH-001). All writes are wrapped in `DB::transaction()` inside services (ARCH-002).

### Frontend Structure

```
frontend/src/
  hooks/use<Domain>.ts   TanStack Query wrappers — one file per domain
  pages/<domain>/        Page components
  types/<domain>.ts      TypeScript interfaces
  schemas/<domain>.ts    Zod validation schemas (17/20 domains have these)
  stores/                authStore.ts + uiStore.ts only (never create more)
  lib/api.ts             Axios instance (baseURL /api/v1, withCredentials: true)
  router/index.tsx       All routes in a single lazy-loaded file
```

Server state lives exclusively in TanStack Query hooks. Only 2 Zustand stores exist — don't create more. `queryKey` always includes the filters object.

### Payroll Pipeline (17 Steps)

```
Step01Snapshots → Step02PeriodMeta → Step03Attendance → Step04YTD → Step05BasicPay →
Step06Overtime → Step07Holiday → Step08NightDiff → Step09GrossPay →
Step10SSS → Step11PhilHealth → Step12PagIBIG → Step13Taxable →
Step14WHT → Step15LoanDeductions → Step16OtherDeductions → Step17NetPay
```

Each step: `public function __invoke(PayrollComputationContext $ctx, Closure $next)`. Steps only mutate `$ctx` — never query the DB directly.

**Payroll Run states (14):** `DRAFT → SCOPE_SET → PRE_RUN_CHECKED → PROCESSING → COMPUTED → REVIEW → SUBMITTED → HR_APPROVED → ACCTG_APPROVED → VP_APPROVED → DISBURSED → PUBLISHED` (plus `RETURNED`/`REJECTED` → `DRAFT`).

---

## Critical Rules

### PHP

- Every PHP file: `<?php\n\ndeclare(strict_types=1);`
- Domain services: `final class` implementing `ServiceContract`
- Value objects: `final readonly class` in `app/Shared/ValueObjects/`
- **Never use `float` for money** — use `Money` value object (stores centavos as integer)
  - `₱25,000 = 2_500_000 centavos`
  - `Money::fromCentavos()` throws on negative — guard before subtracting
- `DomainException` requires all 3 args: `message`, `errorCode` (SCREAMING_SNAKE), `httpStatus`
- No `dd()`, `dump()`, `var_dump()`, `ray()` in `app/` (ARCH-005)

### Database Migrations

- Every domain table needs: `$table->ulid('ulid')->unique()` — frontend uses ULIDs in URLs, never integer IDs
- Money columns: `unsignedBigInteger` (centavos) — never `decimal` or `float`
- Enums: `string` + PostgreSQL CHECK constraint — never `$table->enum()`
- Generated columns (`daily_rate`, `hourly_rate`): defined via `DB::statement()` after `Schema::create()`, never in `$fillable` or factories
- Government IDs: store encrypted text + separate `_hash` (SHA-256) column for uniqueness

### TypeScript / React

- Import path alias: `import api from '@/lib/api'` (default export, not named)
- URL params are ULIDs: `useParams<{ ulid: string }>()`
- Use `z.coerce.number()` for numeric IDs and monetary inputs in Zod schemas
- Paginated response shape: `{ data: T[], meta: { current_page, last_page, per_page, total } }` — use `.meta`, not `.pagination`
- Unused vars/args must be prefixed with `_`

### Testing

- **Always PostgreSQL** for tests (`ogami_erp_test`) — never SQLite
- **Never create `.env.testing`** — config is locked in `phpunit.xml`
- Seed RBAC before creating users: `$this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])`
- Payroll tests: always use `PayrollTestHelper` (strips generated columns, handles field aliases)
- Custom expectations: `->toBeValidationError('field')` and `->toBeDomainError('ERROR_CODE')` (defined in `tests/Pest.php`)

### Authorization & SoD

- `authStore.hasPermission()` is strict — `admin` only has `system.*` permissions, not HR/payroll implicitly
- SoD: same user who created a record cannot approve it (`$user->id !== $record->created_by_id` in policies)
- `dept_scope` middleware applies automatically; bypass with `Employee::withoutDepartmentScope()`
- Only `admin`, `super_admin`, `executive`, `vice_president` bypass department scoping — `manager`/`head` do not

---

## API Response Format

```json
// Success (single)
{ "data": { ... } }

// Success (list, paginated)
{ "data": [...], "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 73 } }

// Error
{ "success": false, "error_code": "DOMAIN_ERROR_CODE", "message": "Human-readable message" }
```

---

## Seeder Order (strict dependency chain)

1. Rate tables: SSS, PhilHealth, PagIBIG, Tax brackets, Overtime multipliers, Holiday calendar, Minimum wage
2. RBAC: `RolePermissionSeeder` → `SampleAccountsSeeder`
3. HR reference: `SalaryGradeSeeder`, `LeaveTypeSeeder`, `LoanTypeSeeder`, `ShiftScheduleSeeder`
4. Accounting: `ChartOfAccountsSeeder`
5. Org: `FiscalPeriodSeeder`, `DepartmentPositionSeeder`, `DepartmentPermissionProfileSeeder`
6. Transactional: `SampleDataSeeder`, `ManufacturingEmployeeSeeder`, `FleetSeeder`, `LeaveBalanceSeeder`
7. System: `SystemSettingsSeeder`, `TestAccountsSeeder`

---

## Domain-Specific Gotchas

- **PO status sequence:** `draft → sent → negotiating → acknowledged → in_transit → delivered → partially_received/fully_received → closed`. `delivered` = vendor confirmed, GR draft created, awaiting warehouse confirmation. All status-aware code (`canReceiveGoods()`, `ThreeWayMatchService`, vendor portal query) must include it.
- **`VendorFulfillmentService`** lives in `app/Domains/AP/Services/` (not Procurement) — vendor portal fulfillment is treated as an AP concern.
- **Stock updates:** Always use `StockService::receive()` — never `StockBalance::firstOrCreate + increment` directly. Direct model calls bypass `stock_ledger_entries` and leave no audit trail.
- **PHPStan + Carbon dates:** Columns with `'date'` cast are typed `string` in PHPDocs. `instanceof Carbon` always evaluates to false — use `(string) $model->date_col` instead of `->toDateString()` or `instanceof` checks.
- **Vendor portal `orderDetail`** returns raw model JSON (no Resource transformer), so all `$fillable` attributes are exposed directly to the vendor user.
- **Vendor propose-changes `items` field:** Use `['present','array']` (not `['required','array','min:1']`) — empty array is valid when only PO-level changes (e.g. delivery date) are proposed.
- **Queued notifications:** Always use `::fromModel()` static factory — never `new NotificationClass($model)`. Queuing with an Eloquent model causes `ModelNotFoundException` if the model is soft-deleted before the job runs. All notification classes in this project implement `fromModel()`.
- **`pulse:check` schedule:** Must have `->withoutOverlapping()`. It's long-running; without it `everyMinute()` piles up orphaned processes. On VPS, `pulse:work` runs as a supervisor daemon instead.
- **pnpm workspace root:** `pnpm-lock.yaml` lives at the repo root, not inside `frontend/`. Run `pnpm install` from repo root, then `pnpm build` from `frontend/`. Use `--no-frozen-lockfile` on the VPS.
- **Fixed Assets:** `asset_code` is set by a PostgreSQL trigger — never set it in PHP or factories
- **Fixed Assets:** Frontend TS type uses `under_maintenance`; DB CHECK uses `impaired` — DB is authoritative
- **Fixed Assets CSV export:** inline route closure queries wrong table name (`asset_depreciation_entries` instead of `fixed_asset_depreciation_entries`) — do not copy this bug
- **`api.ts` write cooldown:** duplicate POST/PUT/PATCH/DELETE to the same URL within 1500 ms are silently aborted
- **`HasPublicUlid` trait** requires `SoftDeletes` on the same model
- **Payroll golden suite** (`tests/Unit/Payroll/GoldenSuiteTest.php`): 24 canonical scenarios — do not change expected values without documented justification

---

## Codebase Search (SocratiCode)
This project is indexed with SocratiCode. Always use its MCP tools 
to explore the codebase before reading any files directly.

### Workflow
1. **Start most explorations with `codebase_search`.** 
   - Use broad queries for orientation: "how is authentication handled", 
     "database connection setup", "error handling patterns"
   - Use precise queries for symbol lookups: exact function/class names
   - Prefer search results to infer which files to read — do NOT 
     speculatively open files directly

2. **Only read files after searching** — use search results to identify 
   the exact file and line range needed, then read only that section.


## Additional Reference Files

| File | Purpose |
|------|---------|
| `AGENTS.md` | Full agent reference: domain tables, all patterns, all pitfalls |
| `.github/instructions/backend.instructions.md` | PHP coding rules |
| `.github/instructions/frontend.instructions.md` | React/TS coding rules |
| `.github/instructions/migrations.instructions.md` | Migration patterns |
| `.github/instructions/tests.instructions.md` | Test writing rules |
| `.github/instructions/fixed-assets.instructions.md` | Fixed Assets domain detail |
| `.agents/skills/` | Specialized skills: payroll-debugger, migration-writer, code-reviewer, etc. |
| `docs/testing/REAL_LIFE_ERP_TESTING_GUIDE.md` | End-to-end ERP workflow testing |
