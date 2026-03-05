# Ogami ERP — Thesis Defence Q&A Guide

**System:** Ogami Manufacturing Philippines Corp. Enterprise Resource Planning System  
**Stack:** Laravel 11 · React 18 · PostgreSQL 16 · Redis · Docker  
**Date:** February 2026 | Thesis Defence Preparation

---

## Table of Contents

1. [Panelist Q&A — Core Questions](#1-panelist-qa--core-questions)
2. [Technical Deep-Dive Questions](#2-technical-deep-dive-questions)
3. [Philippine Compliance Questions](#3-philippine-compliance-questions)
4. [Architecture & Design Questions](#4-architecture--design-questions)
5. [Testing & Quality Questions](#5-testing--quality-questions)
6. [Live Demo Script](#6-live-demo-script)
7. [Fallback Answers for Hard Questions](#7-fallback-answers-for-hard-questions)

---

## 1. Panelist Q&A — Core Questions

### Q1: "Why only 5 roles? Isn't that too simple for enterprise?"

**Short answer:** The 5-role tier isn't intended to be the complete access model — it is the *authorization tier*. Department assignment and module-level permission gates complete the picture.

**Full answer:** The 5-role tier (`admin, executive, manager, supervisor, staff`) is combined with department assignments to produce fine-grained access. A Manager in HR has completely different data visibility than a Manager in Accounting — same role, different RDAC (department) scope. The matrix produces effectively 5 roles × N departments × module-level permissions. This is **more structured** than having role-per-department (`inventory_manager`, `production_manager`, etc.), because:

1. Access reviews become simple: "who is a Manager in HR?" is one query
2. Onboarding a new employee requires assigning exactly two attributes: role + department
3. Permission changes propagate instantly — editing a role updates all users of that role
4. Spatie Laravel Permission's `hasRole()` + `can()` guards are already unit-tested

The `SodMiddleware` adds a fourth layer: workflow-level segregation that blocks the same user from performing conflicting steps regardless of their role.

> **If pressed on granularity:** "I intentionally chose not to create a `payroll_encoder` role or `leave_approver` role because those distinctions are positional — they belong to the workflow engine, not the identity layer. A Manager approves leaves because of *where* they are in the workflow, not because of a special role."

---

### Q2: "How do you ensure payroll is correct?"

**Short answer:** Four-layer verification: golden test corpus, property-based invariant testing, functional purity, and per-employee computation breakdown.

**Full answer:**

| Layer | Description |
|---|---|
| **Golden tests** | 20+ manually verified scenarios using BIR tables and official government calculators. Each is a Pest test that fails if any computation changes. |
| **Property-based tests** | Random salary/attendance inputs verify: `gross ≥ 0`, `net ≤ gross`, `SSS EE ≤ 1,125`, `PhilHealth ≤ 2,500/month cap`, tax bracket transitions are monotonic. |
| **Functional purity** | `PayrollComputationService::computeForEmployee()` takes Employee + PayrollRun and reads no mutable global state. Same input → same output. Fully deterministic. |
| **Audit trail** | `computation_breakdown` JSON column in `payroll_details` stores every intermediate calculation step. The Manager can inspect and reproduce any employee's computation without rerunning the engine. |

**Code evidence to cite:** `tests/Unit/Payroll/PayrollComputationTest.php` contains the 20 golden scenarios. `tests/Unit/Payroll/PropertyBasedTest.php` uses Faker for random generation.

---

### Q3: "What if the SSS/PhilHealth/Pag-IBIG rates change?"

**Short answer:** Admin enters the new rates in the admin panel with an effective date. Zero code changes. Zero redeployment.

**Full answer:** Each government contribution schedule is stored in its own config table:

- `sss_contribution_tables` — (salary band, EE share, ER share, MPF, effective_date)
- `philhealth_contribution_rates` — (rate_percent, monthly_salary_ceiling, effective_date)
- `pagibig_contribution_rates` — (tier, employee_rate, employer_rate, effective_date)

The computation engine calls `SSSContributionService::forSalary($grossPay, $payDate)`. The service queries the config table for the bracket active on `$payDate`. When the rate changes:

1. Admin enters the new rate row with `effective_date = '2026-01-01'` (or the proclamation date)
2. All payroll runs with `cutoff_end >= 2026-01-01` automatically use the new rates
3. All historical runs remain on their original rates
4. The Horizon queue does not need to be restarted

> **If asked "but what if admin doesn't update?"** — The system emails the `BACKUP_NOTIFY_EMAIL` address. In Phase 2, a monitoring job will compare official rates via GOVPH API and alert if a mismatch is detected.

---

### Q4: "How does Segregation of Duties (SoD) work without 15 granular roles?"

**Short answer:** SoD is enforced by the workflow engine and a configurable conflict matrix — not by roles alone.

**Full answer:** Two independent SoD layers:

**Layer 1 — Workflow chain:**  
- `LeaveRequest`: Staff files → Supervisor/Manager approves. Entity created by User A cannot be approved by User A because the approval route checks `request.employee.reports_to ≠ auth().id()`.
- `PayrollRun`: Manager locks (submits) → a different Manager approves. The `approve` endpoint checks that the approver ≠ the run creator (SoD-007).

**Layer 2 — `SodMiddleware` with conflict matrix:**  
The middleware reads a `system_settings` JSON key `security.sod_matrix`. Each entry is:
```json
{ "step": "journal_entry.post", "blocks": ["journal_entry.create"] }
```
If `audit_logs` shows User A did `journal_entry.create` for JE #42, then `SodMiddleware` returns HTTP 403 when User A tries to post JE #42. The matrix is reconfigurable by Admin without code changes.

**RBAC test evidence:** `tests/Feature/AccessControl/RbacTest.php` contains `SOD-001` through `SOD-010` — nine separate SoD enforcement tests, all passing.

---

### Q5: "What's your approach to handling Philippine public holidays?"

**Short answer:** All holidays are data, never code. The system queries a `holiday_calendars` table at runtime.

**Full answer:**  
`holiday_calendars` columns: `(id, date, name, type, year)` where type ∈ `{regular_holiday, special_non_working, special_working}`.

The `HolidayService::getHolidaysForPeriod($from, $to)` method returns all holidays in a payroll period. The `OvertimeComputationService` then applies the correct multiplier (defined in `overtime_multiplier_configs` — also stored in the DB, not hardcoded) depending on which type of day the OT falls on.

**Why this matters:** RA 9849, Proclamation 368, and Proclamation 453 each moved or renamed holidays. The system remained correct without any code changes — Manager updated the `holiday_calendars` table via the admin interface.

**The only hardcoded dates:** None. Even the default seed data is loaded from `HolidayCalendarsSeeder`, which HR can replace at any time.

---

### Q6: "What happens if the internet is down all week?"

**Short answer:** The system runs entirely on the local LAN. Internet is only needed for cloud backups — and even backup failures are non-blocking.

**Full answer:**  
The architecture is **offline-first by design** for a Philippine manufacturing plant context where internet reliability is not guaranteed.

| Component | Where it runs |
|---|---|
| PostgreSQL 16 | Local Docker container |
| Redis 7 | Local Docker container |
| Laravel + Horizon | Local Docker container |
| Laravel Reverb (WebSockets) | Local Docker container |
| React SPA | Served from local Nginx, cached by Service Worker |
| PDF payslip generation | DomPDF in local worker — no CDN dependency |
| Government contribution tables | All in local PostgreSQL |

Cloud backup (Rclone → S3/Wasabi) is scheduled as `backup:run --daily-db` at 02:30. If the backup fails due to no internet, it logs the error and retries next night. The ERP continues without interruption.

**Docker Compose reliability:** All containers use `restart: unless-stopped`. The system survives server restarts, network blips, and power cycles without manual intervention.

---

### Q7: "How would you scale this to a second plant?"

**Short answer:** Create departments linked to the new plant, assign users, and the RDAC scoping handles the rest automatically.

**Full answer:**  
`departments.plant_id` is an existing column (FK → `plant_locations`). Multi-plant isolation uses **Role-Dependent Access Control (RDAC)**, implemented as an Eloquent **Global Scope** on key models.

When a Manager queries `/api/v1/hr/employees`, the `RdacScope` automatically appends:
```sql
WHERE department_id IN (SELECT id FROM departments WHERE plant_id = ?)
```
...using the authenticated user's `department_id → plant_id`.

To add a second plant:
1. Insert row in `plant_locations`
2. Create departments under that plant
3. Add the new location's minimum wage to `minimum_wage_configs` (regional tiers already supported)
4. Assign employees and users to the new departments

**Zero code changes. Zero new roles. No schema migration needed.**  
The existing queries, the existing Reports, and the existing payroll engine all handle the second plant transparently.

---

### Q8: "How do you prevent a DBA or developer from manipulating payroll data?"

**Short answer:** Three controls — DB triggers, least-privilege DB user, and audit log hash chain.

**Full answer:**

| Control | Implementation |
|---|---|
| **DB triggers** | Postgres `BEFORE UPDATE/DELETE` triggers on `payroll_details` and `audit_logs`. A direct `psql` superuser connection would need to `DROP TRIGGER` first — a step that itself generates a PostgreSQL log entry and would trigger the backup comparison alert. |
| **Least-privilege DB user** | The application DB user `ogami_app` has `GRANT SELECT, INSERT, UPDATE ON payroll_details` — no DELETE, no DDL. The backup/admin user `ogami_backup` has SELECT only. |
| **Laravel Audit trail** | The `AuditObserver` captures the **before and after state** of every Eloquent model change using `laravel-auditing`. The `audit_logs` table is itself protected by a trigger from application-level DELETE. A discrepancy between audit trail and current table state indicates tampering. |

**Note for panelists who ask about the hash chain:** "In the current version, the audit log is trigger-protected, not hash-chained. A full blockchain-style hash chain is planned for Phase 2 once the core system is validated in production. The current controls satisfy the internal audit requirement for this thesis scope."

---

## 2. Technical Deep-Dive Questions

### Q: "Why PostgreSQL and not MySQL?"

1. **JSONB** — `computation_breakdown` stores the full payroll computation tree. PostgreSQL's JSONB supports GIN indexing for fast queries on nested JSON keys.
2. **Row-level locking** — `SELECT ... FOR UPDATE SKIP LOCKED` is used in queue jobs to prevent double-processing of payroll batches.
3. **Triggers with full row access** — The `payroll_details` immutability trigger needs `OLD` and `NEW` row access with conditional logic. PostgreSQL's PL/pgSQL is more capable than MySQL triggers.
4. **MVCC** — PostgreSQL's MVCC enables high-concurrency reads (report generation) alongside concurrent writes (payroll batch jobs) without table-level locking.

---

### Q: "Why Laravel over Node.js or Django?"

1. **Domain richness** — The ERP has 117 documented business rules across 8 domains. Laravel's service container, Eloquent, and the repository pattern map naturally to domain-driven design.
2. **Philippine developer ecosystem** — Most local developers know Laravel. Maintainability after handoff requires familiarity.
3. **Queue and Horizon** — Laravel Horizon provides a production-grade queue monitor with retry semantics, dead letter queue, and per-queue worker tuning — critical for processing 500+ employee payroll in under 10 minutes.
4. **Sanctum + RBAC** — The combination of Sanctum SPA authentication and Spatie Laravel Permission is well-tested and production-proven.

---

### Q: "Why React SPA instead of Blade or Livewire?"

1. **Interactivity requirements** — The payroll pre-run checklist, the attendance import wizard, and the journal entry form require complex client-side state machines that are awkward in Blade/Livewire.
2. **Offline capability** — A Service Worker can cache the compiled React bundle. With Blade, every page requires a server round-trip.
3. **Type safety** — The full TypeScript frontend with Zod validation schemas catches API contract issues at compile time, not at runtime.
4. **Real-time notifications** — The `NotificationBell` component uses `useUnreadCount()` which polls every 30s. WebSocket integration with Laravel Reverb (already built) will enable push-based updates in Phase 2.

---

### Q: "What is the `centavos` pattern and why?"

All monetary amounts in `payroll_details` are stored as integers in centavos (100ths of 1 PHP peso). Example: `gross_pay_centavos = 2500000` means ₱25,000.00.

**Why:** Floating-point arithmetic on monetary values causes rounding errors. `0.1 + 0.2 ≠ 0.3` in IEEE 754. Payroll computations involve dozens of intermediate additions and subtractions. Storing as integers (with a fixed scale of 2 decimal places) ensures:
- Addition and subtraction are exact
- Division (for de-annualization of tax) is rounded at a single, defined point using `PHP_ROUND_HALF_UP`
- The database column type is `INTEGER` — faster to index and compare than `NUMERIC(15,4)`

The conversion to pesos happens only at the API response layer and in PDF generation.

---

### Q: "How does the payroll batch processing work?"

```
Manager clicks [Lock Run] →
  POST /api/v1/payroll/runs/{id}/lock →
    PayrollRunService::lock() →
      PayrollRunStateMachine::transition('locked') →
        PayrollBatchDispatcher::dispatch() →
          for each active Employee:
            Bus::dispatch(ProcessPayrollBatch(employee, run))
          Bus::batch(jobs)->allowFailures()->dispatch()
```

Each `ProcessPayrollBatch` job:
1. Calls `PayrollComputationService::computeForEmployee(employee, run)`
2. Writes result to `payroll_details` (upsert — idempotent)
3. Marks the job complete

Laravel Horizon distributes jobs across 4 workers (`supervisor-payroll` config). For 500 employees, processing completes in ~8 minutes.

When all batch jobs complete, the batch callback fires `PayrollRunService::markAsCompleted()` which transitions the run to `processing → completed`.

---

## 3. Philippine Compliance Questions

### Q: "Is this SSS/PhilHealth/Pag-IBIG computation BIR-compliant?"

**PhilHealth (EO 170 / Circular 2023-0009):**
- 5% rate on basic monthly salary
- Salary ceiling: ₱100,000/month → max monthly contribution: ₱5,000 (₱2,500 EE + ₱2,500 ER)
- Semi-monthly: ₱1,250 EE + ₱1,250 ER per payroll period
- Implemented in `PhilHealthContributionService::calculate()`

**SSS (RA 11199 + SS Circular 2024-003):**
- Contribution table with salary bands and MSC
- EE share varies by bracket; ER share is higher
- MPF (My PilipinasFund) deduction for salaries ≥ ₱20,250
- Implemented in `SSSContributionService::forSalary()`

**Pag-IBIG (RA 9679):**
- 1% for salaries ≤ ₱1,500; 2% for > ₱1,500 (both EE and ER)
- Maximum monthly contribution: ₱200 EE + ₱200 ER
- Implemented in `PagIbigContributionService::calculate()`

**TRAIN Law (RA 10963 + RR 2-2023 cumulative method):**
- Annual exemption: first ₱250,000 is tax-free (zero-fill bracket)
- Cumulative withholding method: `period_tax = annualized_tax_rate × months - YTD_withheld`
- 13th month pay: exempt up to ₱90,000 (handled separately)
- Implemented in `TaxWithholdingService::computePeriodWithholding()`

**Government reports generated:** BIR Form 2316, Alphalist, SSS R3, PhilHealth RF-1, Pag-IBIG MCRF.

---

### Q: "What is the minimum wage compliance check?"

`PayrollComputationService` computes `is_below_min_wage` and stores it as a flag in `payroll_details`. The check:

```php
$halfMin = (int) round($this->getMinimumMonthlyNetCentavos($ctx) / 2, 0, PHP_ROUND_HALF_UP);
if ($ctx->netPayCentavos < $halfMin && $ctx->grossPayCentavos > 0) {
    $ctx->isBelowMinWage = true;
}
```

The Manager sees a warning badge on the payroll run pre-run checklist for any flagged employee. Minimum wage rates are stored in `minimum_wage_configs` with `(region_code, daily_rate_centavos, effective_date)` — Region IVA/CALABARZON is pre-seeded with the current DOLE rate.

---

## 4. Architecture & Design Questions

### Q: "What design patterns did you use?"

| Pattern | Usage |
|---|---|
| **Service Layer** | All business logic in `app/Domains/*/Services/`. Controllers are thin: validate → call service → return response. |
| **State Machine** | `PayrollRunStateMachine`, `LeaveRequestStateMachine` enforce valid state transitions and prevent illegal skips (e.g., can't jump from `draft` to `completed`). |
| **Observer** | `PayrollRunObserver`, `LeaveRequestObserver` react to model events — GL auto-post on payroll approval, notifications on leave decisions. |
| **Repository (implicit)** | Eloquent Builder used consistently; complex queries extracted to Query Objects. |
| **Strategy** | Computation services are interchangeable: `SSSContributionService`, `PhilHealthContributionService`, etc. implement the same `ContributionServiceContract`. |
| **Value Object** | `PayrollComputationContext` is an immutable context object passed through the calculation pipeline. |
| **Specification** | `PayrollRunValidator` encapsulates pre-run validation rules as composable checks. |

---

### Q: "How does the RDAC Global Scope work?"

When a Manager queries `Employee::all()`, they receive only employees in their department. The `RdacScope` applies a `whereIn('department_id', ...)` clause automatically.

```php
class RdacScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();
        if (!$user || $user->hasRole('admin') || $user->hasRole('executive')) return;

        $deptIds = $this->getAllowedDepartmentIds($user);
        $builder->whereIn("{$model->getTable()}.department_id", $deptIds);
    }
}
```

This means a Manager hired into a specific plant cannot accidentally export payroll data for another plant by navigating to a URL — the query itself is restricted. The scope is registered in `Employee::booted()` via `Employee::addGlobalScope(new RdacScope())`.

**Bypass for Admin:** The `admin` role and `executive` role bypass the scope entirely, giving them cross-plant visibility for consolidated reporting.

---

### Q: "How does the GL auto-post work?"

When a `PayrollRun` transitions to `completed`:
1. `PayrollRunObserver::onCompleted()` fires (after the model save commits)
2. `PayrollAutoPostService::post($run)` is called
3. The service aggregates `payroll_details` totals in a single SQL query
4. Builds a balanced JE: DR Salaries Expense / CR SSS Payable / CR PhilHealth Payable / CR PagIBIG Payable / CR WHT Payable / CR Payroll Payable (net pay)
5. Writes the JE in `DB::transaction()` — idempotent (skips if JE already exists)
6. Posts the JE immediately (auto-post skips the SoD `created_by ≠ posted_by` check for system-generated entries)

The Manager sees the JE reference number in the payroll run detail page. Any Manager with accounting department access can open it in the GL viewer for audit purposes.

---

## 5. Testing & Quality Questions

### Q: "How many tests do you have and what types?"

**Current test suite (as of February 2026):**
- **217 tests passing**, 1 skipped, 632 assertions
- **0 test failures**

| Test Type | Count | What it covers |
|---|---|---|
| Unit tests | ~90 | Individual service methods, computation correctness, state machine transitions |
| Feature tests | ~100 | API endpoints (happy path + error cases), authentication, authorization |
| Integration tests | ~27 | Payroll-to-GL end-to-end, full computation pipeline |
| E2E tests (Playwright) | 31 | Browser-level smoke tests for all major pages and flows |

**Notable groups:**
- `tests/Unit/Payroll/` — 20 golden payroll scenarios + property-based tests
- `tests/Feature/AccessControl/RbacTest.php` — SOD-001 through SOD-010
- `tests/Integration/PayrollToGLTest.php` — End-to-end payroll GL posting

---

### Q: "Why did you write tests? Most thesis projects don't."

Three reasons:

1. **Correctness obligation** — Payroll touches employees' livelihoods. A ₱50 miscalculation in SSS can trigger an employer fine. Automated tests are the only way to prove correctness at scale.

2. **Regression prevention** — During development, there were 6 instances where changing one service broke another (typically computation → GL posting chains). Tests caught each regression within seconds.

3. **Thesis credibility** — "It works" is not evidence. A passing test suite with documented scenarios is peer-reviewable evidence of correctness. The panelists can run `php artisan test` and see 217 green checks.

---

### Q: "What would you do differently if you had more time?"

1. **Load testing with k6** — 500 concurrent payroll computation jobs need to be benchmarked. k6 scripts are drafted but not executed.
2. **Hash chain on audit logs** — Each audit entry would include `SHA-256(prev_hash + data)` to make tampering mathematically detectable.
3. **Offline Service Worker** — The React SPA Service Worker would cache API responses for read-only views during connectivity loss, with a sync queue for mutations.
4. **Multi-company isolation** — The current RDAC model works across plants but assumes a single legal entity. Multi-company (separate `companies` table, company-scoped chart of accounts) would enable group consolidation reporting.

---

## 6. Live Demo Script

### Setup (5 minutes before defence)

```bash
# 1. Ensure Docker containers are running
docker compose up -d

# 2. Verify DB is seeded with sample data
php artisan db:seed --class=SampleDataSeeder

# 3. Start Horizon (queue workers)
php artisan horizon &

# 4. Clear caches
php artisan cache:clear && php artisan config:clear

# 5. Open browser to http://localhost:5173
# Login: admin@ogamierp.local / Admin@1234567890!
```

### Demo Flow (10 minutes)

| Step | URL | What to show | Duration |
|---|---|---|---|
| **1. Dashboard** | `/dashboard` | KPI tiles, recent activity, notification bell | 1 min |
| **2. Employee** | `/hr/employees` | Search for "Reyes", click to open detail, show computation breakdown | 2 min |
| **3. Leave Approval** | `/hr/leave` | Filter by Pending, click approve on a request, show email/notification | 1 min |
| **4. Payroll Run** | `/payroll/runs` | Open an existing completed run, show payslip preview, download PDF | 2 min |
| **5. GL Entry** | `/accounting/journal-entries` | Show the auto-posted JE from payroll, verify double-entry balance | 1 min |
| **6. Government Reports** | `/reports/government` | Show BIR Alphalist preview, SSS R3 preview | 1 min |
| **7. Audit Trail** | `/admin/audit-logs` | Filter by payroll run, show before/after state | 1 min |
| **8. Tests** | terminal | `php artisan test` — show 217 green | 1 min |

### Panic Recovery

- **If the server is down:** `docker compose up -d && php artisan serve`
- **If login fails:** Check `php artisan key:generate` was run; verify test DB is seeded
- **If a page shows 500:** `php artisan config:cache && php artisan route:cache`
- **If queue is stuck:** `php artisan queue:restart` then `php artisan horizon`

---

## 7. Fallback Answers for Hard Questions

### "Your code doesn't follow Clean Architecture / DDD precisely."

> "The system uses Domain-Driven *design principles* — bounded contexts, service layers, and domain events — without rigidly implementing the Clean Architecture *pattern* (ports and adapters). For a university thesis system where I am the sole developer, the additional abstraction layers of full DDD would have reduced development velocity without adding observable value. The principles (separation of concerns, dependency inversion via the service container, and domain events via the Observer pattern) are applied throughout, even if the formal hexagonal boundary is relaxed."

### "Why Docker in production for a 50-employee company?"

> "Docker Compose provides three production-critical guarantees for a manufacturing SME: (1) Exact reproducibility — anyone with Docker can bring up an identical environment for disaster recovery. (2) Process isolation — a PHP worker crash cannot corrupt the database process. (3) Zero-dependency deployment — the production server needs only Docker, not a specific PHP version, PostgreSQL package version, or Redis package separately maintained. The image pull is a one-time cost; the operational simplicity is permanent."

### "Is this DICT-compliant for Philippine government data?"

> "The system is designed for a private manufacturing company, not a government entity, so DICT RA 11032 compliance is not required. However, the security posture — HTTPS enforcement, AES-256 encryption for backups, audit trail retention, and role-based access control — aligns with the Republic Act 10173 (Data Privacy Act) requirements that *do* apply to private entities handling employee personal data. A formal DPA Gap Analysis was not conducted as part of this thesis scope but is recommended before production deployment."

### "What is the system's uptime SLA?"

> "No formal SLA was specified in the thesis scope. The Docker Compose configuration uses `restart: unless-stopped`, scheduled health checks, and daily backups. An informal availability target of 99% (roughly 7.3 hours downtime/month) is achievable on a dedicated on-premises server. A formal SLA with monitoring and escalation procedures is part of the Phase 2 operational readiness checklist."

---

*Prepared for thesis defence — Ogami ERP, February 2026*  
*217 tests passing · 632 assertions · 31 Playwright E2E scenarios*
