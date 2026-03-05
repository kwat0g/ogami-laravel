# Phase 1A — Foundation
## Sprints 1–4 · Weeks 1–8

**Goal:** Establish the entire technical foundation before writing any business logic — Docker infrastructure, Laravel domain scaffold, authentication, all configuration tables, and the React frontend shell.

---

## Sprint 1 — Infrastructure & Project Scaffold
### Weeks 1–2

### What was built

**Docker Compose Infrastructure**

Multi-stage `Dockerfile` with four build stages:
- `base` — shared PHP 8.3 foundation (extensions, Composer)
- `development` — PHP built-in server (`artisan serve`), volume-mounted source for hot reload
- `frontend-builder` — Node 20 stage that compiles Vite assets
- `production` — Nginx + PHP-FPM + Supervisor; pre-compiled assets baked in from `frontend-builder`

`docker-compose.yml` defines six services:

| Service | Image | Port | Purpose |
|---|---|---|---|
| `app` | Custom (PHP 8.3) | 8000 | Laravel application |
| `postgres` | `postgres:16-alpine` | 5432 | Primary database |
| `redis` | `redis:7-alpine` | 6379 | Queues, sessions, cache |
| `horizon` | Custom (PHP 8.3) | — | Queue monitor |
| `reverb` | Custom (PHP 8.3) | 8080 | WebSocket server |

`APP_BUILD_TARGET` environment variable switches between targets — the same `docker-compose.yml` is used in development and production.

**Laravel 11 Scaffold**

Domain-Driven directory structure established from day one:

```
app/
├── Domains/          One directory per bounded context
├── Infrastructure/   Cross-cutting middleware, console commands
├── Http/             Controllers (thin — delegate to domain services)
└── Shared/           Contracts (interfaces), ValueObjects, Exceptions
```

All `app/Http/Controllers` follow the thin-controller principle: they resolve the FormRequest, call the domain Service, and return a JSON response. No business logic in controllers.

**Core Migrations (Sprint 1)**

| Migration | Tables created |
|---|---|
| `create_users_table` | `users` (Laravel base) |
| `create_cache_table` | `cache`, `cache_locks` |
| `create_jobs_table` | `jobs`, `failed_jobs`, `job_batches` |
| `create_permission_tables` | `permissions`, `roles`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (Spatie) |
| `create_personal_access_tokens_table` | `personal_access_tokens` (Sanctum) |
| `create_pulse_tables` | Laravel Pulse monitoring tables |
| `create_audits_table` | `audits` (owen-it/laravel-auditing) |

**Sanctum Authentication**

Laravel Sanctum configured for dual-mode auth:
- SPA session auth (cookie-based XSRF for the React frontend)
- API token auth (Sanctum PATs for mobile/integration client access)

MFA foundation added: `mfa_enabled`, `mfa_secret` columns on `users` table; TOTP enroll and verify endpoints scaffolded.

---

## Sprint 2 — RBAC, SoD, Audit Trail, State Machines
### Weeks 3–4

### What was built

**Role-Permission System (Spatie Laravel-Permission)**

Five roles seeded — authority levels only, not department labels:

| Role | Description |
|---|---|
| `admin` | Full system access |
| `executive` | Cross-department read-only (reports, dashboards) |
| `manager` | Full CRUD + approve across all modules (dept-scoped by RDAC) |
| `supervisor` | View + create + submit; no approve/post/delete |
| `staff` | Self-service: own payslip, own leave, own profile |

**Role-Restricted Department Access Control (RDAC)**

`user_department_access` pivot table links users to departments. A Laravel Global Scope (`DepartmentScope`) is applied to all HR queries — users can only see employees from their authorized departments. `admin` and `executive` roles bypass the scope.

**Separation of Duties (SoD) Engine**

`SodMiddleware` + conflict matrix stored in `system_settings`. The conflict matrix defines 10 SoD rules:

| Rule | Description |
|---|---|
| SOD-001 | Employee record creator cannot approve the same record |
| SOD-002 | Leave requester cannot approve their own request |
| SOD-003 | Overtime requester cannot approve their own request |
| SOD-004 | Loan applicant cannot approve their own loan |
| SOD-005 | Payroll run creator cannot lock the same run |
| SOD-006 | Payroll lock approver cannot be the run creator |
| SOD-007 | Payroll run approver cannot be the run creator |
| SOD-008 | Journal entry creator cannot post the same entry |
| SOD-009 | AP invoice creator cannot approve the same invoice |
| SOD-010 | AP payment creator cannot create the corresponding JE |

SoD checks are enforced at the **service layer** and throw `SodViolationException` (ex: `JE-010`) with a machine-readable code. All 10 rules have passing negative-path feature tests in `tests/Feature/Auth/SodTest.php` and `tests/Feature/AccessControl/RbacTest.php`.

**Immutable Audit Trail**

`owen-it/laravel-auditing` applied to all critical Eloquent models. Every create/update/delete records:
- `user_id`, `event`, `auditable_type`, `auditable_id`  
- `old_values`, `new_values` (JSON column, diff only)
- `ip_address`, `user_agent`

Database-level protection: `audit_logs` has no `UPDATE` or `DELETE` grants for the application DB user (`ogami_app`).

**Base State Machine**

Abstract `StateMachineContract` interface with `transition(string $to, array $context): void` enforces consistent state management across all domain models. First implementation: `EmployeeStateMachine` with states `pending_onboarding → active → on_leave → suspended → terminated`.

---

## Sprint 3 — Configuration Tables & Seeders
### Weeks 5–6

### What was built

This sprint established the single most important principle: **Zero Hardcoding**. All Philippine government rates and HR policy values live in database tables with `effective_date`, not in PHP constants.

**Configuration Tables Created**

| Table | Content | Seeded rows |
|---|---|---|
| `system_settings` | App-level config (company name, tax ID, payroll cutoffs, pay schedule) | ~40 settings |
| `train_tax_brackets` | TRAIN Law (RA 10963) 6 annual income brackets with `base_tax`, `marginal_rate`, `effective_from` | 6 rows (2023 rates) |
| `sss_contribution_tables` | SSS MSC table per MC-003-2021: `range_from`, `range_to`, `ee_contribution`, `er_contribution` | 55 salary brackets |
| `philhealth_premium_tables` | PhilHealth Circular 2022-005: `coverage_year`, `premium_rate`, `monthly_cap` | 1 active row |
| `pagibig_contribution_tables` | Pag-IBIG MRD 2012-001: `range_to`, `ee_rate`, `er_rate`, `max_ee`, `max_er` | 1 active row |
| `minimum_wage_rates` | Regional daily minimum wage by region code and effective date | 2 rows (NCR + Region IVA) |
| `overtime_multiplier_configs` | Multipliers per OT type (regular day, rest day, special holiday, regular holiday, etc.) | 6 rows |
| `holiday_calendars` | PH declared holidays 2024–2026 | ~60 rows |
| `ewt_rate_configs` | BIR expanded withholding tax ATC codes and applicable rates | ~15 ATC codes |

**SystemSettingsService**

Type-safe accessor: `SystemSettingsService::get(string $key, mixed $default)` casts values to the declared type (`integer`, `float`, `boolean`, `json`) on retrieval. No raw `DB::table('system_settings')` calls outside this service.

**Why this matters:** When SSS changed contribution tables in January 2024, the update required inserting a new row with the new `effective_date` — no code change, no redeployment. The payroll engine reads the applicable rate for a given cutoff date at runtime.

---

## Sprint 4 — React Frontend Scaffold
### Weeks 7–8

### What was built

**Tech stack initialized:**
- Vite 5 + React 18 + TypeScript 5
- Tailwind CSS 3 (utility-first styling)
- shadcn/ui (Radix UI primitives — accessible, unstyled by default)
- React Router v6 with nested layouts
- TanStack Query 5 (server state — caching, stale-while-revalidate)
- TanStack Table 8 (data tables with sorting, filtering, pagination)
- Zustand 4 (client UI state: auth user, sidebar open/close, notifications)
- React Hook Form 7 + Zod 3 (all forms validated client-side before submission)
- Framer Motion 11 (micro-animations, 150ms max, layout transitions only)
- Sonner (toast notifications)

**Design system tokens** (`tailwind.config.ts`):
- Color palette: neutral slate base + brand blue `#2563EB` accent
- Typography: Inter font, 5-step type scale
- Spacing: 4px grid
- Border radius: `sm` (4px), `md` (8px), `lg` (12px), `xl` (16px)

**Application shell components:**
- `AppShell` — sidebar + header + main content area
- `Sidebar` — role-filtered navigation (links hidden if user lacks permission)
- `Header` — user avatar, notification bell, command palette trigger (Ctrl+K)
- `CommandPalette` — global search using `cmdk`
- `NotificationDrawer` — real-time Reverb WebSocket notifications

**Authentication screens:**
- `/login` — email/password form with throttle error feedback
- `/mfa` — TOTP 6-digit entry with countdown
- `/forgot-password` — email request form
- `/reset-password/:token` — new password form with strength meter

**`usePermission(permission: string): boolean` hook** — reads user roles/permissions from Zustand auth store for conditional UI rendering.

**Vite proxy configuration:**
```
/api/* → http://localhost:8000
/sanctum/* → http://localhost:8000
/reverb/* → ws://localhost:8080
```
The React dev server runs **outside** Docker for fast HMR; it proxies all backend requests to the Dockerized Laravel server.

---

## Phase 1A Summary

| Item | Delivered |
|---|---|
| Docker Compose (dev + prod targets) | ✅ |
| Laravel 11 DDD directory structure | ✅ |
| 5 roles + permission matrix seeded | ✅ |
| SoD conflict matrix (10 rules) | ✅ |
| Immutable audit trail | ✅ |
| Base state machine contract | ✅ |
| 7 government rate config tables | ✅ |
| Zero-hardcoding: all rates in DB | ✅ |
| React frontend scaffold + design system | ✅ |
| Authentication screens (login, MFA, password reset) | ✅ |
| RDAC Global Scope | ✅ |

---

*Next: [Phase 1B — HR Module](phase-1b-hr-module.md)*
