# 🔍 FULL SYSTEM AUDIT PROMPT — FROM ZERO TO ARCHITECTURE

> **How to use this prompt:**  
> Paste this entire document into any AI (Claude, ChatGPT, Gemini, Cursor, etc.) alongside your codebase, README, or any relevant files.  
> The AI will answer every section exhaustively — from file naming conventions to cloud infrastructure.  
> If something doesn't apply to your stack, the AI will say so explicitly.

---

## INSTRUCTIONS TO THE AI

You are a **senior software architect and engineering lead** performing a **complete, bottom-up technical audit** of this system.

Your job is to document **everything** — from the smallest coding conventions to the largest architectural decisions. Leave nothing assumed. If something is missing, undocumented, or unclear, **flag it explicitly as a gap**.

For every section:
- Answer **each bullet point** directly and completely
- If something is **not applicable**, say `N/A — [reason]`
- If something is **unknown or undocumented**, say `UNDOCUMENTED — [what's missing]`
- If something is a **risk, smell, or anti-pattern**, prefix it with `⚠️`
- If something is **well-designed or notable**, prefix it with `✅`
- Provide **code snippets, file paths, config excerpts, or schema samples** wherever relevant

This document will become the **single source of truth** for this system.

---

---

# PART 1 — CODEBASE FOUNDATIONS
*The smallest building blocks: files, folders, naming, and conventions.*

---

## 1.1 Repository Structure

- What is the top-level folder structure of this project? List every root-level directory and what it contains.
- Is this a monorepo or polyrepo? If monorepo, what tool manages it? (Turborepo, Nx, Lerna, Yarn Workspaces, pnpm workspaces?)
- List all packages/apps/services inside the monorepo (if applicable).
- Where does application source code live vs. configuration, scripts, docs, and tests?
- Are there any auto-generated folders that should not be committed? Are they in `.gitignore`?
- What files exist at the root level and what is each one's purpose? (e.g., `package.json`, `docker-compose.yml`, `.env.example`, `Makefile`, etc.)
- Is there a `scripts/` or `bin/` folder? List every script and its purpose.

## 1.2 File & Folder Naming Conventions

- What naming convention is used for files? (camelCase, kebab-case, PascalCase, snake_case?)
- Are different conventions used for different file types? (e.g., components vs. utilities vs. configs?)
- What naming convention is used for folders?
- Are there any enforced rules via linting or tooling? (e.g., `eslint-plugin-filenames`)
- Are there inconsistencies or violations of the naming convention anywhere in the codebase?

## 1.3 Language & Runtime

- What programming language(s) are used across the project? List each with its version.
- What runtime(s) are in use? (Node.js, Bun, Deno, Python, JVM, Go, etc.) List versions.
- Is the runtime version pinned? How? (`.nvmrc`, `.tool-versions`, `Dockerfile`, `engines` in `package.json`?)
- Are multiple languages used in the same service? Why?
- What is the TypeScript configuration? (strict mode, `tsconfig.json` settings, path aliases, target/module?)
- Are there any polyfills or transpilation targets that constrain language features?

## 1.4 Package Management & Dependencies

- What package manager is used? (npm, yarn, pnpm, pip, poetry, cargo, go modules, etc.) What version?
- Is there a lockfile? Is it committed?
- How are dependency versions managed? (exact, `^`, `~`, ranges?)
- How many total dependencies (production vs. dev)?
- Are there any known outdated, deprecated, or vulnerable dependencies?
- Are there any homegrown internal packages? Where are they hosted? (private npm registry, GitHub Packages, local workspace?)
- Is there a process for auditing and updating dependencies? (`npm audit`, Dependabot, Renovate?)
- Are there any peer dependency conflicts or resolution overrides?

## 1.5 Code Style & Formatting

- What linter is used? (ESLint, Pylint, RuboCop, golangci-lint, etc.) What version?
- What formatter is used? (Prettier, Black, gofmt, rustfmt, etc.?)
- Share the full linter configuration (`.eslintrc`, `pyproject.toml`, etc.)
- Share the full formatter configuration (`.prettierrc`, etc.)
- What rules are enabled, disabled, or customized?
- Are linting and formatting enforced at commit time? (Husky, lint-staged, pre-commit hooks?)
- Are there any areas of the codebase that bypass linting (`eslint-disable`, `# noqa`, etc.)? How many?
- Is there a `.editorconfig`? What are its settings?

## 1.6 Git & Version Control

- What branching strategy is used? (Gitflow, trunk-based, GitHub Flow, etc.)
- What are the long-lived branches? What does each represent?
- What is the branch naming convention for feature, fix, hotfix, release branches?
- Is branch protection configured? On which branches? What rules are enforced?
- What is the commit message convention? (Conventional Commits, custom, none?)
- Is commit message format enforced? (commitlint, commitizen?)
- How are pull requests/merge requests structured? Is there a PR template?
- What is the required number of approvals before merging?
- Are there any squash/rebase/merge policies?
- How is the git history? (clean linear history, messy merge commits, etc.)

---

---

# PART 2 — CONFIGURATION & ENVIRONMENT
*How the app is configured, across environments.*

---

## 2.1 Environment Variables

- List **every** environment variable used in the project with:
  - Variable name
  - Purpose / description
  - Type (string, number, boolean, URL, etc.)
  - Whether it is required or optional
  - Default value (if any)
  - Which environment(s) it applies to
- Is there a `.env.example` or `.env.schema` file? Is it up to date?
- How are environment variables validated at startup? (Zod, Joi, `envalid`, manual checks, none?)
- What happens if a required env var is missing? Does the app crash loudly or silently misbehave?
- Are any secrets committed to the repo? (⚠️ critical risk if yes)

## 2.2 Configuration Management

- Is there a centralized config module? Where does it live?
- How is environment-specific config handled? (separate files, env var overrides, config service?)
- Are feature flags managed via config? How?
- Is there any dynamic or remote config? (LaunchDarkly, ConfigCat, Firebase Remote Config?)
- How are non-secret config values stored and versioned?

## 2.3 Multi-Environment Setup

- List all environments (local, dev, staging, production, preview, etc.)
- What differs between environments? (DB, API URLs, feature flags, log levels, etc.)
- How are environment-specific configs deployed?
- Is there environment parity? Are there known differences between staging and production that could cause bugs?

---

---

# PART 3 — FRONTEND
*UI layer, components, state, routing, and assets.*

---

## 3.1 Framework & Tooling

- What frontend framework is used? (React, Vue, Angular, Svelte, SolidJS, etc.) What version?
- Is there a meta-framework? (Next.js, Nuxt, SvelteKit, Remix, Astro?) What version?
- What rendering strategy is used per page/route? (CSR, SSR, SSG, ISR, hybrid?)
- What is the build tool? (Vite, Webpack, esbuild, Parcel, Turbopack?) What version?
- What is the dev server setup?
- What is the bundle output structure?

## 3.2 Project Structure (Frontend)

- What is the folder structure inside the frontend source?
- How are components organized? (by feature, by type, atomic design, etc.)
- How are pages/routes organized?
- Where do shared utilities, hooks, types, and constants live?
- Is there a barrel file (`index.ts`) pattern? Is it consistent?
- Are there any circular dependency issues?

## 3.3 Component Architecture

- What component library is used (if any)? (shadcn/ui, MUI, Chakra, Ant Design, etc.)
- Are there custom base/design-system components? Where do they live?
- What is the component composition pattern? (compound components, render props, HOC, hooks?)
- How are component props typed?
- Are components documented? (Storybook, JSDoc, etc.)
- How is component reuse enforced vs. duplication avoided?

## 3.4 Styling

- What styling approach is used? (Tailwind CSS, CSS Modules, styled-components, Emotion, plain CSS, SASS, etc.)
- How is the design system/theme defined? (CSS variables, Tailwind config, theme object?)
- Is there a dark mode? How is it implemented?
- How is responsive design handled? What breakpoints are defined?
- Are there any global styles? Where are they applied?
- How are animations and transitions handled?

## 3.5 State Management

- What state management solution(s) are used? (Redux Toolkit, Zustand, Jotai, Recoil, MobX, XState, Context API, etc.)
- What kind of state lives where? (server state vs. UI state vs. form state vs. global state?)
- How is server/async state managed? (React Query, SWR, Apollo Client, RTK Query, tRPC?)
- How is form state managed? (React Hook Form, Formik, Zod schema, etc.)
- Are there any known state synchronization issues or race conditions?

## 3.6 Routing

- What routing library is used? (Next.js App Router, React Router, TanStack Router, etc.)
- List all routes/pages in the application with a brief description of each.
- How are dynamic routes handled?
- How is authentication-gated routing handled? (middleware, protected route wrappers, etc.)
- How are 404 and error pages handled?
- Is there any route-based code splitting?

## 3.7 API Communication (Frontend)

- How does the frontend communicate with the backend? (REST, GraphQL, tRPC, WebSocket, etc.)
- Is there a centralized API client or are fetch calls scattered?
- How are API errors handled and displayed to the user?
- How are loading states managed?
- Is there optimistic UI anywhere? Where?
- How are authentication tokens attached to requests?
- Is there request caching? What is the invalidation strategy?

## 3.8 Performance (Frontend)

- How are images optimized? (next/image, lazy loading, WebP, CDN?)
- Is there code splitting? At what level? (route, component, library?)
- What are the Core Web Vitals scores? (LCP, FID/INP, CLS)
- Are there any known performance bottlenecks in the UI?
- Is there a bundle size budget? What is the current bundle size?
- Are web workers used anywhere?

## 3.9 Accessibility & Internationalization

- What WCAG compliance level is targeted?
- Are ARIA attributes used correctly?
- Is keyboard navigation fully supported?
- Is the application internationalized (i18n)? What library? (next-i18next, i18next, react-intl?)
- What languages are supported?
- How are translations managed and updated?

---

---

# PART 4 — BACKEND
*Server logic, modules, services, and business rules.*

---

## 4.1 Framework & Architecture Pattern

- What backend framework(s) are used? (Express, NestJS, Fastify, Hono, Django, FastAPI, Laravel, Rails, etc.) What version?
- What architectural pattern is followed? (MVC, Clean Architecture, Hexagonal/Ports & Adapters, DDD, layered, flat?)
- How is the codebase organized at the top level?
- Is the backend a monolith, modular monolith, or microservices?
- Are domain boundaries clearly defined? If so, list each domain/module and its responsibility.

## 4.2 Module Breakdown

For **each major module/domain**, provide:
- Module name
- Responsibility and business purpose
- Key entities it owns
- Exposed interfaces (API endpoints, events emitted, events consumed)
- Internal layers (controller → service → repository pattern, or equivalent)
- External dependencies (other modules, third-party services)
- Known complexity or technical debt

## 4.3 Request Lifecycle

- Walk through the **complete lifecycle of a typical HTTP request**, from network entry to response:
  1. Ingress (load balancer / reverse proxy / CDN)
  2. Middleware chain (what runs on every request?)
  3. Authentication/Authorization check
  4. Route matching
  5. Input validation
  6. Business logic / service layer
  7. Data access / repository layer
  8. Response serialization
  9. Error handling
  10. Logging / observability hooks
- Are there any hooks, interceptors, or decorators that run globally?

## 4.4 Validation & Input Handling

- Where is input validation performed? (controller, middleware, service layer, all three?)
- What library is used for schema validation? (Zod, Joi, class-validator, Pydantic, etc.)
- How are validation errors formatted and returned to clients?
- Is there protection against mass assignment? How?
- How is file upload handled and validated?

## 4.5 Error Handling

- Is there a global error handler? Where is it defined?
- What is the error response format? Provide an example.
- How are operational errors (expected) vs. programmer errors (unexpected) differentiated?
- Are stack traces ever returned to clients? (⚠️ risk in production)
- How are async errors caught? (try/catch, `asyncWrapper`, framework-level?)
- How are unhandled promise rejections and uncaught exceptions handled?

## 4.6 Business Logic Layer

- Where does business logic live? (service classes, use case files, domain models?)
- How are transactions managed across multiple operations?
- How is domain validation (business rules) separated from input validation (schema)?
- Are there any complex workflows or multi-step processes? Describe each.
- How are side effects (emails, notifications, webhooks) triggered from business logic?

## 4.7 Scheduled Jobs & Background Tasks

- List every scheduled/cron job with:
  - Name / identifier
  - Schedule (cron expression)
  - What it does
  - What happens if it fails
  - How long it typically runs
  - Whether it is idempotent
- Are jobs distributed/locked to prevent running on multiple instances simultaneously?

## 4.8 WebSockets / Real-Time

- Is real-time communication used? What library? (Socket.IO, ws, SSE, etc.)
- What events are emitted server → client?
- What events are received client → server?
- How are WebSocket connections authenticated?
- How are disconnects and reconnects handled?
- How does real-time scale across multiple server instances? (Redis pub/sub, sticky sessions?)

---

---

# PART 5 — API LAYER
*Contracts, versioning, auth, and integrations.*

---

## 5.1 API Style & Design

- What API paradigm is used? (REST, GraphQL, gRPC, tRPC, SOAP, JSON-RPC?)
- Is there an API specification? (OpenAPI/Swagger, GraphQL schema, Protobuf?)
- Is the spec auto-generated from code or manually maintained?
- Is the spec publicly accessible or used for testing?

## 5.2 REST Endpoints (if applicable)

List all API endpoints in the following format:

```
METHOD /path/to/endpoint
- Auth required: yes/no
- Roles allowed: [list]
- Request body: [schema or description]
- Query params: [list]
- Response: [schema or description]
- Side effects: [what changes]
```

Group by resource/module.

## 5.3 GraphQL (if applicable)

- List all Queries with their return types and arguments.
- List all Mutations with their inputs and return types.
- List all Subscriptions.
- How is the schema organized? (schema-first vs. code-first?)
- How is N+1 solved? (DataLoader, query batching?)
- How is query depth/complexity limited?

## 5.4 API Versioning

- How is API versioning handled? (URL prefix `/v1/`, headers, query params, none?)
- What is the current API version?
- Is there a deprecation policy for old versions?
- Are there any breaking changes planned?

## 5.5 Authentication & API Security

- How are API requests authenticated? (JWT Bearer, session cookie, API key, OAuth2, mTLS?)
- Where are tokens validated? (middleware, gateway, every endpoint?)
- What is the token expiry? How are tokens refreshed?
- How are API keys scoped and rotated?
- Is there rate limiting? What library? What are the limits?
- Is there IP allowlisting/blocklisting anywhere?
- Are CORS policies configured? What origins are allowed?
- Are security headers set? (Helmet.js or equivalent?)

## 5.6 Third-Party Integrations

For **each external service integrated**, provide:
- Service name and purpose
- How credentials are stored and rotated
- Which module/service owns the integration
- Request/response format used
- Error handling strategy (retries, fallbacks, circuit breakers?)
- Webhook handling (if applicable)
- Known limitations or rate limits of the external service

---

---

# PART 6 — DATABASE & DATA LAYER
*Schema, queries, migrations, caching, and data integrity.*

---

## 6.1 Database Overview

- What database engine(s) are used? (PostgreSQL, MySQL, MongoDB, DynamoDB, SQLite, etc.)
- What version(s)?
- Is the database managed or self-hosted?
- Are there multiple databases? Why? What data lives in each?
- Is there a read replica setup? How is read/write splitting handled?

## 6.2 ORM / Query Builder

- What ORM or query builder is used? (Prisma, TypeORM, Drizzle, Sequelize, SQLAlchemy, ActiveRecord, Knex, raw SQL, etc.)
- How is the DB connection managed? (connection pool, singleton, per-request?)
- What are the pool size settings?
- How are queries structured — through the ORM, raw SQL, or a mix?
- Are N+1 query problems addressed? How? (eager loading, includes, DataLoader?)

## 6.3 Schema & Data Model

For **every table/collection**, provide:
- Table/collection name
- Purpose / business meaning
- All columns/fields with:
  - Name
  - Type
  - Nullable?
  - Default value?
  - Indexed?
  - Description / business meaning
- Primary key strategy (UUID, auto-increment, ULID, CUID, custom?)
- All foreign keys and what they reference
- Unique constraints
- Check constraints
- Any soft-delete columns (`deleted_at`, `is_active`, etc.)

## 6.4 Relationships & Entity Map

- Describe all major entity relationships (one-to-one, one-to-many, many-to-many).
- Are join tables used for M2M? List them.
- Are there any polymorphic associations? How are they implemented?
- Draw or describe a simplified entity-relationship diagram.

## 6.5 Migrations

- What migration tool is used? (Prisma Migrate, Flyway, Liquibase, Alembic, custom, etc.)
- Where do migration files live?
- How are migrations run in each environment?
- How are they run in the CI/CD pipeline?
- Is there a rollback mechanism for migrations?
- Have there been any irreversible migrations? (e.g., column drops, data rewrites?)

## 6.6 Indexing Strategy

- List all indexes beyond primary keys with:
  - Table and column(s)
  - Index type (B-tree, GIN, GiST, composite, partial, etc.)
  - Reason for the index (query it supports)
- Are there any missing indexes on frequently queried columns? (⚠️ gap)
- Are there any unused indexes that waste write performance?

## 6.7 Caching Layer

- Is there a caching layer? What engine? (Redis, Memcached, in-memory, CDN?)
- What version? How is it hosted?
- What data is cached?
- What is the cache key structure?
- What are the TTL strategies?
- How is cache invalidation handled? (TTL expiry, event-based, manual purge?)
- Are there any known cache stampede or thundering herd risks?
- Is Redis used for anything other than caching? (sessions, pub/sub, rate limiting, locks?)

## 6.8 Data Integrity & Consistency

- Are database constraints used (NOT NULL, UNIQUE, FK, CHECK)?
- Are business-level constraints enforced only in code or also in the DB?
- How are transactions used? What isolation level?
- Are there any eventual consistency scenarios? How are they handled?
- Is there any data denormalization? Why?

## 6.9 Sensitive Data Handling

- What data is considered sensitive (PII, financial, health)?
- Is any data encrypted at the column level? Which columns? What algorithm?
- Is PII anonymized or pseudonymized anywhere?
- How is data retention handled? Is there a deletion policy?
- Is the project subject to GDPR, CCPA, HIPAA, or other regulations?

---

---

# PART 7 — QUEUE SYSTEM & ASYNC WORKERS
*All async processing, background jobs, and event-driven flows.*

---

## 7.1 Message Broker / Queue Engine

- What queue/broker technology is used? (BullMQ, Bull, Celery, RabbitMQ, Kafka, AWS SQS/SNS, Redis Streams, NATS, etc.)
- What version? Where is it hosted?
- Is the broker managed or self-hosted?
- What persistence and durability guarantees does it provide?

## 7.2 Queue Inventory

For **every queue**, provide:
- Queue name
- Purpose
- Message/job payload schema (example)
- Average throughput (jobs/sec or jobs/day)
- Expected processing time per job
- Priority level (if applicable)
- Consumer count

## 7.3 Worker Inventory

For **every worker/consumer**, provide:
- Worker name
- Which queue(s) it consumes
- Concurrency setting
- What it does step by step
- External services it calls
- Typical duration
- Known failure modes

## 7.4 Retry & Dead Letter Strategy

- What is the retry policy? (number of retries, backoff type: linear/exponential/fixed?)
- What happens to jobs that exhaust all retries? (dead-letter queue, deletion, alerting?)
- Is there a dead-letter queue? How are failed jobs monitored and reprocessed?
- Are jobs idempotent? What ensures idempotency? (idempotency key, deduplication?)
- How are duplicate jobs prevented?

## 7.5 Job Monitoring & Observability

- How are job queues monitored? (Bull Dashboard, Flower, custom dashboard, Grafana?)
- Are job failures alerted on? How?
- Are queue depths monitored? Are there alerting thresholds?

---

---

# PART 8 — AUTHENTICATION & AUTHORIZATION
*Identity, sessions, tokens, roles, and permissions.*

---

## 8.1 Authentication Flow

- What authentication method is used? (JWT, session/cookie, OAuth2, OpenID Connect, SSO, magic link, passkeys, etc.)
- Walk through the complete sign-in flow step by step.
- Walk through the complete sign-up/registration flow step by step.
- How are passwords stored? (hashing algorithm: bcrypt, argon2, etc. — cost factor?)
- How is "forgot password" / password reset implemented?
- Is multi-factor authentication (MFA) supported? What methods? (TOTP, SMS, email OTP?)
- Is social login supported? Which providers? (Google, GitHub, Apple, etc.)

## 8.2 Token Management

- What type of tokens are issued? (access token, refresh token, session ID?)
- Where are tokens stored client-side? (localStorage, sessionStorage, httpOnly cookie?)
- What is the access token expiry? The refresh token expiry?
- How are tokens refreshed? (silent refresh, refresh token rotation?)
- How are tokens revoked? (token blacklist, short expiry, session invalidation?)
- Are tokens stateless or stateful?
- What is the token payload? List all claims.

## 8.3 Authorization Model

- What authorization model is used? (RBAC, ABAC, ACL, ReBAC, custom?)
- List all roles in the system with a description of each.
- List all permissions/capabilities and which roles have them.
- How is authorization checked? (middleware, decorators, service-level guards, DB policies like Row-Level Security?)
- Is there resource-level ownership checked? (can user A access resource owned by user B?)
- Are there any authorization bypass paths? (⚠️ audit this carefully)

## 8.4 Session Management

- Are sessions used? Where are they stored? (DB, Redis, in-memory?)
- What is the session expiry? Is it sliding or absolute?
- How are concurrent sessions handled? (single session, multiple allowed, device management?)
- How does "log out all devices" work?

## 8.5 Security Hardening

- How is injection (SQL, NoSQL, command) prevented?
- How is XSS prevented?
- How is CSRF prevented?
- Are security headers set? (CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy?)
- Is HTTPS enforced everywhere? How?
- How are secrets and credentials managed? (env vars, AWS Secrets Manager, HashiCorp Vault, etc.)
- Is there any security scanning in CI? (SAST tools, dependency audits, Snyk, Semgrep, Trivy?)
- Is there an audit log for sensitive operations? (login, permission changes, data exports, deletions?)

---

---

# PART 9 — INFRASTRUCTURE & DEPLOYMENT
*Where the system lives and how it's provisioned.*

---

## 9.1 Cloud Provider & Hosting

- What cloud provider(s) are used? (AWS, GCP, Azure, Hetzner, DigitalOcean, Vercel, Railway, Fly.io, etc.)
- What regions are deployed to?
- Is there multi-region or multi-AZ setup?
- What is the estimated monthly infrastructure cost?

## 9.2 Compute

- How is the application hosted? (EC2, ECS Fargate, EKS, Lambda, Cloud Run, VMs, bare metal?)
- For each service/process, what compute resource is it running on?
- What are the resource allocations? (CPU, memory per container/instance)
- How many instances run in production?

## 9.3 Containerization

- Is Docker used? What base images?
- Is there a `docker-compose.yml` for local development? What services does it define?
- Are Dockerfiles multi-stage? Are images optimized for size?
- Is Kubernetes used? What distribution? (EKS, GKE, AKS, k3s, etc.)
- What Kubernetes objects are defined? (Deployments, Services, Ingresses, ConfigMaps, Secrets, HPA, etc.)
- Is Helm used? What charts?

## 9.4 Networking

- How is traffic routed from the internet to the application?
  - DNS provider and TTL settings
  - CDN (CloudFront, Fastly, Cloudflare?) — what is cached?
  - Load balancer type and configuration
  - Reverse proxy (Nginx, Traefik, Caddy?) — config?
  - WAF in use?
- What is the VPC/network topology?
- How do services communicate internally? (service mesh, internal DNS, direct IP?)
- Are there any firewall rules or security groups? List the key ones.

## 9.5 Storage

- What object/blob storage is used? (S3, GCS, Azure Blob, Cloudflare R2?)
- What data is stored there? (user uploads, exports, backups, assets?)
- What is the bucket structure?
- Are buckets publicly accessible? Should they be?
- Are signed URLs used for private assets?
- Is there a CDN in front of the storage?

## 9.6 Scaling

- What is the auto-scaling strategy? (horizontal pod autoscaler, EC2 ASG, manual?)
- What metrics trigger scaling? (CPU, memory, request queue depth, custom?)
- What are the min/max instance counts?
- Is there pre-warming for traffic spikes?
- What is the scale-to-zero strategy (if any)?

## 9.7 Disaster Recovery & Backups

- What is the RPO (Recovery Point Objective)? The RTO (Recovery Time Objective)?
- How are databases backed up? How frequently?
- Where are backups stored? Are they tested?
- Is there a documented disaster recovery runbook?
- Has a DR drill been performed? When?

---

---

# PART 10 — DEVOPS, CI/CD & DEVELOPER EXPERIENCE
*How code goes from laptop to production.*

---

## 10.1 CI/CD Pipeline

- What CI/CD platform is used? (GitHub Actions, GitLab CI, CircleCI, Jenkins, BuildKite, etc.)
- Walk through the **complete pipeline** from a `git push` to a successful production deployment:
  1. Trigger condition
  2. Checkout & setup
  3. Dependency installation
  4. Linting & type checking
  5. Unit tests
  6. Integration tests
  7. Build
  8. Docker image build (if applicable)
  9. Image push to registry
  10. Migration run
  11. Deployment (how? rolling, blue-green, canary?)
  12. Smoke tests / health checks post-deploy
  13. Notifications
- How long does the full pipeline take?
- What is the pipeline broken down by step duration?

## 10.2 Deployment Strategy

- What deployment strategy is used? (rolling, blue-green, canary, recreate?)
- How is zero-downtime deployment achieved?
- How are database migrations handled in relation to code deployment? (before, after, simultaneously?)
- What is the rollback procedure? How long does it take?
- Are there any feature flags controlling new code paths? (⚠️ if not, this is a risk)

## 10.3 Environment Promotion

- How does code flow from dev → staging → production?
- Is promotion automated or manual?
- Is there a preview environment per PR/branch?
- How are environment-specific secrets provisioned?

## 10.4 Developer Onboarding & Local Setup

- What is the local development setup procedure? (step by step)
- What prerequisites does a new developer need to install?
- How long does it take to get a fully working local environment from scratch?
- Is there a `Makefile`, `justfile`, or equivalent with common commands? List all commands.
- Are there any known local setup pain points or gotchas?
- Is there a seed/fixture script to populate the local database?

## 10.5 Code Quality Gates

- What checks are required before merging a PR? (linting, type-check, tests, coverage, review?)
- What is the minimum required test coverage? Is it enforced?
- Is there static analysis? (SonarQube, CodeClimate, DeepSource, Semgrep?)
- Are there any security scanning gates? (Snyk, Trivy, GitLeaks?)
- Is there a bundle size check on the frontend?

---

---

# PART 11 — TESTING
*Coverage, strategy, tools, and gaps.*

---

## 11.1 Testing Strategy Overview

- What testing philosophy is followed? (testing pyramid, testing trophy, risk-based?)
- What types of tests exist?
  - Unit tests
  - Integration tests
  - End-to-end (E2E) tests
  - Contract tests
  - Performance/load tests
  - Visual regression tests
  - Accessibility tests
  - Security tests

## 11.2 Unit Tests

- What test framework is used? (Jest, Vitest, Mocha, pytest, RSpec, etc.)
- What assertion library?
- What mocking library?
- Where do unit tests live? (co-located, separate `__tests__` folder, `spec/` folder?)
- What is the current unit test coverage? (overall, per module)
- What is the most undertested module? (⚠️ gap)

## 11.3 Integration Tests

- What is being integration tested? (API endpoints, service layer, DB layer?)
- How is the test database set up and torn down?
- Are real external services called or mocked in integration tests?
- What is the current integration test coverage?

## 11.4 End-to-End Tests

- What E2E framework is used? (Playwright, Cypress, Selenium?)
- What user flows are covered by E2E tests? List them.
- Where do E2E tests run? (CI only, also locally?)
- How long do E2E tests take?
- Are E2E tests flaky? Any known unstable tests?

## 11.5 Test Data & Fixtures

- How is test data managed? (factories, fixtures, seeds, in-line data?)
- What factory/fixture library is used? (factory-boy, Faker, fishery, etc.)
- Is there a shared test database or isolated per-test?
- How is the database state reset between tests?

## 11.6 Performance & Load Testing

- Is there load/stress testing? What tool? (k6, Locust, Artillery, JMeter?)
- What scenarios are tested?
- What are the performance baselines / SLAs?
- When was the last load test run? What were the results?

---

---

# PART 12 — OBSERVABILITY & MONITORING
*Logs, metrics, traces, and alerts.*

---

## 12.1 Logging

- What logging library is used? (Winston, Pino, Bunyan, structlog, etc.)
- Is structured/JSON logging used?
- What log levels are used, and when?
- What fields are included in every log entry? (timestamp, request ID, user ID, service name, etc.)
- Is there a correlation/trace ID propagated through requests?
- Where are logs shipped to? (Datadog, Grafana Loki, CloudWatch, ELK, Papertrail?)
- What is the log retention policy?
- Are there any sensitive fields being inadvertently logged? (⚠️ risk)

## 12.2 Metrics

- What metrics system is used? (Prometheus, Datadog, CloudWatch, StatsD?)
- What application-level metrics are tracked? (request count, latency, error rate, queue depth, etc.)
- What infrastructure metrics are tracked? (CPU, memory, disk, network?)
- Are custom business metrics tracked? (signups per hour, payments processed, etc.)

## 12.3 Distributed Tracing

- Is distributed tracing implemented? What tool? (Jaeger, Zipkin, Datadog APM, OpenTelemetry?)
- Are all service-to-service calls instrumented?
- Are DB queries visible in traces?
- Are external API calls visible in traces?

## 12.4 Alerting

- What alerting platform is used? (PagerDuty, Opsgenie, Datadog, Grafana?)
- List all configured alerts with:
  - Alert name
  - Condition / threshold
  - Severity
  - Who is notified
  - Runbook link (if any)
- Are there any missing alerts for critical paths? (⚠️ gap)
- What is the on-call rotation?

## 12.5 Error Tracking

- What error tracking platform is used? (Sentry, Rollbar, Bugsnag, Datadog?)
- Is it integrated in both frontend and backend?
- How are errors grouped and deduplicated?
- How are errors triaged and resolved?
- What is the current unresolved error count and rate?

## 12.6 Health Checks

- Is there a `/health` or `/healthz` endpoint? What does it check?
- Is there a readiness probe vs. liveness probe? What do they each check?
- How do health checks integrate with the load balancer and orchestration layer?

---

---

# PART 13 — PERFORMANCE & SCALABILITY
*Bottlenecks, limits, and optimization opportunities.*

---

## 13.1 Current Performance Baselines

- What are the current API response time percentiles? (p50, p95, p99)
- What is the current request throughput? (requests/sec)
- What is the current error rate?
- What are the DB query time percentiles?
- What are the queue processing times?

## 13.2 Known Bottlenecks

- What are the known performance bottlenecks in the system?
- Which endpoints or operations are the slowest?
- Are there any N+1 query problems?
- Are there any missing database indexes causing slow queries?
- Are there any memory leaks or unbounded memory growth?

## 13.3 Scalability Limits

- What is the current peak load handled?
- At what scale does the system begin to degrade?
- What is the single point of failure (SPOF) in the current architecture?
- What would need to change to 10x the current load?

## 13.4 Optimization Opportunities

- Where could caching be introduced or improved?
- Where could async processing offload synchronous work?
- Where could DB queries be optimized? (query rewrite, index, denormalization?)
- Where could CDN/edge caching reduce origin load?

---

---

# PART 14 — DOCUMENTATION & KNOWLEDGE
*What is written down and what lives only in people's heads.*

---

## 14.1 Existing Documentation

- What documentation exists? List every doc, wiki page, README, or runbook.
- Where does documentation live? (Notion, Confluence, GitHub Wiki, inline README files, etc.)
- How current is the documentation? When was it last updated?

## 14.2 Runbooks & Operational Procedures

- Is there a runbook for each common operational task?
  - Deploying a hotfix
  - Rolling back a deployment
  - Restoring a database backup
  - Handling a queue overflow
  - Rotating secrets/credentials
  - Responding to an outage
- List which runbooks exist and which are missing. (⚠️ gaps are risks)

## 14.3 Architecture Decision Records (ADRs)

- Are ADRs maintained? Where?
- List the most significant architectural decisions made and the rationale behind them.
- Are there any past decisions that are now regretted or reconsidered?

## 14.4 Tribal Knowledge

- What critical knowledge exists only in individuals' heads and is not documented?
- Who are the key people ("bus factor") without whom the system would be hard to maintain?
- What are the most complex or surprising parts of the codebase that need better documentation?

---

---

# PART 15 — TECHNICAL DEBT & RISK REGISTER

---

## 15.1 Known Technical Debt

List every known item of technical debt in the following format:

```
ID: TD-001
Location: [file path, module, or service]
Description: [what the debt is]
Impact: [how it affects the system or team]
Effort to fix: [hours/days/weeks estimate]
Priority: [critical / high / medium / low]
Owner: [who knows the most about this]
```

## 15.2 Security Risks

List every known or suspected security risk:
- Risk description
- Likelihood (high / medium / low)
- Potential impact
- Current mitigation (if any)
- Recommended fix

## 15.3 Operational Risks

- What single points of failure exist?
- What happens if the database goes down?
- What happens if the queue broker goes down?
- What happens if a third-party service is unavailable?
- What happens if the CDN goes down?
- What is the maximum tolerable downtime?

## 15.4 Compliance & Legal Risks

- What regulatory frameworks apply? (GDPR, CCPA, SOC2, HIPAA, PCI-DSS, ISO 27001?)
- What compliance controls are in place?
- What is currently non-compliant or at risk?

---

---

# FINAL SYNTHESIS

After completing all sections above, provide the following summary:

## Executive Summary
Write a 3–5 paragraph narrative description of this system — what it is, how it works, its strengths, and its current state of maturity.

## Architecture Diagram (Text)
Produce a text-based architecture diagram showing all major components and how they connect.

## Top 5 Strengths
List the 5 best-designed aspects of this system with a brief explanation for each.

## Top 10 Risks & Gaps
List the 10 most important issues, risks, anti-patterns, or missing pieces — ordered by severity. For each, include a recommended action.

## Recommended Roadmap
Provide a prioritized improvement roadmap broken into:
- **Immediate (this sprint):** Critical fixes and quick wins
- **Short-term (1–4 weeks):** Important improvements
- **Medium-term (1–3 months):** Architectural investments
- **Long-term (3–12 months):** Strategic evolution

## Bus Factor Analysis
Who are the key people this system depends on? What knowledge is at risk if they leave?

## Maturity Assessment
Rate each dimension on a scale of 1–5 and provide a one-line justification:

| Dimension | Score (1–5) | Notes |
|---|---|---|
| Code Quality | | |
| Test Coverage | | |
| Documentation | | |
| Observability | | |
| Security | | |
| Performance | | |
| Scalability | | |
| DevOps / CI-CD | | |
| Developer Experience | | |
| Operational Readiness | | |

---

*End of audit prompt. Paste this alongside your codebase, README, and any relevant configuration files.*
