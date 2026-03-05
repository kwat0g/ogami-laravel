# Ogami ERP — Phase 1 Complete Documentation Index

**Project:** Ogami Manufacturing Philippines Corp. Enterprise ERP System  
**Phase:** Phase 1 — HR Module + Accounting Module  
**Duration:** Weeks 1–36 (18 Sprints)  
**Completed:** February 25, 2026  
**Tag:** `v1.0.0`

---

## Project Overview

Ogami ERP is an on-premises enterprise resource planning system built for Ogami Manufacturing Philippines Corp. (OMPC), a plastic parts manufacturer supplying Honda Philippines and Mitsuba Philippines. The system replaces all manual Excel-based HR and accounting processes with a structured, auditable, rules-based ERP running entirely on the company's local area network (LAN).

**Scale:** ~400 employees · 11 departments · Semi-monthly payroll (24 cycles/year)  
**Architecture:** Modular Monolith → Microservices-Ready · Domain-Driven Design  
**Stack:** PHP 8.3 + Laravel 11 · React 18 + TypeScript · PostgreSQL 16 · Redis 7 · Docker Compose

---

## Documentation Files

| File | Phase | Sprints | Topics |
|---|---|---|---|
| [phase-1a-foundation.md](phase-1a-foundation.md) | 1A | 1–4 | Infrastructure, auth, config tables, React scaffold |
| [phase-1b-hr-module.md](phase-1b-hr-module.md) | 1B | 5–8 | Employee, attendance, leave, loans |
| [phase-1c-payroll-engine.md](phase-1c-payroll-engine.md) | 1C | 9–12 | Payroll computation, reports, government forms |
| [phase-1d-accounting-module.md](phase-1d-accounting-module.md) | 1D | 13–16 | GL, AP, AR, tax, financial statements |
| [phase-1e-qa-security-launch.md](phase-1e-qa-security-launch.md) | 1E | 17–18 | Testing, security, UAT, go-live |

---

## Final Metrics (v1.0.0)

| Metric | Result |
|---|---|
| Automated tests | **222 passing** · 1 skipped · 656 assertions |
| Test failures | **0** |
| OWASP ZAP scan | **0 HIGH · 0 MEDIUM** · 6 LOW/INFO (all accepted) |
| API response times | **All < 200ms** (target: < 3s) |
| UAT scenarios passed | **3 / 3** |
| Backup & restore | **PASS** — 24/24 golden tests on restored DB |
| DB migrations | **39 migrations** — all applied cleanly |
| Domains implemented | 9 (HR, Attendance, Leave, Loan, Payroll, Tax, Accounting, AP, AR) |

---

## Architecture Summary

```
app/
├── Domains/
│   ├── HR/              Employee lifecycle, department, position management
│   ├── Attendance/      Time logs, shift assignments, overtime
│   ├── Leave/           Leave types, balances, request workflow
│   ├── Loan/            Loan types, applications, amortization schedules
│   ├── Payroll/         Computation pipeline, payroll runs, payslips
│   ├── Tax/             TRAIN law brackets, BIR forms, Alphalist
│   ├── Accounting/      GL, journal entries, fiscal periods, COA, reports
│   ├── AP/              Vendor invoices, payments, aging, Form 2307
│   └── AR/              Customer invoices, VAT ledger, tax summaries
│
├── Infrastructure/
│   ├── Middleware/      SecurityHeadersMiddleware, SodMiddleware
│   ├── Console/         VerifyBackupCommand, scheduled jobs
│   └── Exceptions/      DomainException, SodViolationException
│
└── Shared/
    ├── Contracts/       ServiceContract, StateMachineContract
    └── ValueObjects/    Money, PayPeriod, WorkingDays
```

```
frontend/
├── src/
│   ├── domains/         Feature modules mirroring backend domains
│   ├── components/      shadcn/ui + custom shared components
│   ├── layouts/         AppShell, AuthLayout, DashboardLayout
│   ├── hooks/           usePermission, useAuth, useDepartmentScope
│   └── store/           Zustand stores (auth, notifications)
└── e2e/                 Playwright E2E specs (31 scenarios)
```

---

## Key Design Decisions

### Zero Hardcoding Policy
All government rates (SSS, PhilHealth, Pag-IBIG, TRAIN, holiday multipliers, minimum wage) live in versioned database tables with `effective_date`. Changing rates requires no code deployment — only a database record insert.

### Separation of Duties (SoD)
10 enforced SoD rules (SOD-001 to SOD-010) — the same employee cannot both initiate and approve any transaction in HR, Payroll, or Accounting. Violations throw `SodViolationException` at the service layer and are tested via negative-path feature tests.

### Four-Layer Validation
Every input is validated at: (1) React form (Zod), (2) API FormRequest, (3) Service layer business rules, (4) PostgreSQL constraints and triggers.

### Immutable Audit Trail
Posted journal entries cannot be modified — only reversed via a new reversing entry. The `audit_logs` table has no UPDATE or DELETE grants even for Admin. Payroll details have a PostgreSQL `BEFORE UPDATE` trigger.

### Role-Restricted Department Access (RDAC)
Users can only see employees, leave requests, and payroll data from departments they are assigned to — enforced via a Laravel Global Scope on all HR queries.

---

## Roles

The system uses **5 roles** (authority levels, not department labels). Adding a new department never requires a new role — just assign the user to the existing role that matches their authority level and add them to the department via `user_department_access`. RDAC handles all data isolation.

| Role | Scope |
|---|---|
| `admin` | Full access + user management |
| `executive` | Cross-department read-only (reports, dashboards) |
| `manager` | Full CRUD + approve (all modules, dept-scoped by RDAC) |
| `supervisor` | View + create + submit; no approve/post/delete |
| `staff` | Self-service only |

---

*Ogami ERP Phase 1 · February 2026 · v1.0.0*
