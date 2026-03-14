---
description: "Use when writing or editing PHP files in app/. Covers Ogami ERP backend architecture: domain services, controllers, value objects, exceptions, state machines, and payroll pipeline steps."
applyTo: "app/**"
---
# Ogami ERP — Backend Development Guidelines

## File Header

Every PHP file must begin with:
```php
<?php

declare(strict_types=1);
```

## Domain Services

```php
final class FooService implements ServiceContract
{
    public function __construct(private readonly SomeDependency $dep) {}

    public function create(array $data): Foo
    {
        return DB::transaction(function () use ($data): Foo {
            // ...
        });
    }
}
```

Rules:
- `final class` — no extension allowed
- Implement `App\Shared\Contracts\ServiceContract` (marker interface — no methods to implement)
- All write operations wrapped in `DB::transaction()`
- Constructor injection only — never `app()` / `resolve()` inside a service
- **No** `DB::` calls outside of services (ARCH-001 forbids it in controllers)

## Controllers

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

Rules:
- `final class` — no extension
- Zero business logic, zero DB calls
- Delegate everything to the injected service
- Always call `$this->authorize()` before delegating
- Return `JsonResource` (single) or `JsonResource::collection()` (list)
- Multi-step workflow actions are **named methods** (`headApprove`, `hrCheck`) — not a generic `action()` method

## Value Objects

```php
final readonly class Foo implements Stringable
{
    public function __construct(public readonly string $value) {}
}
```

Rules:
- `final readonly class` in `app/Shared/ValueObjects/`
- Immutable — all mutation methods return a new instance
- No Eloquent, no service container inside a value object

## Currency — `Money` Value Object

- **Never use `float`** for money. Always use `Money`.
- `₱25,000 = 2_500_000 centavos`
- `Money::fromFloat(25000.00)` and `Money::fromCentavos(2_500_000)` both work
- `Money::fromCentavos()` throws `ValidationException` if value is negative — guard before subtracting
- DB columns store raw centavo integers; cast to `Money` in the model via `$casts` or accessor

## Custom Exceptions

```php
final class FooException extends DomainException
{
    public static function forBar(string $detail): self
    {
        return new self(
            message: "Foo failed: {$detail}",
            errorCode: 'FOO_BAR',
            httpStatus: 422,
        );
    }
}
```

Rules:
- Extend `App\Shared\Exceptions\DomainException`
- All 3 constructor args are **mandatory**: `message`, `errorCode` (SCREAMING_SNAKE), `httpStatus`
- Place in `app/Shared/Exceptions/` — never inside a domain folder
- Use named static factory constructors for readability

## State Machines

- Defined in `app/Domains/<Domain>/StateMachines/`
- Hold a `TRANSITIONS` constant — never scatter status string comparisons in controllers or services
- Call via `$this->stateMachine->transition($model, $event)` inside the service
- Invalid transitions throw `InvalidStateTransitionException`

## Payroll Pipeline Steps

```php
final class StepXXFooStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        // mutate $ctx fields
        return $next($ctx);
    }
}
```

- `final class`, invokable, no constructor dependencies — inject via `app()->make()` only if absolutely necessary
- Only mutate `PayrollComputationContext` — never query the DB directly inside a step (snapshots are in `$ctx`)
- Steps run in strict order 01–17; do not skip or reorder

## Policies

- Place in `app/Domains/<Domain>/Policies/`
- Register in `AppServiceProvider` using `Gate::policy()`
- Method names map to controller methods: `viewAny`, `view`, `create`, `update`, `delete`
- Extra workflow actions: `approve`, `reject`, `publish`, etc.
- SoD check: `$user->id !== $record->created_by_id` — the same user cannot create AND approve

## Department Scoping

- `dept_scope` middleware applies a global scope to all queries inside its route group
- Services that must bypass it call `Employee::withoutDepartmentScope()`
- Roles that automatically bypass: `admin`, `super_admin`, `executive`, `vice_president`
- `manager` and `head` do **not** automatically bypass — they need explicit department pivot entries

## ARCH Rules Checklist (enforced by `tests/Arch/`)

| Rule | What it checks |
|------|---------------|
| ARCH-001 | No `DB::` in `app/Http/Controllers/**` |
| ARCH-002 | All `app/Domains/**/Services/**` implement `ServiceContract` |
| ARCH-003 | All `app/Shared/Exceptions/**` extend `DomainException` |
| ARCH-004 | All `app/Shared/ValueObjects/**` are `final readonly class` |
| ARCH-005 | No `dd()` / `dump()` / `var_dump()` anywhere in `app/` |
| ARCH-006 | `app/Shared/Contracts/` contains only interfaces |

## Prohibited

- `dd()`, `dump()`, `var_dump()`, `ray()` anywhere in `app/`
- `float` for currency
- Business logic or DB queries in controllers
- Inline closures in routes for anything beyond trivial reference/lookup endpoints
- Adding `@phpstan-ignore` without first checking if it belongs in `phpstan-baseline.neon`
