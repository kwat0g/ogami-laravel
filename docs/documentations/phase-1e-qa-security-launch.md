# Phase 1E — QA, Security & Go-Live
## Sprints 17–18 · Weeks 33–36

**Goal:** Achieve production-grade confidence through exhaustive automated testing, OWASP security remediation, user acceptance testing, performance profiling, and a verified backup/restore cycle before tagging `v1.0.0`.

---

## Sprint 17 — Full Test Suite
### Weeks 33–34

### What was built

**Test Stack**

| Tool | Purpose |
|---|---|
| Pest PHP 3 | PHP unit + feature test runner (Laravel-native) |
| Mockery | Spies, mocks, and fakes for service isolation |
| RefreshDatabase | Each test runs in a DB transaction, rolled back after |
| k6 | JavaScript-based load testing |
| Playwright | E2E browser tests (Chromium) |

**24-Scenario Golden Payroll Test Suite**

`tests/Feature/Payroll/GoldenPayrollTest.php` — the most important test file in the codebase. Each scenario is a fully deterministic payroll computation with known inputs and expected outputs (verified manually against BIR RR 11-2018 calculations):

| ID | Scenario |
|---|---|
| GS-01 | Regular monthly employee, no OT, no absences |
| GS-02 | Regular employee with 3 days absent |
| GS-03 | Regular employee with 4 hours OT on regular day |
| GS-04 | Employee who worked on a special non-working holiday |
| GS-05 | Employee who worked on a regular holiday |
| GS-06 | Employee with night differential (≥4 hours between 10 PM–6 AM) |
| GS-07 | Employee with active SSS salary loan deduction |
| GS-08 | Employee where LN-007 (min wage protection) truncates loan deduction |
| GS-09 | First payroll run of a new hire (mid-period proration) |
| GS-10 | Final payroll run of a resigned employee (last day of month) |
| GS-11 | Employee on unpaid leave for full period |
| GS-12 | Employee with salary change effective mid-period |
| GS-13 | Minimum wage earner (MWE) — zero income tax |
| GS-14 | High earner crossing the 35% TRAIN bracket |
| GS-15 | 13th month pay computation (December run) |
| GS-16 | SIL monetization added to December run |
| GS-17 | Employee with multiple deductions: loan + cash advance + uniform |
| GS-18 | Employee with PhilHealth reaching annual cap |
| GS-19 | Employee with retroactive OT approval (adjustment run) |
| GS-20 | Night shift employee — rest day OT at 130% + ND |
| GS-21 | Employee on approved leave (paid) — leave pay counts toward gross |
| GS-22 | Regular holiday + OT (200% base + OT premium) |
| GS-23 | December year-end tax reconciliation (over-withheld → refund) |
| GS-24 | December year-end tax reconciliation (under-withheld → collection) |

Each scenario asserts exact values for: gross pay, SSS, PhilHealth, Pag-IBIG, withholding tax, loan deductions, and net pay (to PHP centavo precision using `Money::equals()`).

**Property-Based Tests**

`tests/Feature/Payroll/PropertyBasedTest.php` — uses `$this->faker` to generate hundreds of random payroll inputs and asserts invariants:
- Net pay ≥ 0 (never negative)
- Net pay ≤ Gross pay
- Sum of EE deductions + net pay = gross pay (conservation)
- If employee is MWE, withholding tax = 0
- If salary > PHP 250,000/year, withholding tax > 0

**Feature Test Coverage**

Coverage tracked per domain:

| Domain | Coverage |
|---|---|
| HR (Employee, Attendance, Leave, Loan) | 84% |
| Payroll (Engine, Reports) | 91% |
| Accounting (GL, AP, AR, Reports) | 82% |
| Auth / RBAC / SoD | 96% |
| **Overall** | **87%** |

**SoD Negative-Path Tests (SOD-001–010)**

`tests/Feature/AccessControl/SodTest.php` and `tests/Feature/AccessControl/RbacTest.php` — each test attempts an operation that violates a SoD rule and asserts the response is `403 Forbidden` with the correct machine-readable `sod_code` in the JSON body.

**k6 Load Tests**

Located in `tests/k6/` and `tests/load/`:
- `payroll_run_load.js` — 50 virtual users, 5-minute ramp, simulates payroll run submission + polling
- `attendance_import_load.js` — 20 VUs uploading CSV files concurrently
- `reports_load.js` — 30 VUs hitting GL report + balance sheet endpoints

Baseline thresholds configured: `p95 < 500ms`, `error_rate < 1%`.

---

## Sprint 18 — Security, UAT, Performance & Go-Live
### Weeks 35–36

### What was built

**OWASP ZAP Baseline Scan**

ZAP automated baseline scan against the staging deployment. Final scan result:

| Severity | Count |
|---|---|
| HIGH | 0 |
| MEDIUM | 0 |
| LOW | 3 (informational) |
| INFORMATIONAL | 57 |
| **PASS** | **60** |

All HIGH and MEDIUM findings resolved during this sprint (detailed remediation log in [docs/zap-security-findings.md](../zap-security-findings.md)).

**Security Bugs Fixed in Sprint 18**

| Bug | Location | Fix Applied |
|---|---|---|
| PHP emits `X-Powered-By` regardless of Symfony header removal | `SecurityHeadersMiddleware.php` | Added `header_remove('X-Powered-By')` before `$next($request)` — must be called pre-SAPI |
| `expose_php` not disabled in production | `docker/php/php-prod.ini` | Added `expose_php = Off` |
| CSP `script-src` still had `'unsafe-inline'` in Nginx config | `docker/nginx/default.conf` | Removed `'unsafe-inline'`; added `fastcgi_hide_header X-Powered-By` |
| `AppServiceProvider` did not register `LeaveBalance` policy | `AppServiceProvider.php` | Added `Gate::policy(LeaveBalance::class, LeaveRequestPolicy::class)` — caused 403 on `/leave/balances` |
| `FiscalPeriodPolicy` had no `before()` hook for admin bypass | `FiscalPeriodPolicy.php` | Added `before(User $user)` returning `true` for the `admin` role |
| `ChartOfAccountPolicy` had no `before()` hook | `ChartOfAccountPolicy.php` | Same `before()` fix as above |
| `JournalEntryService::generateJeNumber` received Carbon but declared `string` type | `JournalEntryService.php` | Added `->toDateString()` conversion at all call sites |
| `BalanceSheetService` did not select `is_current` on COA query | `BalanceSheetService.php` | Added `is_current` to the `->select()` array — caused "Undefined property" 500 |
| `__dirname` undefined in ESM context (`auth.setup.ts`) | `e2e/setup/auth.setup.ts` | Replaced with `fileURLToPath(import.meta.url)` + `dirname()` + `join()` from `url`/`path` |
| Playwright/E2E files not covered by `@types/node` | `tsconfig.node.json` | Added `e2e/**` and `playwright.config.ts` to `include`; added `esModuleInterop: true` |

**User Acceptance Testing (UAT)**

Three UAT scenarios conducted with Finance and HR staff:

| ID | Scenario | Result |
|---|---|---|
| UAT-01 | Full payroll cycle: setup → attendance import → leave reconciliation → run payroll → approve → download payslips | **PASS** |
| UAT-02 | Month-end accounting: create AP invoices → approve → generate aging report → post payments → run Trial Balance | **PASS** |
| UAT-03 | HR onboarding: create employee → upload documents → assign shift → request leave → approve leave → verify calendar | **PASS** |

All UAT-01/02/03 passed without blocking defects. One cosmetic UI feedback item (payslip column alignment on Safari) noted as non-blocking.

**Performance Profiling**

All endpoints measured with Blackfire PHP profiler + production Docker build on the UAT server (4 vCPU, 8 GB RAM):

| Endpoint | P95 Response Time | Result |
|---|---|---|
| `GET /api/payroll/runs/{id}` | 87 ms | ✅ |
| `POST /api/payroll/runs/{id}/validate` | 143 ms | ✅ |
| `GET /api/accounting/reports/balance-sheet` | 162 ms | ✅ |
| `GET /api/accounting/reports/income-statement` | 178 ms | ✅ |
| `GET /api/accounting/reports/trial-balance` | 134 ms | ✅ |
| `GET /api/accounting/ap/aging` | 91 ms | ✅ |
| `GET /api/employees` (paginated, 50/page) | 44 ms | ✅ |
| `POST /api/attendance/import` (500-row CSV) | 196 ms | ✅ |

All endpoints comfortably under the 200 ms SLA.

**Production Docker Build Validation**

```bash
docker build --target production -t ogami-erp:prod-clean .
docker compose -f docker-compose.prod.yml up -d
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Build passed. Image size: 312 MB (PHP-FPM + app). Nginx container: 23 MB.

**Backup & Restore Verification**

`php artisan backup:run` → produces encrypted `.zip` (PostgreSQL dump + all Medialibrary files).

Restore test procedure:
1. Spin fresh Postgres container (empty)
2. Restore dump from latest backup
3. Run all 24 golden payroll scenarios against restored DB
4. Result: **24/24 golden tests PASS** on restored database

This confirms the backup contains a complete, consistent, and operationally valid dataset.

**Horizon + Pulse Monitoring**

- Horizon running 2 supervisors: `supervisor-default` (8 workers) and `supervisor-payroll` (5 workers, `payroll` queue)
- Pulse dashboard accessible at `/pulse` (admin-only route) showing: queue throughput, slow queries (>100ms logged), exception rate, cache hit ratio
- Reverb WebSocket status visible on the Pulse dashboard

**v1.0.0 Tag**

```
git tag v1.0.0 22cd030 -m "Phase 1 complete — Production ready"
git push origin v1.0.0
```

---

## Final Phase 1 Metrics

| Metric | Result |
|---|---|
| Test count | 222 passing, 1 skipped, 0 failed |
| Assertions | 656 |
| Test coverage (overall) | 87% |
| ZAP HIGH findings | 0 |
| ZAP MEDIUM findings | 0 |
| UAT scenarios passed | 3 / 3 |
| Endpoint P95 latency | All < 200 ms |
| Golden payroll scenarios (on live DB) | 24 / 24 ✅ |
| Golden payroll scenarios (on restored DB) | 24 / 24 ✅ |
| Migrations | 39 |
| Domain-driven modules | 9 |
| Git tag | `v1.0.0` @ `22cd030` |

---

## Known Non-Blocking Items (Post-Launch Backlog)

| Item | Priority |
|---|---|
| Payslip column alignment on Safari (cosmetic) | Low |
| Property-based test coverage for AR invoice edge cases | Medium |
| Multi-entity (multi-branch) support | Future phase |
| API versioning (`/api/v2/`) for mobile integration | Future phase |

---

*Previous: [Phase 1D — Accounting Module](phase-1d-accounting-module.md) · Back to: [README](README.md)*
