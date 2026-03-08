---
description: "Use when writing, editing, or debugging Pest PHP tests, feature tests, integration tests, or unit tests. Covers test setup, database conventions, and Ogami-specific test helpers."
applyTo: "tests/**"
---
# Ogami ERP — Test Writing Guidelines

## Database

- **Always use PostgreSQL** test DB (`ogami_erp_test`). Never use SQLite — stored computed columns (`daily_rate`, `hourly_rate`) and CHECK constraints are PgSQL-only.
- **Never create a `.env.testing` file.** Test DB config is locked in `phpunit.xml` with `force="true"`, which wins over all shell env vars.
- `RefreshDatabase` is required for Feature and Integration tests. Pure Unit tests (value objects, pipeline steps) do not use it.

## Test Setup Boilerplate (Feature / Integration)

Always seed RBAC before creating users, and seed rate tables before running payroll-related tests:

```php
beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    // Add domain-specific rate seeders as needed:
    // $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder'])->assertExitCode(0);
    $this->actor = User::factory()->create();
    $this->actor->assignRole('hr_manager');
});
```

## Payroll Test Factories

Always use `PayrollTestHelper` (in `tests/Support/PayrollTestHelper.php`) instead of raw `Employee::factory()` calls for payroll tests:

- `normalizeOverrides()` strips `GENERATED ALWAYS AS STORED` columns (`daily_rate`, `hourly_rate`) automatically.
- Factory key aliases: `hired_at` → `date_hired`; `resigned_at` → `separation_date`.
- Monetary values are always in **centavos**: `'basic_monthly_rate' => 2_500_000` = ₱25,000.

## Custom Expectations

```php
->toBeValidationError('field_name')   // asserts error_code === 'VALIDATION_ERROR' + errors.field_name present
->toBeDomainError('ERROR_CODE')       // asserts error_code matches
```

Both are defined in `tests/Pest.php`.

## Architecture Rules (ARCH tests — `tests/Arch/`)

Do not break these; they are enforced automatically:

| Rule | Constraint |
|------|-----------|
| ARCH-001 | Controllers must not call `DB::` |
| ARCH-002 | Domain services must implement `ServiceContract` |
| ARCH-003 | Custom exceptions must extend `DomainException` |
| ARCH-004 | Value objects must be `final readonly class` |
| ARCH-005 | No `dd()`/`dump()`/`var_dump()` in `app/` |
| ARCH-006 | `Shared\Contracts` contains interfaces only |

## Test Suites

```bash
./vendor/bin/pest --testsuite=Unit        # value objects, payroll golden suite (no DB)
./vendor/bin/pest --testsuite=Feature     # HTTP endpoint tests (RefreshDatabase)
./vendor/bin/pest --testsuite=Integration # cross-domain workflows (PayrollToGL, APToGL)
./vendor/bin/pest --testsuite=Arch        # structural rules
```

## Domain-Specific Notes

- **Payroll golden suite**: 24 canonical scenarios in `tests/Unit/Payroll/GoldenSuiteTest.php`. Do not change expected values without a documented reason.
- **`Money` value object**: Never use floats. `₱25,000 = 2_500_000 centavos`. `Money::fromCentavos()` throws on negative values.
- **`DomainException` requires 3 args**: `message`, `errorCode`, `httpStatus` — all mandatory.
- **SoD tests**: The same user who creates a record cannot approve it. Only `admin` and `super_admin` bypass SoD — `manager` cannot.
