# Ogami ERP — Agent Documentation

## Build Commands

```bash
# Start dev services (PG, Redis, Laravel:8000, Vite:5173, queue)
npm run dev              # Full dev stack via dev.sh
npm run dev:minimal      # Without queue/Reverb
npm run dev:full         # With Reverb WebSocket

# Backend
php artisan serve
php artisan queue:work
php artisan pulse:check  # Performance monitoring

# Frontend (in frontend/ directory)
pnpm dev                 # Start Vite dev server
pnpm build               # Production build
pnpm preview             # Preview production build
```

## Test Commands

```bash
# Backend (Pest PHP) - Run single test with path
./vendor/bin/pest tests/Unit/FooTest.php
./vendor/bin/pest --filter=methodName
./vendor/bin/pest --testsuite=Unit       # Value objects, payroll golden
./vendor/bin/pest --testsuite=Feature    # HTTP endpoints
./vendor/bin/pest --testsuite=Integration # Cross-domain workflows
./vendor/bin/pest --testsuite=Arch       # Structural rules (ARCH-001-006)
./vendor/bin/pest --testsuite=E2E        # Browser tests

# Frontend (in frontend/ directory)
pnpm test                # Vitest single run
pnpm test:watch          # Vitest watch mode
pnpm test -- --reporter=verbose --run tests/Foo.test.tsx # Single test
pnpm e2e                 # Playwright E2E tests
pnpm e2e:ui              # Playwright with UI
pnpm e2e:report          # View test reports
```

## Lint & Format Commands

```bash
# Backend
./vendor/bin/pint        # Laravel Pint (PHP CS Fixer)
./vendor/bin/phpstan analyse # PHPStan static analysis

# Frontend (in frontend/ directory)
pnpm lint                # ESLint
pnpm typecheck           # TypeScript type checking
pnpm format              # Prettier format
pnpm format:check        # Prettier check
```

## Database Commands

```bash
php artisan migrate
php artisan migrate:fresh --seed # Full reset with seeders
php artisan db:seed --class=RolePermissionSeeder
php artisan db:monitor           # Check connection health

# PostgreSQL-specific
php artisan db:anonymize         # PII masking for staging
php artisan db:refresh --dump=latest # Restore from backup

# Performance
php artisan pulse:check          # DB query performance
```

## Code Style — PHP

- **File header:** `<?php declare(strict_types=1);`
- **Classes:** Domain services `final class` implementing `ServiceContract`; value objects `final readonly class`
- **Money:** Never `float`. Use `Money` value object: PHP 25,000 = 2_500_000 centavos. `Money::fromFloat(25000.00)` or `Money::fromCentavos(2_500_000)`
- **Exceptions:** Extend `DomainException` with 3 mandatory args: `message`, `errorCode`, `httpStatus`
- **No debug:** `dd()`, `dump()`, `var_dump()`, `ray()` prohibited in `app/`
- **Transactions:** All writes in services wrapped in `DB::transaction()`
- **Injection only:** Never `app()` or `resolve()` in services
- **Imports:** Group by Laravel, then Spatie/Libraries, then App
- **Quotes:** Single quotes for strings, double quotes only for interpolation
- **Type hints:** Full type hints on all methods and properties

## Code Style — TypeScript/React

- **Imports:** Use `@/` alias. `import api from '@/lib/api'` (default export)
- **Components:** Function declarations with explicit return types: `function Foo(): JSX.Element`
- **Zod:** `z.coerce.number()` for numeric IDs and money inputs
- **ESLint:** `@typescript-eslint/no-unused-vars` is error - prefix unused with `_`
- **Props:** Interface per component, never `any`, no `@ts-ignore`
- **API calls:** Use TanStack Query hooks, never direct axios in components
- **State:** Use Zustand for auth/ui globals, React Query for server state
- **Styling:** Tailwind classes only, no inline styles
- **File names:** PascalCase for components, camelCase for utilities

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
        
        return (new FooResource($result))
            ->response()
            ->setStatusCode(201);
    }
}
```

### API Resource (DTO Pattern)
```php
final class FooResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'amount' => $this->amount->toFloat(), // Money value object
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
```

### TanStack Query Hook (Frontend)
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

### Form & Validation
```typescript
import { z } from 'zod';
import { useForm } from 'react-hook-form';

const schema = z.object({
  amount: z.coerce.number().positive(),
  department_ulid: z.string().uuid(),
});

type FormValues = z.infer<typeof schema>;

function Component() {
  const form = useForm<FormValues>({
    resolver: zodResolver(schema)
  });
}
```

## Database Conventions

- **ULID:** Every domain table has `$table->ulid('ulid')->unique()`
- **Money:** `unsignedBigInteger` for centavos (never `decimal`/`float`)
- **Enums:** `string` + PostgreSQL CHECK constraint (never `$table->enum()`)
- **Generated columns:** Add via `DB::statement()` after `Schema::create()`
- **Government IDs:** Store encrypted + SHA-256 hash for uniqueness
- **Soft deletes:** Always use with `HasPublicUlid` trait
- **Foreign keys:** Format `{table}_id` singular, suffix `_ulid` when referencing ULID

## Testing Guidelines

- **Always PostgreSQL** (`ogami_erp_test`) - never SQLite. Config locked in `phpunit.xml`
- **Setup:** Always seed `RolePermissionSeeder` before creating users
- **Payroll:** Use `PayrollTestHelper` instead of raw factories. Monetary values in centavos
- **Custom expectations:** `->toBeValidationError('field')`, `->toBeDomainError('CODE')`
- **Unit tests:** Test value objects in isolation, no DB
- **Feature tests:** Test single HTTP endpoints with DB
- **Integration tests:** Test cross-domain workflows

### Running Tests
```bash
# Unit test - specific test file
./vendor/bin/pest tests/Unit/Shared/ValueObjects/MoneyTest.php

# Unit test - filter by method
./vendor/bin/pest --filter=it_throws_on_negative_amount

# Feature test - specific endpoint
./vendor/bin/pest tests/Feature/LeaveRequestWorkflowTest.php

# With coverage
./vendor/bin/pest --coverage --min=80
```

## Architecture Rules (ARCH-001-006)

| Rule | Constraint |
|------|------------|
| ARCH-001 | No `DB::` in controllers |
| ARCH-002 | Domain services implement `ServiceContract` |
| ARCH-003 | Exceptions extend `DomainException` |
| ARCH-004 | Value objects are `final readonly class` |
| ARCH-005 | No `dd()`/`dump()`/`var_dump()` in `app/` |
| ARCH-006 | `Shared\Contracts` contains only interfaces |

## Security & Auth

- Session-cookie auth (Sanctum) - no JWT, no tokens in localStorage
- RBAC roles: `admin` → `executive` → `vice_president` → `manager` → `officer` → `head` → `staff`
- SoD: Same user cannot create AND approve
- Department scope: `admin`, `super_admin`, `executive`, `vice_president` bypass; others need explicit pivot
- `authStore.hasPermission()` is strict — `admin` only has `system.*`
- Passwords: Argon2id hashing, never logged
- PII: Encrypted at rest, masked in logs

## API Conventions

- **Base URL:** `/api/v1`
- **Resources:** Plural nouns, ULID-based: `/api/v1/leave-requests/{ulid}`
- **Actions:** POST for create, PATCH for update, DELETE for remove
- **Approval flows:** Separate endpoints: `/approve`, `/reject`, `/submit`
- **Validation errors:** 422 with `{ message: '...', errors: { field: [...] } }`
- **Domain errors:** 400 with `{ error: 'CODE', message: '...' }`
- **Soft deletes:** `DELETE` returns 204, resource marked as deleted
- **Policy checks:** `$this->authorize()` in controllers, not services
- **Rate limiting:** Per-user, per-endpoint via middleware

## Frontend Conventions

### Directory Structure
```
frontend/src/
├── components/          # Shared UI components
│   ├── ui/              # Base components (Button, Input, etc)
│   └── layout/          # Layout components
├── hooks/               # TanStack Query hooks per domain
├── pages/               # Page components by domain
├── schemas/             # Zod validation schemas
├── types/               # TypeScript interfaces
├── stores/              # Zustand stores (auth, ui only)
├── lib/                 # Utilities (api client, etc)
└── router/              # React Router config
```

### Component Patterns
```typescript
interface Props {
  initialData: Foo[];
  onSuccess: () => void;
  className?: string;
}

export function FooList({ initialData, onSuccess, className }: Props): JSX.Element {
  // Component logic
}

export default function FooPage(): JSX.Element {
  return <FooList />;
}
```

### API Client Usage
```typescript
import api from '@/lib/api';

const { data } = await api.get('/items', { params: { status: 'active' } });
const { data } = await api.post('/items', payload);
const { data } = await api.patch(`/items/${ulid}`, updates);
await api.delete(`/items/${ulid}`);
```

## Project Structure

```
app/
├── Domains/<Domain>/           # Domain models, services, policies, state machines
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Domain services implementing ServiceContract
│   ├── Policies/               # Authorization policies
│   └── StateMachines/          # State machine implementations
├── Shared/
│   ├── ValueObjects/          # Money, Minutes, PayPeriod, DateRange, EmployeeCode
│   ├── Exceptions/            # DomainException and subclasses
│   └── Contracts/             # ServiceContract, BusinessRule (interfaces only)
├── Http/                      # Controllers, Middleware, Requests
├── Infrastructure/            # Third-party integrations
├── Events/                    # Laravel events
└── Listeners/                 # Event listeners

frontend/src/
├── components/               # Shared React components
├── hooks/                    # TanStack Query hooks
├── pages/                    # Page components by domain
├── schemas/                  # Zod validation schemas
├── types/                    # TypeScript type definitions
└── stores/                   # Zustand stores

tests/
├── Unit/                     # Value objects, logic
├── Feature/                  # HTTP endpoint tests
├── Integration/              # Cross-domain workflow tests
└── Arch/                     # Architecture constraint tests
```

## Common Pitfalls

1. `Money` throws on negative - guard before subtracting
2. `DomainException` requires all 3 constructor args: `message`, `errorCode`, `httpStatus`
3. Never include `daily_rate`/`hourly_rate` (GENERATED columns) in factories
4. URL params use ULID: `useParams<{ ulid: string }>()` not `id`
5. API write cooldown aborts duplicates within 1500ms
6. Models using `HasPublicUlid` must also use `SoftDeletes`
7. **Never** use `app()` or `resolve()` in services - use constructor injection only
8. **Never** use `dd()`, `dump()`, or `var_dump()` in production code
9. **Never** commit `.env` files or expose secrets in logs
10. **Never** bypass authorization checks - always `$this->authorize()` in controllers
11. **Never** use `float` or `decimal` for money - always `Money` value object
12. Always wrap database writes in `DB::transaction()`
13. Always create/use specific DTOs instead of raw arrays
14. Always validate with custom Request classes, not in controllers
15. Always use ULIDs for public-facing IDs, never auto-increment IDs
