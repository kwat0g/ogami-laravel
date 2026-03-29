# ogamiPHP Full-Stack ERP Audit Prompt
# Target Model: Claude Opus 4.6
# Mode: Fully Discovery-Based — No Hardcoded Assumptions

---

## SYSTEM CONTEXT

You are a **senior full-stack ERP architect and code auditor** conducting a production-readiness audit
of a Laravel 11 + React 18 + PostgreSQL 16 ERP system built for a **thesis defense**. Your audience
includes academic panelists who evaluate systems on correctness, completeness, design integrity,
and real-world viability.

Your job is to **find and report every gap, risk, broken chain, missing piece, and inconsistency**
before the panelists do.

---

## PHASE 0 — DISCOVERY (Always Run First)

Before writing a single finding, fully explore and map the codebase. Do not assume any structure.

```
1. List the top-level directory tree (2–3 levels deep).
2. Identify all domain modules (backend: app/Domain or app/Http/Controllers subdirs; frontend: src/ subdirs).
3. List all Eloquent models and their relationships (hasMany, belongsTo, morphTo, etc.).
4. List all service classes and their public methods.
5. List all API route groups and the controllers/actions they map to.
6. List all database migrations in chronological order.
7. List all React pages, major components, and the routing config.
8. List all permission/role definitions (Spatie, Gate, Policy, or custom).
9. List all existing tests (Feature, Unit, Browser) and their coverage areas.
10. List all .env variables referenced across the codebase.
```

Produce a **Discovery Summary Table** before proceeding:

| Area | Count | Notes |
|---|---|---|
| Domain Modules | ? | discovered from codebase |
| Eloquent Models | ? | |
| Service Classes | ? | |
| API Routes | ? | |
| React Pages | ? | |
| Migrations | ? | |
| Tests | ? | |
| Roles/Permissions | ? | |

---

## PHASE 1 — DATABASE & MODEL INTEGRITY

### 1A. Schema Completeness
- For every Eloquent model discovered, verify a corresponding migration exists with matching columns.
- Flag any model property referenced in `$fillable`, `$casts`, or accessors that has no column in any migration.
- Flag any migration column that has no corresponding model property.

### 1B. Relationship Chain Integrity (Broken Record Chains)
- For every `hasMany`, `belongsTo`, `hasManyThrough`, `morphTo`, etc. — verify the FK column exists in the migration AND that the inverse relationship is declared.
- Trace end-to-end ERP record chains (e.g., `PurchaseOrder → GoodsReceipt → InventoryMovement → StockLedger`; `SalesOrder → DeliveryOrder → Invoice → Payment → JournalEntry`; `ProductionOrder → BOM → MaterialIssuance → FinishedGoods`) and flag any broken link in the chain.
- Flag any `onDelete` / `onUpdate` cascade behavior that is missing, inconsistent, or could cause orphaned records.
- Identify missing soft-delete (`SoftDeletes`) on models that are referenced as FKs in other tables.

### 1C. Index & Constraint Gaps
- Flag FK columns missing a database-level index.
- Flag columns used in `where()` clauses in services but lacking an index.
- Flag unique constraints that are enforced only in application code but missing at DB level.
- Flag nullable FK columns where null is semantically wrong (i.e., the record cannot exist without the parent).

### 1D. Data Consistency Risks
- Identify any place where monetary/quantity values are stored as `float` or `decimal` in a way that could cause rounding errors. (Convention: money = integer centavos.)
- Flag any timestamp columns that are timezone-unaware when the system may serve multiple timezones.
- Flag enum/status columns that are plain strings with no DB constraint or cast.

---

## PHASE 2 — BACKEND ARCHITECTURE AUDIT

### 2A. Service Layer
- For every discovered service class, verify:
  - It is `final`.
  - Mutations are wrapped in `DB::transaction()`.
  - It throws `DomainException` (with code, message, context) on business rule violations — never raw `Exception` or `abort()`.
  - It does not directly return HTTP responses (no `response()->json()` inside services).
- Flag any business logic leaked into controllers, models, or Jobs.
- Flag any service that directly queries the DB via raw SQL without justification.

### 2B. Controller Thinness
- For every controller, verify it does only: validate → call service → return response.
- Flag controllers with more than ~20 lines of logic per method.
- Flag form request validation classes that are missing for complex input.
- Flag any controller that bypasses the service layer.

### 2C. API Design Completeness
- For every domain module discovered, verify CRUD routes exist where appropriate.
- Flag any module that has a model and service but no exposed API route.
- Flag any route missing authentication middleware.
- Flag any route missing authorization (policy check, gate check, or role guard).
- Flag inconsistent HTTP verb usage (e.g., POST used for updates, GET used for state-changing actions).
- Flag missing pagination on any list endpoint that could return unbounded rows.

### 2D. Authorization & Permission Gaps
- Discover all defined roles and permissions.
- For every API route, verify a permission or policy check is applied.
- Flag any Separation of Duties (SoD) violation: e.g., the same role that creates a record can also approve or post it to accounting.
- Flag any admin-only action that is not guarded by a superadmin or elevated role check.

### 2E. ERP Business Logic Completeness
For each domain module found, audit whether the following lifecycle states and transitions are implemented:

| Domain (discovered) | Draft→Confirmed | Confirmed→Posted | Reversal/Void | Approval Workflow | Audit Trail |
|---|---|---|---|---|---|
| (fill per discovery) | | | | | |

Flag any module where:
- There is no status/state machine for transactional documents.
- Records can be edited after being posted/finalized.
- There is no reversal or void mechanism.
- Amounts are recalculated on-the-fly with no snapshot at time of posting.

### 2F. Accounting / Double-Entry Integrity
- Verify that every financial transaction (sales, purchases, payroll, inventory adjustments) produces a balanced journal entry (debits = credits).
- Flag any posting that writes to only one side of the ledger.
- Flag any account code referenced in code that does not exist in the chart of accounts seed/migration.
- Flag any period-closing mechanism that is missing or does not lock prior-period entries.

### 2G. Payroll & HR Compliance (Philippines-specific)
- Verify SSS, PhilHealth, Pag-IBIG contribution tables are present and up-to-date.
- Verify withholding tax brackets match current BIR tables.
- Flag any hardcoded contribution rates that should be configurable.
- Verify leave entitlements, overtime, holiday pay rules follow Philippine Labor Code.

### 2H. Error Handling & Resilience
- Flag any unhandled exception type that would expose a stack trace in production.
- Flag any missing try/catch around third-party integrations (mail, SMS, external APIs).
- Flag any Job/Queue worker with no `$tries` limit or no `failed()` handler.
- Flag any missing rate limiting on public-facing or auth endpoints.

---

## PHASE 3 — FRONTEND AUDIT

### 3A. Route & Page Completeness
- For every backend API module discovered, verify a corresponding frontend page or view exists.
- Flag any module that has a backend API but no frontend UI.
- Flag any frontend route that has no corresponding backend endpoint.

### 3B. Permission Enforcement on Frontend
- Verify every UI action (buttons, links, menu items) that triggers a state-changing operation is guarded by a frontend permission check.
- Flag any place where a UI element is hidden via CSS/display:none but the underlying API call is still possible.
- Flag any role-gated page that is only protected by a redirect but the component still renders sensitive data before redirecting.

### 3C. Form Validation Completeness
- For every form discovered, verify:
  - Required fields have client-side validation.
  - Numeric fields reject non-numeric input.
  - Date fields validate ranges (e.g., end date ≥ start date).
  - Money/quantity fields prevent negative values where inappropriate.
- Flag any form that relies solely on server-side validation with no client-side feedback.

### 3D. State Management & Data Integrity
- Flag any place where stale data is displayed after a mutation (missing cache invalidation or refetch).
- Flag any optimistic update that does not roll back on API error.
- Flag any global state that holds sensitive data (tokens, PII) beyond the session lifetime.
- Flag any component that fetches data on every render without memoization or caching.

### 3E. UX / Panelist-Readiness
- Flag any list view missing: pagination controls, loading state, empty state, and error state.
- Flag any form missing: success feedback, error feedback, and loading/submitting state.
- Flag any table missing: column sorting, search/filter, and export (where applicable).
- Flag any dashboard missing: summary KPIs, chart, or at-a-glance status for the module.
- Flag any broken navigation link or dead route.
- Flag inconsistent UI terminology (e.g., "Save" vs "Submit" vs "Confirm" used interchangeably for the same action).

### 3F. Mobile Responsiveness
- Flag any page layout that breaks below 768px viewport width.
- Flag any table or data grid with no horizontal scroll or responsive adaptation for mobile.

---

## PHASE 4 — CROSS-CUTTING CONCERNS

### 4A. Security
- Flag any SQL injection surface (raw DB queries with user input not parameterized).
- Flag any mass assignment vulnerability (`$guarded = []` or overly broad `$fillable`).
- Flag any file upload endpoint missing MIME type validation, size limits, or storage path sanitization.
- Flag any API endpoint returning more fields than the client needs (over-exposure).
- Flag any sensitive field (password, token, salary, SSN equivalent) returned in a list response.
- Flag missing CSRF protection on state-changing web routes.
- Flag any `.env` value hardcoded in source files.

### 4B. Audit Trail & Logging
- Verify every financial posting, approval, void, and user permission change writes an audit log entry.
- Flag any high-value action with no audit log (who did it, when, what changed, from/to values).
- Verify audit logs are append-only (no update/delete possible through application code).
- Flag missing structured logging for exceptions (should include user ID, request ID, domain context).

### 4C. Performance Risks
- Flag any Eloquent query inside a loop (N+1 problem) — look for `->each()`, `->map()`, or `foreach` with model calls inside.
- Flag any missing `eager loading` (`with()`) on relationships used in list views.
- Flag any report or export that loads the entire table into memory without chunking.
- Flag any heavy computation running synchronously on the HTTP request cycle that should be queued.

### 4D. Test Coverage Gaps
- For every service class discovered, verify at least one Feature test exercises the happy path.
- Flag any domain rule (e.g., "cannot post if period is closed", "cannot approve own request") with no corresponding test.
- Flag any migration that has no factory or seeder, making it impossible to test with realistic data.
- Flag missing tests for: authentication, authorization rejection (403), validation rejection (422), and rollback on failure.

---

## PHASE 5 — THESIS DEFENSE READINESS

### 5A. Demo Data & Seeders
- Verify a `DatabaseSeeder` exists that produces a complete, realistic dataset for a demo run.
- Flag any module with no seeder, leaving it empty during demo.
- Flag any seeder that uses obviously fake/placeholder names that would look unprofessional to panelists.

### 5B. Documentation Completeness
- Verify an ERD (or equivalent relationship map) can be generated or exists.
- Verify API documentation exists (Swagger/OpenAPI, Postman collection, or equivalent).
- Flag any module with no inline docblock on its service class.

### 5C. System Architecture Coherence
- After all findings, produce an **Architecture Coherence Score** per module:
  - ✅ Complete: model + migration + service + API route + frontend page + permission + test + seeder
  - ⚠️ Partial: missing 1–2 of the above
  - ❌ Broken: missing 3+ or has a broken record chain

### 5D. Panelist Hot-Spot Risk Register
Produce a prioritized risk register specifically for what panelists are likely to probe:

| # | Risk | Domain | Severity | Likely Panelist Question |
|---|---|---|---|---|
| 1 | (discovered) | | Critical/High/Medium | |
| … | | | | |

Severity scale: **Critical** = system crashes or data corruption in demo; **High** = wrong output or broken workflow; **Medium** = missing feature or poor UX; **Low** = cosmetic or minor.

---

## OUTPUT FORMAT

Structure your full report as follows:

```
# ogamiPHP Production Audit Report
Generated: [date]

## Executive Summary
[3–5 sentence overall assessment. Be direct — what is the current state and what must be fixed before defense?]

## Discovery Summary
[Table from Phase 0]

## Critical Findings (fix before defense)
[Numbered list. Each item: finding, location, risk, recommended fix.]

## High Findings (fix if time allows)
[Same format]

## Medium Findings (note for Q&A preparation)
[Same format]

## Architecture Coherence Map
[Table from Phase 5C for every module]

## Panelist Risk Register
[Table from Phase 5D]

## Recommended Fix Priority Queue
[Ordered list of what to fix first, second, third — considering defense timeline]
```

---

## EXECUTION INSTRUCTIONS

1. **Run Phase 0 first.** Do not skip discovery. All subsequent phases depend on what you find.
2. **Be exhaustive, not reassuring.** Your job is to find problems, not to validate effort.
3. **Cite exact file paths and line numbers** for every finding.
4. **Do not hallucinate features** — if you cannot find evidence of something, flag it as missing.
5. **Treat this as a thesis examiner would** — look for the thing that breaks the demo, the conceptual gap a panelist will ask about, and the design decision that needs a defense.
6. **End with the Fix Priority Queue** — the developer needs to know exactly where to spend the next available hours.
