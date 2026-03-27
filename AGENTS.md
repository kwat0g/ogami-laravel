# AGENTS.md

This file provides guidance to agents when working with code in this repository.

> **Stack:** Laravel 11 (PHP 8.2+) / PostgreSQL 16 / Redis — React 18 / TypeScript / Vite 6 / pnpm 10
> **20 domain modules** under `app/Domains/<Domain>/`. Flow: Route → Controller → authorize() → Service → DB::transaction() → Resource.

## Commands

```bash
# Dev
npm run dev                # PG + Redis + Laravel:8000 + Vite:5173 + queue
npm run dev:minimal        # Without queue/Reverb

# Backend tests (Pest) — always PostgreSQL, never SQLite
./vendor/bin/pest tests/Feature/FooTest.php
./vendor/bin/pest --filter=method_name
./vendor/bin/pest --testsuite=Unit|Feature|Integration|Arch

# Frontend (run from frontend/)
pnpm test                  # Vitest
pnpm lint && pnpm typecheck

# Lint
./vendor/bin/pint          # PHP CS Fixer
./vendor/bin/phpstan analyse
```

## Critical Non-Obvious Rules

- **Money:** Never `float`. Use `Money` VO (centavos as int). `Money::fromCentavos()` throws on negative — guard before subtracting. DB columns: `unsignedBigInteger`.
- **DomainException:** 4 constructor args: `message`, `errorCode` (SCREAMING_SNAKE), `httpStatus`, `context[]`. All domain exceptions must extend it.
- **Generated columns** (`daily_rate`, `hourly_rate`): defined via `DB::statement()` after `Schema::create()` — never in `$fillable` or factories.
- **`HasPublicUlid` trait** requires `SoftDeletes` on the same model.
- **DB enums:** `string` + PostgreSQL CHECK constraint — never `$table->enum()`.
- **Government IDs:** Store encrypted text + separate `_hash` (SHA-256) column for uniqueness.
- **Queued notifications:** Always use `::fromModel()` static factory — never `new NotificationClass($model)`.
- **PHPStan + Carbon dates:** Columns with `'date'` cast typed `string` in PHPDocs. Use `(string) $model->date_col` not `->toDateString()`.
- **`asset_code`** is set by a PostgreSQL trigger — never set it in PHP or factories.
- **`VendorFulfillmentService`** lives in `app/Domains/AP/Services/` (not Procurement).
- **Stock updates:** Always use `StockService::receive()` — never direct model increment (bypasses `stock_ledger_entries` audit trail).
- **pnpm workspace:** `pnpm-lock.yaml` at repo root, not `frontend/`. Run `pnpm install` from root.

## Testing Gotchas

- **Always PostgreSQL** (`ogami_erp_test`) — config locked in `phpunit.xml` with `force="true"`. Never create `.env.testing`.
- **Seed RBAC first:** `$this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])` before creating users.
- **Payroll tests:** Use `PayrollTestHelper` (strips generated columns, handles aliases). Golden suite has 24 canonical scenarios — don't change expected values without justification.
- **Custom Pest expectations:** `->toBeValidationError('field')`, `->toBeDomainError('ERROR_CODE')` (defined in `tests/Pest.php`).

## Frontend Gotchas

- **Only 2 Zustand stores** (`authStore`, `uiStore`) — never create more. Server state goes in TanStack Query.
- **API write cooldown:** `api.ts` silently aborts duplicate POST/PUT/PATCH/DELETE to the same URL within 1500ms.
- **`authStore.hasPermission()`** is strict — `admin` only has `system.*`, not HR/payroll implicitly.
- **Paginated responses:** Use `.meta` (not `.pagination`): `{ data: T[], meta: { current_page, last_page, per_page, total } }`.
- **URL params are ULIDs:** `useParams<{ ulid: string }>()` — never `id`.
- **API client:** `import api from '@/lib/api'` (default export).

## Architecture Rules (enforced by Arch tests)

| Rule | Constraint |
|------|------------|
| ARCH-001 | No `DB::` in controllers |
| ARCH-002 | Domain services implement `ServiceContract` |
| ARCH-003 | Exceptions extend `DomainException` |
| ARCH-004 | Value objects are `final readonly class` |
| ARCH-005 | No `dd()`/`dump()`/`var_dump()` in `app/` |
| ARCH-006 | `Shared\Contracts` contains only interfaces |

## Auth & SoD

- Session-cookie auth (Sanctum) — no JWT, no localStorage tokens.
- SoD: Same user who created a record cannot approve it.
- Department scope auto-applied; only `admin`/`super_admin`/`executive`/`vice_president` bypass.
- Vendor portal `orderDetail` returns raw model JSON (no Resource) — all `$fillable` exposed.
- Vendor propose-changes `items`: use `['present','array']` not `['required','array','min:1']`.

## Reference Files

| File | Purpose |
|------|---------|
| `CLAUDE.md` | Domain-specific gotchas, seeder order, PO status sequence |
| `.github/instructions/*.instructions.md` | Copilot rules per concern (backend, frontend, migrations, tests, fixed-assets) |
| `.agents/skills/` | Specialized skills: payroll-debugger, migration-writer, code-reviewer, budget-planner |
