# Ogami ERP — Agent Documentation

## Build Commands

```bash
# Start dev services (PG, Redis, Laravel:8000, Vite:5173, queue)
npm run dev              # Full dev stack
npm run dev:minimal      # Without queue/Reverb

# Backend
php artisan serve
php artisan queue:work

# Frontend (in frontend/ directory)
pnpm dev
```

## Test Commands

```bash
# Backend (Pest) — run single test with path
./vendor/bin/pest tests/Unit/FooTest.php
./vendor/bin/pest --filter=methodName
./vendor/bin/pest --testsuite=Unit        # Value objects, payroll golden
./vendor/bin/pest --testsuite=Feature     # HTTP endpoints
./vendor/bin/pest --testsuite=Integration # Cross-domain workflows
./vendor/bin/pest --testsuite=Arch        # Structural rules (ARCH-001–006)

# Frontend (in frontend/)
pnpm test              # Vitest single run
pnpm test:watch        # Vitest watch mode
pnpm test -- --reporter=verbose --run tests/Foo.test.tsx  # Single test
pnpm e2e               # Playwright
pnpm e2e:ui            # Playwright with UI

# Static Analysis
./vendor/bin/phpstan analyse
./vendor/bin/pint
pnpm lint
pnpm typecheck         # in frontend/
```

## Database Commands

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=RolePermissionSeeder
```

## Code Style — PHP

- **File header:** `<?php declare(strict_types=1);`
- **Classes:** Domain services `final class` implementing `ServiceContract`; value objects `final readonly class`
- **Money:** Never `float`. Use `Money` value object: `₱25,000 = 2_500_000 centavos`. `Money::fromFloat(25000.00)` or `Money::fromCentavos(2_500_000)`
- **Exceptions:** Extend `DomainException` with 3 mandatory args: `message`, `errorCode`, `httpStatus`
- **No debug:** `dd()`, `dump()`, `var_dump()`, `ray()` prohibited in `app/`
- **Transactions:** All writes in services wrapped in `DB::transaction()`
- **Injection only:** Never `app()` or `resolve()` in services

## Code Style — TypeScript/React

- **Imports:** Use `@/` alias. `import api from '@/lib/api'` (default export)
- **Components:** Function declarations with explicit return types: `function Foo(): JSX.Element`
- **Zod:** `z.coerce.number()` for numeric IDs and money inputs
- **ESLint:** `@typescript-eslint/no-unused-vars` is error — prefix unused with `_`

## Architecture Patterns

### Domain Service
```php
final class FooService implements ServiceContract
{
    public function __construct(private readonly Dep $dep) {}

    public function create(array $data): Foo
    {
        return DB::transaction(function () use ($data): Foo {
            // Business logic
        });
    }
}
```

### Controller
```php
final class FooController extends Controller
{
    public function __construct(private readonly FooService $service) {}

    public function store(StoreFooRequest $request): JsonResponse
    {
        $this->authorize('create', Foo::class);
        $result = $this->service->create($request->validated());
        return (new FooResource($result))->response()->setStatusCode(201);
    }
}
```

### TanStack Query Hook
```typescript
export function useLeaveRequests(filters: LeaveFilters = {}) {
    return useQuery({
        queryKey: ['leave-requests', filters],
        queryFn: () => api.get('/leave/requests', { params: filters })
    });
}

export function useSubmitLeaveRequest() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: (data) => api.post('/leave/requests', data),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['leave-requests'] })
    });
}
```

## Database Conventions

- **ULID:** Every domain table has `$table->ulid('ulid')->unique()`
- **Money:** `unsignedBigInteger` for centavos (never `decimal`/`float`)
- **Enums:** `string` + PostgreSQL CHECK constraint (never `$table->enum()`)
- **Generated columns:** Add via `DB::statement()` after `Schema::create()`
- **Government IDs:** Store encrypted + SHA-256 hash for uniqueness

## Testing Guidelines

- **Always PostgreSQL** (`ogami_erp_test`) — never SQLite. Config locked in `phpunit.xml`
- **Setup:** Always seed `RolePermissionSeeder` before creating users
- **Payroll:** Use `PayrollTestHelper` instead of raw factories. Monetary values in centavos
- **Custom expectations:** `->toBeValidationError('field')`, `->toBeDomainError('CODE')`

## Architecture Rules (ARCH-001–006)

| Rule | Constraint |
|------|------------|
| ARCH-001 | No `DB::` in controllers |
| ARCH-002 | Domain services implement `ServiceContract` |
| ARCH-003 | Exceptions extend `DomainException` |
| ARCH-004 | Value objects are `final readonly class` |
| ARCH-005 | No `dd()`/`dump()`/`var_dump()` in `app/` |
| ARCH-006 | `Shared\Contracts` contains only interfaces |

## Security & Auth

- Session-cookie auth (Sanctum) — no JWT, no tokens in localStorage
- RBAC roles: `admin` → `executive` → `vice_president` → `manager` → `officer` → `head` → `staff`
- SoD: Same user cannot create AND approve
- Department scope: `admin`, `super_admin`, `executive`, `vice_president` bypass; others need explicit pivot
- `authStore.hasPermission()` is strict — `admin` only has `system.*`

## Project Structure

```
app/Domains/<Domain>/       # Models, Services, Policies, StateMachines
app/Shared/ValueObjects/    # Money, Minutes, PayPeriod, DateRange, EmployeeCode
app/Shared/Exceptions/      # DomainException
app/Shared/Contracts/       # ServiceContract, BusinessRule
frontend/src/
  hooks/                    # TanStack Query per domain
  pages/<domain>/           # Page components
  schemas/<domain>.ts       # Zod schemas
  types/<domain>.ts         # TypeScript interfaces
  stores/                   # Zustand: authStore.ts, uiStore.ts only
tests/
  Unit/                     # Value objects, payroll golden suite
  Feature/                  # HTTP endpoints
  Integration/              # Cross-domain workflows
  Arch/                     # Structural constraints
```

## Common Pitfalls

1. `Money` throws on negative — guard before subtracting
2. `DomainException` requires all 3 constructor args
3. Never include `daily_rate`/`hourly_rate` (GENERATED columns) in factories
4. URL params use ULID: `useParams<{ ulid: string }>()` not `id`
5. API write cooldown aborts duplicates within 1500ms
6. Models using `HasPublicUlid` must also use `SoftDeletes`
