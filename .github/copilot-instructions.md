# Ogami ERP — Copilot Workspace Instructions

> Full project documentation: [AGENTS.md](../AGENTS.md)
> Backend rules: [.github/instructions/backend.instructions.md](instructions/backend.instructions.md) (`applyTo: app/**`)
> Frontend rules: [.github/instructions/frontend.instructions.md](instructions/frontend.instructions.md) (`applyTo: frontend/src/**`)
> Test rules: [.github/instructions/tests.instructions.md](instructions/tests.instructions.md) (`applyTo: tests/**`)
> Migration rules: [.github/instructions/migrations.instructions.md](instructions/migrations.instructions.md) (`applyTo: database/migrations/**`)

## Project at a Glance

**Ogami ERP** — Manufacturing ERP for Philippine businesses. **Laravel 11 + React 18 SPA + PostgreSQL 16.**
18 domain modules under `app/Domains/`, 25 API route files under `routes/api/v1/`.

```
app/Domains/<Domain>/    Models/ Services/ Policies/ StateMachines/ Pipeline/
app/Http/Controllers/<Domain>/   # thin; no DB logic
app/Shared/ValueObjects/         # Money, Minutes, PayPeriod, DateRange, …
app/Shared/Exceptions/           # DomainException base + 13 specific exceptions
frontend/src/
  hooks/          # TanStack Query wrappers — one file per domain
  pages/<domain>/ # page components
  pages/vendor-portal/ # vendor self-service portal
  pages/client-portal/ # client ticket portal
  schemas/        # Zod schemas (9 of 18 domains)
  types/          # TypeScript interfaces (incl. crm.ts)
  lib/api.ts      # Axios default export, withCredentials, 1500ms write cooldown
tests/
  Unit/ Feature/ Integration/ Arch/ Support/PayrollTestHelper.php
```

## Dev Commands

```bash
npm run dev             # start everything (PG, Redis, Laravel:8000, Vite:5173, queue)
npm run dev:minimal     # skip queue + Reverb
php artisan migrate:fresh --seed   # reset DB with all 25 seeders
```

## Test Commands

```bash
./vendor/bin/pest                          # all suites
./vendor/bin/pest --testsuite=Unit         # value objects, payroll golden (no DB)
./vendor/bin/pest --testsuite=Feature      # HTTP tests (RefreshDatabase)
./vendor/bin/pest --testsuite=Integration  # cross-domain (PayrollToGL, APToGL)
./vendor/bin/pest --testsuite=Arch         # ARCH-001–006 structural rules
./vendor/bin/phpstan analyse               # Larastan level 5
./vendor/bin/pint                          # code style fixer
cd frontend && pnpm typecheck && pnpm lint
```

## Non-Negotiable Code Rules

### PHP
- `declare(strict_types=1)` in **every** file
- Domain services: `final class` + `implements ServiceContract` + wrap mutations in `DB::transaction()`
- Controllers: `final class`, **zero DB/business logic**, delegate to service, return `JsonResource`
- Value objects: `final readonly class` in `app/Shared/ValueObjects/`
- Custom exceptions: must extend `DomainException(message, errorCode, httpStatus)` — all 3 args required
- Currency: **always `Money` value object**, never `float`. `₱25,000 = 2_500_000 centavos`
- Never: `dd()`, `dump()`, `var_dump()` in `app/` (ARCH-005)

### TypeScript / React
- `import api from '@/lib/api'` (default export, not named)
- Only 2 Zustand stores: `authStore.ts`, `uiStore.ts` — do not add more
- All routes lazy-loaded in `frontend/src/router/index.tsx` only
- Paginated response: `.meta.total` / `.meta.current_page` — **not** `.pagination`
- URL params are **ULID strings**, not integer IDs

## Critical Gotchas

### PostgreSQL Generated Columns
`daily_rate` and `hourly_rate` on `employees` are `GENERATED ALWAYS AS STORED`. **Never include them in INSERT/UPDATE or factory state.** Use `PayrollTestHelper::normalizeOverrides()` which strips them automatically.

### Money Is Always Centavos
`Money::fromFloat(25000.00)` → `2_500_000`. Assertions or factory values must use centavos: `'basic_monthly_rate' => 2_500_000`.

### Department Scoping
`dept_scope` middleware limits queries to the auth user's department. To bypass (e.g., HR listing all employees): `Employee::withoutDepartmentScope()`. Only `admin`, `super_admin`, `executive`, `vice_president` bypass automatically — `manager` does **not**.

### Test DB Is Locked
DB config is in `phpunit.xml` with `force="true"`. **Never create `.env.testing`** — it has no effect and confuses setup. Always run tests against `ogami_erp_test` (PostgreSQL).

### SoD Enforcement
Same user who created a record cannot approve it. Enforced in policy + middleware backend, and `useSodCheck(createdById)` frontend. Only `admin`/`super_admin` bypass — `manager` can be blocked.

### Admin Is NOT Super-Admin
`authStore.hasPermission()` is strict. `admin` has only `system.*` permissions. It does **not** implicitly hold HR, payroll, or other domain permissions.

### api.ts Write Cooldown
The Axios instance silently drops duplicate POST/PUT/PATCH/DELETE to the same URL within 1500 ms. Do not fire the same mutation twice in quick succession in tests or scripts.

## Architecture Rules (auto-enforced in Arch suite)

| Rule | Constraint |
|------|-----------|
| ARCH-001 | Controllers: no `DB::` calls |
| ARCH-002 | Domain services implement `ServiceContract` |
| ARCH-003 | Custom exceptions extend `DomainException` |
| ARCH-004 | Value objects are `final readonly class` |
| ARCH-005 | No debug dumps in `app/` |
| ARCH-006 | `Shared\Contracts` contains interfaces only |

## Key Reference Files

| Purpose | File |
|---------|------|
| Example domain service | `app/Domains/HR/Services/EmployeeService.php` |
| Example controller | `app/Http/Controllers/Leave/LeaveRequestController.php` |
| Money value object | `app/Shared/ValueObjects/Money.php` |
| DomainException base | `app/Shared/Exceptions/DomainException.php` |
| Employee state machine | `app/Domains/HR/StateMachines/EmployeeStateMachine.php` |
| Payroll pipeline context | `app/Domains/Payroll/Services/PayrollComputationContext.php` |
| Payroll test helper | `tests/Support/PayrollTestHelper.php` |
| Payroll golden suite | `tests/Unit/Payroll/GoldenSuiteTest.php` |
| API Axios instance | `frontend/src/lib/api.ts` |
| Permissions constants | `frontend/src/lib/permissions.ts` |
| Frontend router | `frontend/src/router/index.tsx` |
| Leave hook example | `frontend/src/hooks/useLeave.ts` |

## Seeder Order (25 seeders — must run in this sequence)

1. Rate tables: SSS → PhilHealth → Pag-IBIG → TRAIN tax → OT multipliers → Holiday calendar → Min wage
2. RBAC: `RolePermissionSeeder` → `SampleAccountsSeeder`
3. HR reference: SalaryGrade → LeaveType → LoanType → ShiftSchedule
4. Accounting: `ChartOfAccountsSeeder`
5. Org: FiscalPeriod → DepartmentPosition → DeptPermissionProfile → DeptPermissionTemplate
6. Sample data: SampleData → ManufacturingEmployee → Fleet → LeaveBalance
7. System config: SystemSettings → NewModules

## Payroll Pipeline (17 steps in order)

```
Step01Snapshots → Step02PeriodMeta → Step03AttendanceSummary → Step04LoadYtd →
Step05BasicPay → Step06OvertimePay → Step07HolidayPay → Step08NightDiff →
Step09GrossPay → Step10Sss → Step11PhilHealth → Step12Pagibig →
Step13TaxableIncome → Step14WithholdingTax → Step15LoanDeductions →
Step16OtherDeductions → Step17NetPay
```

Each step: `final class`, invokable `(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext`.

## Known Domain Exceptions

All in `app/Shared/Exceptions/` (all extend `DomainException`):
`AuthorizationException` · `ContributionTableNotFoundException` · `CreditLimitExceededException` · `DuplicatePayrollRunException` · `InsufficientLeaveBalanceException` · `InvalidStateTransitionException` · `LockedPeriodException` · `NegativeNetPayException` · `SodViolationException` · `TaxTableNotFoundException` · `UnbalancedJournalEntryException` · `ValidationException`

## Available Prompts

Use `/new-domain <DomainName>` to scaffold a complete domain module (migration, model, service, controller, policy, route, and optional frontend hook).

Use `/run-domain-tests <Domain|all>` to run the correct Pest suites for a domain (Feature + Integration + golden suite for Payroll).

## Available Skills

| Skill | When to invoke |
|-------|---------------|
| `payroll-debugger` | Pipeline step produces wrong amounts, net pay is negative, deductions off |
| `migration-writer` | Creating or altering tables — PgSQL patterns (generated columns, CHECK constraints, SHA-256 hashes) |
| `code-reviewer` | Security audit, performance review, PR review |
| `debugger` | Systematic root-cause analysis for errors/crashes |
