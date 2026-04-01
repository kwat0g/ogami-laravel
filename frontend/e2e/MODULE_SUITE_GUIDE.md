# Frontend Module Suite Guide

Use this guide to test all frontend modules without losing momentum when a bug appears.

## Why This Flow

- Runs module-by-module instead of one huge fragile run.
- Continues even if a module fails.
- Produces a failed-modules list so you can fix and re-run only what failed.

## Commands

Run all modules:

```bash
bash e2e/run-module-suite.sh
```

Run all modules with Lightpanda:

```bash
bash e2e/run-module-suite.sh --project lightpanda
```

Run one module:

```bash
bash e2e/run-module-suite.sh --module inventory
```

Run one module with Lightpanda:

```bash
bash e2e/run-module-suite.sh --module inventory --project lightpanda
```

Run all configured Playwright projects (for example chromium + lightpanda):

```bash
bash e2e/run-module-suite.sh --all-projects
```

Retry only failed modules from last run:

```bash
bash e2e/run-module-suite.sh --retry-failed
```

Show available modules:

```bash
bash e2e/run-module-suite.sh --list
```

## Included Modules

- auth
- hr
- payroll
- accounting
- procurement
- inventory
- production
- qc
- mold
- crm
- delivery
- rbac

## Artifacts

Each run writes:

- per-module logs in e2e/module-reports/<timestamp>/
- summary table in e2e/module-reports/<timestamp>/summary.md
- failed module list in:
  - e2e/module-reports/<timestamp>/failed-modules.txt
  - e2e/module-reports/last-failed-modules.txt

## CI Gates

- PR smoke gate runs on Lightpanda for `auth` and `rbac` only.
- Nightly gate runs full canonical module suite on Lightpanda.
- Manual CI dispatch supports `smoke`, `full`, or `both` via `e2e_target` input.
- Both CI jobs upload these artifacts for triage:
  - `frontend/e2e/module-reports`
  - `frontend/e2e/playwright-report`
  - Laravel and Vite logs from `/tmp`

## Daily Practical Loop

1. Run full module suite once.
2. Fix first failed module.
3. Re-run only failed modules.
4. Repeat until last-failed-modules is empty.

## Notes

- Requires frontend on http://localhost:5173 and backend on http://localhost:8000.
- The runner uses workers=1 for stability against rate-limit and shared test data issues.
- By default the runner uses Playwright project chromium for reliability.
- Use `--project lightpanda` when you want to run the same suite on Lightpanda.
- Canonical module specs are mapped from `e2e/specs/NN-*.spec.ts` via `e2e/run-module-suite.sh`.
- Legacy specs are listed in `e2e/LEGACY_SPECS.md` and are not part of default module execution.
