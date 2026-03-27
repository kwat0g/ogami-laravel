# Debug Mode Rules (Non-Obvious Only)

- Tests MUST use PostgreSQL (`ogami_erp_test`) — `phpunit.xml` uses `force="true"` to override any env vars. Never create `.env.testing`.
- Seed RBAC before creating users in tests: `$this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])` — without it, all permission checks fail silently.
- Custom Pest expectations: `->toBeValidationError('field')` and `->toBeDomainError('ERROR_CODE')` are defined in `tests/Pest.php`.
- `PayrollTestHelper` in `tests/Support/` strips generated columns and handles field aliases — raw factories will fail for payroll-related models.
- Payroll golden suite (`tests/Unit/Payroll/GoldenSuiteTest.php`): 24 canonical scenarios with locked expected values — don't change without documented justification.
- `Money::fromCentavos()` throws `ValidationException` on negative input — if net pay goes negative, check deduction ordering in the 17-step pipeline.
- `api.ts` cooldown-aborted requests return `{ __cooldown: true }` — check `isHandledApiError()` before diagnosing "silent" frontend failures.
- Department scope middleware applies automatically — use `Employee::withoutDepartmentScope()` if queries return unexpectedly empty results.
- Fixed assets `asset_code` is PG-trigger-set — if it's NULL after insert, check the trigger exists (`asset_code_trigger`).
- `pulse:check` needs `->withoutOverlapping()` in scheduler — without it, orphan processes pile up every minute.
- PO status sequence: `draft → sent → negotiating → acknowledged → in_transit → delivered → partially_received/fully_received → closed`. `delivered` means vendor confirmed but warehouse hasn't verified yet.
