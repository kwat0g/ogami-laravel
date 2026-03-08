# Ogami ERP — Agent Documentation

This document provides essential information for AI coding agents working on the Ogami ERP project.

## Project Overview

**Ogami ERP** is a comprehensive Enterprise Resource Planning system for manufacturing businesses in the Philippines (HR, payroll, accounting, production, QC, procurement, ISO compliance). Laravel 11 backend + React 18 SPA, backed by PostgreSQL 16.

### Domains (17 total — all under `app/Domains/`)

| Domain | Key Models | Route file |
|--------|------------|------------|
| **HR** | `Employee`, `Department`, `Position`, `SalaryGrade` | `routes/api/v1/hr.php` |
| **Attendance** | `AttendanceLog`, `ShiftSchedule`, `OvertimeRequest` | `routes/api/v1/attendance.php` |
| **Leave** | `LeaveRequest`, `LeaveBalance`, `LeaveType` | `routes/api/v1/leave.php` |
| **Payroll** | `PayrollRun`, `PayrollDetail`, `PayrollAdjustment` | `routes/api/v1/payroll.php` |
| **Loan** | `Loan`, `LoanAmortizationSchedule` | `routes/api/v1/loans.php` |
| **Accounting** | `ChartOfAccount`, `JournalEntry`, `JournalEntryLine`, `BankAccount` | `routes/api/v1/accounting.php` |
| **AP** | `Vendor`, `VendorInvoice`, `VendorPayment` | `routes/api/v1/finance.php` |
| **AR** | `Customer`, `CustomerInvoice`, `CustomerPayment` | `routes/api/v1/ar.php` |
| **Tax** | `VatLedger` | `routes/api/v1/tax.php` |
| **Inventory** | `ItemMaster`, `StockLedger`, `MaterialRequisition` | `routes/api/v1/inventory.php` |
| **Procurement** | `PurchaseRequest`, `PurchaseOrder`, `GoodsReceipt` | `routes/api/v1/procurement.php` |
| **Production** | `ProductionOrder`, `BillOfMaterials`, `DeliverySchedule` | `routes/api/v1/production.php` |
| **QC** | `Inspection`, `NonConformanceReport`, `CapaAction` | `routes/api/v1/qc.php` |
| **Maintenance** | `Equipment`, `MaintenanceWorkOrder`, `PmSchedule` | `routes/api/v1/maintenance.php` |
| **Mold** | `MoldMaster`, `MoldShotLog` | `routes/api/v1/mold.php` |
| **Delivery** | `Shipment`, `DeliveryReceipt`, `Vehicle` | `routes/api/v1/delivery.php` |
| **ISO** | `ControlledDocument`, `InternalAudit`, `AuditFinding` | `routes/api/v1/iso.php` |

## Technology Stack

**Backend**: Laravel 11 / PHP 8.2+ · PostgreSQL 16 (stored computed columns, CHECK constraints via `DB::statement()`) · Redis 7 · Laravel Sanctum (session-cookie, no JWT) · Spatie RBAC · Spatie Media Library · Owen-it Auditing · Laravel Horizon + Reverb + Pulse · barryvdh/dompdf · maatwebsite/excel · spatie/laravel-backup · bacon/bacon-qr-code

**Frontend**: React 18 + TypeScript · Vite 6 · pnpm 10 · TanStack Query v5 + React Hook Form + Zod · TanStack Table · Zustand · Recharts · Lucide React · Sonner · Tailwind CSS 3

**Testing**: Pest PHP 3 · Vitest + RTL · Playwright E2E · PHPStan/Larastan level 5 · Laravel Pint

**Infra**: Docker Compose · Nginx+PHP-FPM (prod) · pnpm (frontend) · Composer (PHP)

## Project Structure

```
app/
  Domains/<Domain>/          # 17 domain modules
    Models/                  # Eloquent models
    Services/                # Domain services (implement ServiceContract)
    Policies/                # Laravel policies
    StateMachines/           # State machines (HR, Payroll)
    Pipeline/                # Payroll computation steps
    DataTransferObjects/     # DTOs (some domains)
  Http/
    Controllers/<Domain>/    # Thin, final controllers — no business logic
    Requests/                # FormRequest validation classes
    Resources/               # API response transformers (always return data wrapped)
  Shared/
    Contracts/ServiceContract.php   # Marker interface all domain services must implement
    ValueObjects/            # Money, Minutes, PayPeriod, DateRange, EmployeeCode, etc.
    Exceptions/DomainException.php  # Base for all custom exceptions
    Traits/                  # Reusable model traits
  Jobs/<Domain>/             # Queueable jobs (payroll batch, leave accrual, etc.)
database/migrations/         # 100+ migrations — never SQLite-compatible (PgSQL features)
database/seeders/            # 25 seeders with strict ordering (see Seeder Order below)
frontend/src/
  hooks/                     # TanStack Query wrappers (one file per domain)
  pages/<domain>/            # Page components
  schemas/<domain>.ts        # Zod validation schemas
  types/<domain>.ts          # TypeScript interfaces
  lib/api.ts                 # Axios instance (baseURL /api/v1, withCredentials)
  lib/permissions.ts         # Typed Spatie permission constants
routes/api/v1/               # 23 domain route files
tests/
  Feature/<Domain>/          # HTTP endpoint tests
  Unit/                      # Value objects, payroll computation (golden suite)
  Integration/               # Cross-domain workflows (PayrollToGL, APToGL)
  Arch/                      # ARCH-001–006 structural constraints
  Support/PayrollTestHelper.php    # Payroll test factories
```

## Code Patterns

### Domain Service
`final class` + implements `ServiceContract` + constructor-inject dependencies + wrap mutations in `DB::transaction()`.  
Reference: `app/Domains/HR/Services/EmployeeService.php`

```php
final class EmployeeService implements ServiceContract
{
    public function __construct(private readonly EmployeeStateMachine $stateMachine) {}

    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data): Employee { ... });
    }
}
```

### Controller
`final class`, no DB/business logic, delegate to service, return API Resource.  
Reference: `app/Http/Controllers/Leave/LeaveRequestController.php`

```php
final class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $service) {}

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $this->authorize('create', [LeaveRequest::class, $employee]);
        $result = $this->service->submit($employee, $request->validated(), (int) $request->user()->id);
        return (new LeaveRequestResource($result))->response()->setStatusCode(201);
    }
}
```

Multi-step workflow actions are **named methods** (`headApprove`, `managerCheck`, `gaProcess`) not a generic action.

### Value Objects
`final readonly class` in `app/Shared/ValueObjects/`.  
**`Money`**: stores **centavos (integer)**, never float. Factory: `Money::fromFloat(25000.00)` → `2_500_000` centavos. All arithmetic is immutable and rounds with `PHP_ROUND_HALF_UP`.  
`₱25,000 = 2_500_000 centavos` — pay attention in tests (`'basic_monthly_rate' => 2_500_000`).  
Reference: `app/Shared/ValueObjects/Money.php`

### State Machines
Dedicated class with a `TRANSITIONS` constant. Never scatter status string comparisons in controllers.  
- `app/Domains/HR/StateMachines/EmployeeStateMachine.php` — `draft→active→on_leave|suspended→resigned|terminated`
- `app/Domains/Payroll/StateMachines/PayrollRunStateMachine.php` — 14-state workflow with legacy alias compatibility

### Payroll Pipeline — 17 Steps
Uses Laravel `Pipeline::through([Step01...::class, ..., Step17...::class])`. Each step is an invokable with signature:

```php
public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
{
    // mutate $ctx fields
    return $next($ctx);
}
```

Files in `app/Domains/Payroll/Pipeline/Step01SnapshotsStep.php` through `Step17NetPayStep.php`.  
Shared mutable state object: `app/Domains/Payroll/Services/PayrollComputationContext.php`.

### PostgreSQL-Specific Migration Gotchas
1. **Stored computed columns** — `daily_rate` and `hourly_rate` on `employees` table are `GENERATED ALWAYS AS STORED`. **Never include them in factory/test inserts** or PostgreSQL will error. Skip them explicitly in test helpers (see `tests/Support/PayrollTestHelper.php`).
2. **Raw CHECK constraints** added via `DB::statement("ALTER TABLE ... ADD CONSTRAINT ...")`.
3. **Government ID encryption** — raw values encrypted + SHA-256 hash columns for uniqueness (`tin_hash`, `sss_no_hash`, etc.).

### Route Conventions
All routes require `auth:sanctum`. Domain middleware applied at group level (`dept_scope`, `permission:xxx`).  
Custom workflow actions added beside `apiResource`:

```php
Route::apiResource('employees', EmployeeController::class);
Route::post('employees/{employee}/transition', [EmployeeController::class, 'transition']);
```

Inline closures are acceptable for simple reference/lookup endpoints (salary grades, departments list).  
Reference: `routes/api/v1/hr.php`

### Frontend Hooks
One file per domain in `frontend/src/hooks/`. `queryKey` always includes filters object.

```typescript
export function useLeaveRequests(filters: LeaveFilters = {}) {
  return useQuery({ queryKey: ['leave-requests', filters], queryFn: ... })
}
export function useSubmitLeaveRequest() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: ..., onSuccess: () => qc.invalidateQueries({ queryKey: ['leave-requests'] }) })
}
```

Reference: `frontend/src/hooks/useLeave.ts`

**Paginated response shape**: `{ data: T[], meta: { current_page, last_page, per_page, total } }` — the pagination wrapper is `.meta`, **not** `.pagination`.

**`api.ts` write cooldown**: The Axios instance in `frontend/src/lib/api.ts` silently aborts duplicate write calls (POST/PUT/PATCH/DELETE) to the same URL within 800 ms. Do not fire the same mutation URL twice in quick succession in tests or scripts.

**Global QueryClient defaults**: `staleTime: 30_000`, `refetchOnWindowFocus: false`; API errors with `error_code` never retry.

### Frontend URL Identifiers
Frontend URL params use **ULID** strings (not integer IDs): `useParams<{ ulid: string }>()`.

### Zod Schemas
`z.coerce.number()` for all numeric IDs and monetary inputs. All schemas in `frontend/src/schemas/`.  
Derive TypeScript types: `type EmployeeFormValues = z.infer<typeof employeeFormSchema>`.  
Only 9 of the 17 domains have schema files; other domains use inline Zod or plain TypeScript types.

### Frontend Router & Stores
- All routes are lazy-loaded in a single file `frontend/src/router/index.tsx` with a local `RequirePermission` guard component.
- Only 2 Zustand stores exist: `authStore.ts` (auth/permissions) and `uiStore.ts` (UI state). Do not add new global stores unless necessary.
- **AP/Vendor pages live in `pages/accounting/`**, not `pages/ap/`.
- ESLint rule `@typescript-eslint/no-unused-vars` is `error`; prefix intentionally unused variables/args with `_` (e.g., `_event`).

### SoD (Segregation of Duties)
Enforced backend (middleware + policy) **and** frontend via `useSodCheck(createdById)`.  
Same user who created a record cannot approve/activate it.  
Only `admin` and `super_admin` roles bypass SoD — the `manager` role **can** be blocked.

## Build and Run Commands

```bash
# Start all dev services (PG, Redis, Laravel:8000, Vite:5173, queue)
npm run dev          # OR: bash dev.sh
npm run dev:minimal  # Without queue/Reverb
npm run dev:full     # With Reverb WebSocket server

# Database
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed  # seed only
```

## Testing Commands

```bash
# Backend (Pest)
./vendor/bin/pest                         # all suites
./vendor/bin/pest --testsuite=Unit        # value objects, payroll golden suite
./vendor/bin/pest --testsuite=Feature     # HTTP endpoint tests
./vendor/bin/pest --testsuite=Integration # cross-domain workflows
./vendor/bin/pest --testsuite=Arch        # ARCH-001–006 structural rules

# Frontend
cd frontend
pnpm test             # Vitest unit tests
pnpm typecheck        # tsc --noEmit
pnpm lint             # ESLint
pnpm e2e              # Playwright

# Static analysis
./vendor/bin/phpstan analyse  # Larastan level 5
./vendor/bin/pint             # Code style fixer
```

## Code Style Guidelines

### PHP
1. `declare(strict_types=1)` at top of every file
2. Domain services and models: `final class`
3. Value objects: `final readonly class`
4. All domain services implement `ServiceContract` (marker interface only, no methods)
5. Controllers delegate everything to services — no `DB::` calls, no business logic
6. All custom exceptions extend `App\Shared\Exceptions\DomainException`
7. **No** `dd()`, `dump()`, `var_dump()`, `ray()` — enforced by ARCH-005
8. **No** float for currency — always use `Money` value object (see `app/Shared/ValueObjects/Money.php`)

### TypeScript/React
1. Strict TypeScript; all components are `function` declarations with explicit return types
2. Server state via TanStack Query hooks in `frontend/src/hooks/`; global state via Zustand
3. Forms: React Hook Form + Zod schema; derive types with `z.infer<typeof schema>`
4. Always use `@/` path alias for imports from `src/`
5. `z.coerce.number()` for all numeric IDs and monetary inputs

## Testing Strategy

### Test Setup Pattern (Feature/Integration)
Always seed RBAC + domain rate tables before tests:
```php
beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder'])->assertExitCode(0);
    $this->hrManager = User::factory()->create();
    $this->hrManager->assignRole('hr_manager');
});
```

### Custom Expectations (in `tests/Pest.php`)
```php
->toBeValidationError('field_name')  // checks error_code === 'VALIDATION_ERROR' + errors.field
->toBeDomainError('ERROR_CODE')      // checks error_code matches
```

### Key Conventions
- **Always use PostgreSQL** test DB (`ogami_erp_test`) — never SQLite (stored columns, triggers)
- **No `.env.testing`** — test DB config is locked inside `phpunit.xml` with `force="true"`, which wins over shell env vars. Never create a `.env.testing` file.
- Monetary values in centavos: `'basic_monthly_rate' => 2_500_000` (= ₱25,000)
- **Factory key aliases**: `hired_at` → maps to `date_hired`; `resigned_at` → maps to `separation_date` inside `PayrollTestHelper::normalizeOverrides()`. Use the helper, not raw factory calls.
- Payroll golden suite: 24 canonical scenarios in `tests/Unit/Payroll/GoldenSuiteTest.php`
- Reference payroll test factories: `tests/Support/PayrollTestHelper.php`
- `RefreshDatabase` applied to Feature + Integration; pure Unit tests don't need it

### Architecture Rules (ARCH-001–006)
| Rule | Constraint |
|------|-----------|
| ARCH-001 | Controllers: no `DB::` or database calls |
| ARCH-002 | Domain services must implement `ServiceContract` |
| ARCH-003 | Custom exceptions must extend `DomainException` |
| ARCH-004 | Value objects must be `final readonly` |
| ARCH-005 | No `dd()`/`dump()`/`var_dump()` in `app/` |
| ARCH-006 | `Shared\Contracts` namespace: interfaces only |

### Seeder Order (25 seeders — strict dependency order)
1. Rate tables: `SssContributionTableSeeder`, `PhilhealthPremiumTableSeeder`, `PagibigContributionTableSeeder`, `TrainTaxBracketSeeder`, `OvertimeMultiplierSeeder`, `HolidayCalendarSeeder`, `MinimumWageRateSeeder`  
2. RBAC: `RolePermissionSeeder` → `SampleAccountsSeeder`  
3. HR reference: `SalaryGradeSeeder`, `LeaveTypeSeeder`, `LoanTypeSeeder`, `ShiftScheduleSeeder`  
4. Accounting: `ChartOfAccountsSeeder`  
5. Org structure: `FiscalPeriodSeeder`, `DepartmentPositionSeeder`, `DepartmentPermissionProfileSeeder`, `DepartmentPermissionTemplateSeeder`  
6. Transactional sample data: `SampleDataSeeder`, `ManufacturingEmployeeSeeder`, `FleetSeeder`, `LeaveBalanceSeeder`  
7. System config: `SystemSettingsSeeder`, `NewModulesSeeder`  

## Security Considerations

### Authentication & Authorization
- **Session-cookie auth** (Sanctum) — no JWT, no tokens in localStorage
- **RBAC**: `spatie/laravel-permission` — roles: `admin → executive → vice_president → manager → officer → head → staff`
- **SoD**: Same user cannot create AND approve the same record — enforced in middleware/policy AND frontend (`useSodCheck`)
- **Department scoping**: `dept_scope` middleware applies a global query scope restricting data to user's department
- **Rate limiting**: 120 reads / 60 writes per minute on all API routes; separate brute-force throttle on auth

### Data Protection
- Government IDs (SSS, TIN, PhilHealth, Pag-IBIG) are **encrypted** at model layer
- **SHA-256 hash columns** (`tin_hash`, `sss_no_hash`) used for uniqueness constraints without storing raw values
- Full audit trail via `owen-it/laravel-auditing` on all sensitive models

## Domain Architecture Patterns

### Payroll Computation Pipeline (17 Steps)
```
Step01SnapshotsStep → Step02PeriodMetaStep → Step03AttendanceSummaryStep →
Step04LoadYtdStep → Step05BasicPayStep → Step06OvertimePayStep →
Step07HolidayPayStep → Step08NightDiffStep → Step09GrossPayStep →
Step10SssStep → Step11PhilHealthStep → Step12PagibigStep →
Step13TaxableIncomeStep → Step14WithholdingTaxStep → Step15LoanDeductionsStep →
Step16OtherDeductionsStep → Step17NetPayStep
```

Steps are in `app/Domains/Payroll/Pipeline/`. Each is `final class` with signature:
```php
public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
```
Shared mutable state: `app/Domains/Payroll/Services/PayrollComputationContext.php`

### State Machines
- `app/Domains/HR/StateMachines/EmployeeStateMachine.php`  
  `draft → active → on_leave|suspended → resigned|terminated`
- `app/Domains/Payroll/StateMachines/PayrollRunStateMachine.php`  
  14-state workflow: `DRAFT → SCOPE_SET → PRE_RUN_CHECKED → PROCESSING → COMPUTED → REVIEW → SUBMITTED → HR_APPROVED → ACCTG_APPROVED → DISBURSED → PUBLISHED` (+ `RETURNED`/`REJECTED → DRAFT`)  
  Has legacy lowercase alias compatibility layer.

### Value Objects (all in `app/Shared/ValueObjects/`)
| Class | Key Detail |
|-------|-----------|
| `Money` | Integer centavos. `fromFloat(25000.00)` = `2_500_000`. Immutable arithmetic, `PHP_ROUND_HALF_UP` |
| `Minutes` | Whole integer minutes. `toHours()` → float |
| `PayPeriod` | Semi-monthly. `periodNumber` must be 1 or 2. Factories: `firstHalf()`, `secondHalf()` |
| `DateRange` | Start/end date encapsulation |
| `OvertimeMultiplier` | OT rate calculations |
| `EmployeeCode` | Code generation (`EMP-YYYY-NNN`) |
| `WorkingDays` | Working day count calculations |

### Job Queues
Defined queues: `payroll`, `computations`, `notifications`, `default`  
Jobs in `app/Jobs/<Domain>/`. Pattern: `final class`, `ShouldQueue`, `Batchable` for payroll.  
Reference: `app/Jobs/Payroll/ProcessPayrollBatch.php`

## API Structure

All routes under `/api/v1/` via `routes/api.php` which includes 23 domain route files from `routes/api/v1/`.  
All endpoints require `auth:sanctum`. Write throttle: 60/min; Read throttle: 120/min.

Key prefixes: `hr`, `leave`, `loans`, `attendance`, `payroll`, `accounting`, `ar`, `tax`, `procurement`, `inventory`, `production`, `qc`, `maintenance`, `mold`, `delivery`, `iso`, `reports`, `employee`, `admin`, `notifications`, `dashboard`, `auth`

API responses always use `JsonResource` — data is wrapped: `{ "data": { ... } }` or `{ "data": [...] }`.  
Error responses: `{ "success": false, "error_code": "DOMAIN_ERROR_CODE", "message": "..." }`.

## Deployment

### Docker
```bash
docker-compose up --build           # Build and start all services
docker-compose exec app php artisan migrate
```

### Production Dockerfile stages
1. `base` — PHP 8.3 + extensions
2. `development` — Artisan serve
3. `frontend-builder` — Vite build
4. `production` — Nginx + PHP-FPM + Supervisor

### Key Environment Variables
```bash
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true          # sessions are encrypted
SESSION_COOKIE=ogami_session  # custom cookie name
BROADCAST_CONNECTION=reverb
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000,localhost
DEFAULT_REGION=NCR   # Philippine minimum wage region
APP_TIMEZONE=Asia/Manila
TEST_DB_DATABASE=ogami_erp_test
```

## Troubleshooting

### Common Issues
1. **Storage permissions**: `chmod -R 775 storage bootstrap/cache`
2. **Queue not processing**: Check Horizon at `/horizon`
3. **WebSocket issues**: Verify Reverb running and `REVERB_*` env vars set
4. **DB errors**: Check Docker containers (`docker ps`)

### Logs
- Laravel: `storage/logs/laravel.log`
- Services: `storage/logs/{serve,queue,vite,reverb}.log`

## Common Pitfalls & Gotchas

### PHP / Backend
- **`Money` throws on negative** — `Money::fromCentavos()` and subtraction both throw if the result is negative. Always guard before subtracting.
- **`DomainException` has 3 mandatory args**: `message`, `errorCode`, `httpStatus`. The `httpStatus` parameter is NOT optional.
- **`GENERATED` columns in factories**: `daily_rate` and `hourly_rate` on `employees` are `GENERATED ALWAYS AS STORED`. Never include them in INSERT/UPDATE statements or factory definitions. Use `PayrollTestHelper::normalizeOverrides()` which strips them automatically.
- **`dept_scope` middleware** restricts queries to the authenticated user's department. Services that need to bypass it (e.g., HR managers listing all employees) must call `Employee::withoutDepartmentScope()`.
- **Department access bypass**: only `admin`, `super_admin`, `executive`, `vice_president` automatically bypass department scoping; `manager` and `head` roles require explicit department pivot entries.
- **`authStore.hasPermission` is strict**: `admin` has only `system.*` permissions and does NOT implicitly have HR/payroll permissions. Always check the role matrix in `RolePermissionSeeder`.
- **PHPStan baseline**: `phpstan-baseline.neon` suppresses known false positives at level 5. Do not add `@phpstan-ignore` without checking if it belongs in the baseline.

### Known Domain Exceptions (`app/Shared/Exceptions/`)
13 specific exceptions (all extend `DomainException`): `AuthorizationException`, `ContributionTableNotFoundException`, `CreditLimitExceededException`, `DuplicatePayrollRunException`, `InsufficientLeaveBalanceException`, `InvalidStateTransitionException`, `LockedPeriodException`, `NegativeNetPayException`, `SodViolationException`, `TaxTableNotFoundException`, `UnbalancedJournalEntryException`, `ValidationException`.

### Frontend
- **`api.ts` default import**: hooks import `import api from '@/lib/api'` (default export), not `import { api }`.
- **No legacy auth tokens**: there is no JWT or token in localStorage. Auth state lives entirely in the session cookie and `authStore`.

## Additional Resources

- **System Summary**: `docs/SYSTEM_SUMMARY.md`
- **Testing Guide**: `docs/TESTING_GUIDE.md`
- **Phase Documentation**: `docs/documentations/`
- **Workflow Guides**: `docs/guides/`
