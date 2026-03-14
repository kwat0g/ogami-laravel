# 🧠 OGAMI ERP — FULL CODEBASE COMPREHENSION PROMPT
# For: GitHub Copilot Agent Mode / Cursor / Any AI with cocoindex + context7 active
# Purpose: Build a complete mental model of the entire system before doing any work
# Paste this into Agent chat before starting any implementation task

---

You are a **senior engineer onboarding onto this codebase**.
Your job is not to write code yet. Your job is to **deeply understand the entire system**
— every module, every feature, how data flows between them, how the architecture works,
and how all the pieces fit together.

Use your tools on every step:
- `cocoindex_code_search` — search the actual codebase for evidence
- `context7` — fetch official docs when you need to understand a library or pattern

At the end of this study session you will produce a **System Intelligence Report**
that captures everything you learned — written as if you are briefing a new engineer
who needs to be productive on day one.

## Ground Rules

- Search before you assume. Every claim must come from actual code you found.
- Do not invent features. If you can't find evidence of it, say so.
- Do not summarize generic Laravel/React patterns — describe THIS system's patterns.
- If you find something unexpected or unusual, highlight it.
- If you find something broken or dangerous, flag it immediately.

---

---

# PART 1 — SYSTEM ORIENTATION

---

## 1.1 — Technology Stack

Search for and confirm every technology in use:

```
cocoindex_code_search: "composer.json require laravel framework version"
cocoindex_code_search: "package.json dependencies react vite typescript"
cocoindex_code_search: "docker-compose postgres redis queue"
```

Document:
- Exact Laravel version and all major backend packages with versions
- Exact React version and all major frontend packages with versions
- Database engine and version
- Cache/queue driver
- Any unusual or noteworthy packages that define how the system works

---

## 1.2 — Project Structure

Search for the top-level architecture:

```
cocoindex_code_search: "app/Domains directory structure service"
cocoindex_code_search: "routes/api route files module"
cocoindex_code_search: "frontend/src pages components structure"
```

Document:
- How the backend is organized (DDD? MVC? hybrid?)
- How the frontend is organized (pages, components, hooks)
- How routes are organized (one file per module? centralized?)
- Where business logic lives (controllers? services? actions?)

---

## 1.3 — Core Conventions

Search for the patterns this codebase enforces:

```
cocoindex_code_search: "final class Service DB::transaction DomainException"
cocoindex_code_search: "FormRequest rules authorize controller thin"
cocoindex_code_search: "ApiResponse success data message"
cocoindex_code_search: "centavos integer money amount"
```

Document every convention you find:
- How services are structured (final? interface? transaction wrapping?)
- How controllers are written (thin? what do they delegate to?)
- How API responses are formatted (what is the standard envelope?)
- How money/currency is handled
- How errors and exceptions are thrown
- How validation is done
- Any other patterns that appear consistently across the codebase

---

## 1.4 — Authentication and Authorization Architecture

```
cocoindex_code_search: "auth middleware sanctum jwt token guard"
cocoindex_code_search: "spatie permission role hasPermissionTo can"
cocoindex_code_search: "useAuth authStore permission frontend check"
```

Document:
- How authentication works end to end (login → token → API calls)
- How permissions are checked on the backend
- How permissions are checked on the frontend
- How roles are structured and assigned
- Any multi-tenancy or company scoping

---

---

# PART 2 — MODULE DEEP DIVE

---

For each module discovered, perform a complete study.
Search deeply — don't just find the service class, find the whole picture.

**For every module, answer all of these questions:**

```
MODULE STUDY TEMPLATE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

WHAT IS THIS MODULE?
  In one paragraph, what does this module do in business terms?
  What real-world process does it automate or support?

DOMAIN MODELS
  What Eloquent models exist in this module?
  What are their key fields and relationships?
  What database tables do they map to?
  Are there any unusual database design choices?

BUSINESS LOGIC
  What does the service layer do?
  What are the main operations (CRUD + domain-specific)?
  What are the business rules enforced?
  What validation happens and where?
  What calculations are performed?

WORKFLOWS
  What are the status states and transitions?
  What triggers a state change?
  What happens at each step of the workflow?
  Who can perform each action (which role/permission)?

API SURFACE
  What endpoints exist?
  What are the inputs and outputs?
  What permissions guard each endpoint?

FRONTEND
  What pages/screens exist for this module?
  What does each page show and allow?
  What forms exist and what do they submit?
  What data is displayed and how is it fetched?

CROSS-MODULE CONNECTIONS
  What other modules does this module READ from?
  What other modules does this module WRITE to or trigger?
  What events or jobs does this module fire?
  What notifications does this module send?

REPORTS AND EXPORTS
  What reports are generated?
  What can be exported (Excel, PDF)?
  What dashboard widgets come from this module?

KNOWN GAPS OR ISSUES
  What features seem incomplete?
  What is connected on one side but not the other?
  What is missing that you would expect to be there?

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**Study every module you find. Start with these searches to discover them all:**

```
cocoindex_code_search: "app/Domains subdirectories modules list"
cocoindex_code_search: "routes/api route file module name"
cocoindex_code_search: "frontend/src/pages directory module"
cocoindex_code_search: "database/migrations table name domain"
```

---

---

# PART 3 — DATA FLOW STUDY

---

## 3.1 — Request Lifecycle

Trace a complete request from browser to database and back:

```
cocoindex_code_search: "middleware pipeline auth throttle api"
cocoindex_code_search: "controller FormRequest service repository"
cocoindex_code_search: "ApiResponse transform resource collection"
```

Document:
- What happens when a request hits the API?
- What middleware runs and in what order?
- How does data get validated?
- How does data get transformed on the way out?
- What is the exact path: Request → Middleware → Controller → Service → DB → Response

---

## 3.2 — Critical Business Workflows

Find and trace these specific flows through the code:

**Payroll Flow:**
```
cocoindex_code_search: "payroll run generate compute process steps"
cocoindex_code_search: "payroll deductions contributions BIR tax"
cocoindex_code_search: "payroll post GL journal entry accounting"
```

**Procurement Flow:**
```
cocoindex_code_search: "purchase request order approval three way matching"
cocoindex_code_search: "vendor invoice receipt matching"
```

**Production Flow:**
```
cocoindex_code_search: "production order BOM material release"
cocoindex_code_search: "production inventory stock deduction"
cocoindex_code_search: "production QC inspection quality"
```

**HR Hire-to-Retire Flow:**
```
cocoindex_code_search: "employee onboard hire position department"
cocoindex_code_search: "leave attendance overtime payroll connect"
```

For each flow, document:
- Every step in the process
- What data is created/updated at each step
- What module hands off to what other module
- Where the flow is complete vs. where it breaks or is incomplete

---

## 3.3 — Accounting Integration

```
cocoindex_code_search: "journal entry debit credit GL post"
cocoindex_code_search: "chart of accounts account code"
cocoindex_code_search: "financial report balance sheet income"
```

Document:
- What events trigger GL journal entries?
- What modules post to accounting?
- How are journal entries structured?
- What financial reports are generated?
- What is the fiscal period structure?

---

## 3.4 — Queue and Background Jobs

```
cocoindex_code_search: "job dispatch queue handle class implements"
cocoindex_code_search: "command schedule artisan daily weekly"
cocoindex_code_search: "notification send mail broadcast event"
```

Document:
- Every background job and what it does
- Every scheduled command and when it runs
- Every notification and what triggers it
- Any event listeners and what they respond to

---

---

# PART 4 — FRONTEND ARCHITECTURE STUDY

---

## 4.1 — State Management

```
cocoindex_code_search: "zustand store create auth ui state"
cocoindex_code_search: "useStore selector slice state management"
```

Document:
- What global state exists (which stores)?
- What does each store hold?
- How is state shared between components?
- What is persisted vs. ephemeral?

---

## 4.2 — API Communication Layer

```
cocoindex_code_search: "axios instance baseURL interceptor token"
cocoindex_code_search: "useQuery useMutation custom hook api"
cocoindex_code_search: "api service fetch hook pattern"
```

Document:
- How API calls are made (axios config, interceptors)
- How authentication tokens are attached
- How errors are handled globally
- The pattern used for data fetching in components

---

## 4.3 — Routing and Navigation

```
cocoindex_code_search: "react router route path lazy component"
cocoindex_code_search: "sidebar navigation menu permission guard"
cocoindex_code_search: "protected route auth redirect"
```

Document:
- How routing is organized
- How protected routes work
- How the sidebar navigation is built and what drives it
- How lazy loading is implemented

---

## 4.4 — Component Architecture

```
cocoindex_code_search: "shared components ui common reusable"
cocoindex_code_search: "table pagination datatable filter"
cocoindex_code_search: "form input field validation schema"
cocoindex_code_search: "modal dialog drawer confirm"
```

Document:
- What shared/common components exist
- What UI patterns are used consistently (tables, forms, modals)
- What third-party UI libraries are in use
- How forms are built and validated on the frontend

---

---

# PART 5 — INFRASTRUCTURE AND DEVOPS STUDY

---

```
cocoindex_code_search: "Dockerfile docker-compose build production"
cocoindex_code_search: "env environment config database redis queue"
cocoindex_code_search: "phpunit pest test feature unit"
cocoindex_code_search: "CI CD github actions pipeline deploy"
```

Document:
- How the application is containerized
- What environment variables are required
- How the test suite is structured
- What CI/CD pipelines exist
- How deployments work

---

---

# PART 6 — SECURITY STUDY

---

```
cocoindex_code_search: "csrf xss injection sanitize validate"
cocoindex_code_search: "rate limit throttle middleware"
cocoindex_code_search: "audit log owen-it activity"
cocoindex_code_search: "encrypt secret sensitive data"
```

Document:
- What security measures are in place
- What is audited and logged
- What rate limiting exists
- Any obvious security gaps

---

---

# FINAL OUTPUT — SYSTEM INTELLIGENCE REPORT

---

After completing all parts, produce this full report:

---

## Section 1 — Executive Summary

In 3–5 paragraphs:
- What is this system? What does it do?
- Who uses it and how?
- What is the overall architectural approach?
- What is the maturity level of the system?
- What are the 3 most important things a new engineer needs to know?

---

## Section 2 — Technology Stack Reference

Complete list of every technology, library, and tool with versions.

---

## Section 3 — Architecture Map

A text-based diagram showing how all layers connect:

```
[Browser / React 18]
        │
        ▼
[React Router + Auth Guard]
        │
        ▼
[Zustand Store] ←→ [Axios + Interceptors]
                              │
                              ▼
                    [Laravel API Routes]
                              │
                              ▼
                    [Middleware Pipeline]
                              │
                              ▼
                    [FormRequest Validation]
                              │
                              ▼
                    [Controller (thin)]
                              │
                              ▼
                    [Service (final class)]
                              │
                    ┌─────────┴──────────┐
                    ▼                    ▼
            [Eloquent Models]    [Jobs / Events]
                    │
                    ▼
            [PostgreSQL 16]
```

Customize this based on what you actually found.

---

## Section 4 — Module Reference Card

For every module, a concise card:

```
┌─────────────────────────────────────────────────┐
│ MODULE: [Name]                                  │
├─────────────────────────────────────────────────┤
│ Purpose:    [one line]                          │
│ Models:     [list]                              │
│ Key tables: [list]                              │
│ Status flow: [state1] → [state2] → [state3]    │
│ Connects to: [other modules it integrates with] │
│ API routes: [count] endpoints                   │
│ Frontend:   [count] pages                       │
│ Completeness: [%] — [brief status note]         │
└─────────────────────────────────────────────────┘
```

---

## Section 5 — Cross-Module Integration Map

A map showing which modules talk to which:

```
HR ──────────────► Payroll (employee data, positions)
HR ──────────────► Leave (employee eligibility)
HR ──────────────► Attendance (employee records)
Attendance ──────► Payroll (hours, tardiness)
Leave ───────────► Payroll (leave pay)
Loan ────────────► Payroll (deductions)
Procurement ─────► Inventory (stock receipts)
Procurement ─────► AP (vendor invoices)
AP ──────────────► Accounting (GL entries)
Production ──────► Inventory (material consumption) [⚠️ GAP]
Production ──────► QC (inspection gate)
...
```

Fill this in based on what you actually found.

---

## Section 6 — Critical Workflows Step by Step

For each major workflow found, document every step.

---

## Section 7 — Conventions Cheat Sheet

A quick reference card for any engineer about to write code:

```
MONEY:          Always integers in centavos. Never floats. Never decimals.
SERVICES:       final class, implements ServiceContract, DB::transaction() for writes
CONTROLLERS:    Thin. Delegate to service. Return ApiResponse.
EXCEPTIONS:     DomainException with 3 args: [message, code, context]
VALIDATION:     FormRequest classes. Never validate in controllers or services.
RESPONSES:      ApiResponse::success(data) / ApiResponse::error(message)
PERMISSIONS:    Spatie. Check with $user->can() or middleware('can:...')
AUDITING:       Auditable trait on all financial models
TESTS:          Feature tests against real PostgreSQL. Never SQLite.
EXPORTS:        maatwebsite/excel. Dedicated Export class per report.
```

---

## Section 8 — Known Gaps and Issues

Everything you found that is incomplete, broken, or missing:

```
🔴 CRITICAL: [description] — [impact]
🟠 HIGH:     [description] — [impact]
🟡 MEDIUM:   [description] — [impact]
🟢 LOW:      [description] — [impact]
```

---

## Section 9 — Questions for the Team

Everything you couldn't determine from the code alone:

```
Q1: [question] — [why it matters]
Q2: [question] — [why it matters]
```

---

*This prompt uses cocoindex to search your actual codebase on every step.*
*Nothing in the report is assumed — everything comes from evidence found in the code.*
*Run this once when onboarding to a new codebase or after a major refactor.*