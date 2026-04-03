# PHPStan + Pint Debt Follow-up (2026-04-03)

## Context
During feature validation, static analysis and style checks were executed after fixing severe blockers.

## Completed in this pass
- Removed stale phpstan baseline entries pointing to missing files.
- Removed duplicate `ServiceContract` imports that caused severe PHPStan halts.
- Confirmed PHPStan now executes full scan instead of stopping at config/import failures.

## Remaining debt (pre-existing, broad scope)
- PHPStan reports numerous domain-wide issues (notifications/types/nullability/class-not-found in ISO-related symbols).
- Pint check reports repository-wide style drift across many modules.

## Recommended follow-up phases
1. Static analysis baseline normalization
- Rebuild baseline from current main branch after triaging critical findings.
- Separate real defects from annotation-level noise.

2. Type-safety fixes by module
- CRM notifications
- Delivery notifications
- ISO notifications/models references
- Leave notifications null coalesce warnings

3. Style cleanup by module batches
- Run Pint auto-fixes module-by-module (avoid whole-repo mega-diff).
- Verify each batch with targeted tests.

4. CI hardening
- Add staged checks:
  - `phpstan analyse` on changed paths (required)
  - `pint --test` on changed paths (required)
  - full repo checks as non-blocking nightly until debt is burned down.

## Suggested ticket breakdown
- T1: Normalize phpstan baseline and remove stale suppressions.
- T2: Fix notification typing/nullability warnings.
- T3: Resolve ISO model reference errors.
- T4: Incremental Pint cleanup for AP/AR/Production/Recruitment modules.
- T5: CI policy update for changed-path static/style gates.
