---
name: run-domain-tests
description: "Run the Pest test suite for a specific domain or all suites. Pass the domain name (e.g. 'HR', 'Payroll', 'Leave') or 'all' to run everything."
argument-hint: "Domain name (HR, Payroll, Leave, etc.) or 'all'"
agent: agent
---
Run the correct Pest PHP test suite for the specified domain in the Ogami ERP project.

## Instructions

1. **Determine the test scope** from the argument:
   - If `all` → run all suites
   - If a domain name is given (e.g. `HR`, `Payroll`, `Leave`) → run Feature + Integration for that domain, plus Unit if payroll-related
   - If no argument → ask which domain or scope to test

2. **Seed check**: Feature and Integration tests require the test database to be migrated. Confirm `ogami_erp_test` is accessible before running (check `phpunit.xml` for connection details).

3. **Run the appropriate command(s)**:

```bash
# All suites
./vendor/bin/pest

# Single domain — Feature tests
./vendor/bin/pest --testsuite=Feature --filter=<DomainName>

# Single domain — Integration tests
./vendor/bin/pest --testsuite=Integration --filter=<DomainName>

# Payroll: also run the golden suite
./vendor/bin/pest --testsuite=Unit --filter=GoldenSuite

# Architecture rules (always safe to run)
./vendor/bin/pest --testsuite=Arch

# Value objects only
./vendor/bin/pest --testsuite=Unit
```

4. **Report results**: Show pass/fail counts. If any test fails:
   - Show the full failure output.
   - Identify whether it is a data issue (missing seed), a logic regression, or an architecture violation.
   - For payroll golden suite failures, compare actual vs expected centavo values.

## Domain → Test File Mapping

| Domain | Feature path | Integration path |
|--------|-------------|-----------------|
| HR | `tests/Feature/HR/` | — |
| Leave | `tests/Feature/Leave/` | — |
| Payroll | `tests/Feature/Payroll/` | `tests/Integration/PayrollToGL/` |
| AP | `tests/Feature/AP/` | `tests/Integration/APToGL/` |
| Attendance | `tests/Feature/Attendance/` | — |
| Loan | `tests/Feature/Loan/` | — |
| Accounting | `tests/Feature/Accounting/` | — |
| Procurement | `tests/Feature/Procurement/` | — |

## Important Reminders

- Never use SQLite — tests require PostgreSQL (`ogami_erp_test` on 127.0.0.1:5432).
- Never create a `.env.testing` file — DB config is hardcoded in `phpunit.xml` with `force="true"`.
- Monetary assertions use **centavos** (`2_500_000` = ₱25,000).
