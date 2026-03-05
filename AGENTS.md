# Ogami ERP — Agent Documentation

This document provides essential information for AI coding agents working on the Ogami ERP project.

## Project Overview

**Ogami ERP** is a comprehensive Enterprise Resource Planning system built for manufacturing businesses in the Philippines. It integrates HR management, payroll processing, accounting, and financial operations into a unified platform.

### Key Domains

| Domain | Description |
|--------|-------------|
| **HR** | Employee management, departments, positions, documents |
| **Attendance** | Time tracking, shift schedules, overtime requests |
| **Leave** | Leave types, balance tracking, request workflows |
| **Payroll** | Multi-step computation pipeline, government contributions (SSS, PhilHealth, Pag-IBIG), withholding tax |
| **Loans** | Employee loan management with amortization schedules |
| **Accounting** | Double-entry bookkeeping, chart of accounts, journal entries, financial statements |
| **AP/AR** | Accounts payable (vendors, vendor invoices) and accounts receivable (customers, customer invoices) |
| **Tax** | VAT ledger tracking for Philippine tax compliance |

## Technology Stack

### Backend
- **Framework**: Laravel 11 (PHP 8.3+)
- **Database**: PostgreSQL 16 (uses stored computed columns, triggers, and constraints)
- **Cache/Queue/Session**: Redis 7
- **Queue Workers**: Laravel Horizon (with Supervisor in production)
- **Real-time**: Laravel Reverb (WebSockets)
- **Monitoring**: Laravel Pulse
- **Authentication**: Laravel Sanctum with MFA (TOTP via `pragmarx/google2fa-laravel`)
- **PDF Generation**: `barryvdh/laravel-dompdf`
- **Excel Exports**: `maatwebsite/excel`
- **Media Handling**: `spatie/laravel-medialibrary`
- **Backup**: `spatie/laravel-backup`
- **Audit Trail**: `owen-it/laravel-auditing`
- **RBAC**: `spatie/laravel-permission`
- **Process Runner**: Spiral RoadRunner (CLI and HTTP)

### Frontend
- **Framework**: React 18 + TypeScript
- **Build Tool**: Vite 6
- **Styling**: Tailwind CSS 3 + Framer Motion
- **State Management**: Zustand
- **Server State**: TanStack Query (React Query)
- **Forms**: React Hook Form + Zod validation
- **Data Tables**: TanStack Table
- **Charts**: Recharts
- **Notifications**: Sonner
- **Icons**: Lucide React
- **Testing**: Vitest + React Testing Library + Playwright (E2E)

### Testing
- **Backend**: Pest PHP 3 with PHPUnit 11
- **Frontend**: Vitest + React Testing Library
- **E2E**: Playwright
- **Static Analysis**: PHPStan (Larastan) at level 5
- **Code Style**: Laravel Pint
- **Load Testing**: k6

### Infrastructure
- **Containerization**: Docker + Docker Compose
- **Web Server**: Nginx (production) / PHP built-in server (development)
- **Package Manager**: pnpm (frontend), Composer (PHP)
- **Process Manager**: Supervisor (production)

## Project Structure

```
/
├── app/
│   ├── Console/Commands/      # Artisan commands
│   ├── Domains/               # Domain-driven modules
│   │   ├── AP/                # Accounts Payable
│   │   ├── AR/                # Accounts Receivable
│   │   ├── Accounting/        # GL, COA, Journal Entries
│   │   ├── Attendance/        # Time tracking
│   │   ├── HR/                # Employees, Departments
│   │   ├── Leave/             # Leave management
│   │   ├── Loan/              # Employee loans
│   │   ├── Payroll/           # Payroll computation pipeline
│   │   └── Tax/               # VAT tracking
│   ├── Events/                # Domain events
│   ├── Exceptions/            # Exception handler
│   ├── Exports/               # Excel export classes
│   ├── Http/
│   │   ├── Controllers/       # Organized by domain
│   │   ├── Requests/          # Form request validation
│   │   └── Resources/         # API response transformers
│   ├── Infrastructure/
│   │   ├── Middleware/        # Custom middleware
│   │   ├── Observers/         # Model observers
│   │   └── Scopes/            # Query scopes
│   ├── Jobs/                  # Queueable jobs
│   ├── Models/                # Core models (User, DepartmentPermission, etc.)
│   ├── Notifications/         # Email/notification classes
│   ├── Providers/             # Service providers
│   ├── Rules/                 # Custom validation rules
│   ├── Services/              # Shared services
│   └── Shared/
│       ├── Contracts/         # Interfaces (ServiceContract, etc.)
│       ├── Exceptions/        # Domain exceptions
│       ├── Traits/            # Reusable traits
│       └── ValueObjects/      # Value objects (Money, Minutes, etc.)
├── bootstrap/
├── config/                    # Laravel configuration
├── database/
│   ├── factories/             # Model factories
│   ├── migrations/            # Migration files (76+ migrations)
│   └── seeders/               # Database seeders
├── docker/                    # Docker configuration
│   ├── nginx/
│   ├── php/
│   ├── postgres/
│   ├── entrypoint.sh
│   └── supervisord.conf
├── docs/                      # Documentation
│   ├── documentations/        # Phase documentation
│   └── guides/                # Workflow guides
├── frontend/                  # React SPA
│   ├── e2e/                   # Playwright tests
│   ├── src/
│   │   ├── components/        # React components (auth, layout, modals, ui)
│   │   ├── contexts/          # React contexts
│   │   ├── hooks/             # Custom hooks (tanstack-query wrappers)
│   │   ├── lib/               # Utilities (api, permissions)
│   │   ├── pages/             # Page components (by domain)
│   │   ├── router/            # React Router config
│   │   ├── schemas/           # Zod validation schemas
│   │   ├── stores/            # Zustand stores
│   │   ├── styles/            # Tailwind/CSS styles
│   │   ├── types/             # TypeScript types (by domain)
│   │   └── test/              # Test setup
│   ├── package.json
│   └── vite.config.ts
├── public/                    # Web root (built assets go to public/build/)
├── resources/                 # Laravel resources
├── routes/
│   ├── api.php                # API v1 router
│   ├── api/v1/                # Domain-specific route files
│   ├── channels.php           # Broadcast channels
│   ├── console.php            # Console routes
│   └── web.php                # SPA catch-all
├── storage/                   # Laravel storage
├── tests/
│   ├── Arch/                  # Architecture tests
│   ├── Feature/               # Feature tests (HTTP endpoints)
│   ├── Integration/           # Integration tests (cross-domain workflows)
│   ├── Unit/                  # Unit tests (value objects, services)
│   ├── Pest.php               # Pest configuration
│   ├── Support/               # Test helpers
│   ├── k6/                    # Load testing scripts
│   └── load/                  # Load test scenarios
├── composer.json
├── docker-compose.yml
├── Dockerfile                 # Multi-stage: base, development, production
├── phpunit.xml
├── phpstan.neon
├── dev.sh                     # Development startup script
└── package.json               # Root npm scripts
```

## Build and Run Commands

### Development (Local)

```bash
# Start all services (PostgreSQL, Redis, Laravel, Vite, Queue Worker)
npm run dev
# OR directly:
bash dev.sh
```

The `dev.sh` script:
- Starts Docker containers for PostgreSQL (`ogami-pg-test`) and Redis (`ogami-redis-test`) if not running
- Starts Laravel development server on http://127.0.0.1:8000 (8 workers)
- Pre-warms PHP workers to avoid cold-start latency
- Starts queue worker for `default`, `payroll`, and `computations` queues
- Starts Vite dev server on http://localhost:5173
- Tails logs from all services

### Docker Development

```bash
# Build and start all services
docker-compose up --build

# Run migrations
docker-compose exec app php artisan migrate

# Access app container
docker-compose exec app bash
```

### Production Build

```bash
# Frontend build (outputs to public/build/)
cd frontend && pnpm build

# PHP optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --no-dev --optimize-autoloader
```

### Database Operations

```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Fresh database with seeding
php artisan migrate:fresh --seed
```

## Testing Commands

### Backend (Pest/PHPUnit)

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suites
./vendor/bin/pest --testsuite=Unit
./vendor/bin/pest --testsuite=Feature
./vendor/bin/pest --testsuite=Integration
./vendor/bin/pest --testsuite=Arch

# With coverage
./vendor/bin/pest --coverage
```

### Frontend

```bash
cd frontend

# Unit tests
pnpm test

# Watch mode
pnpm test:watch

# Coverage
pnpm test:coverage

# E2E tests
pnpm e2e

# E2E with UI
pnpm e2e:ui
```

### Static Analysis

```bash
# PHPStan (Larastan)
./vendor/bin/phpstan analyse

# Laravel Pint (code style)
./vendor/bin/pint

# Type check frontend
cd frontend && pnpm typecheck
```

## Code Style Guidelines

### PHP

1. **Strict typing**: All files must start with `declare(strict_types=1);`
2. **Final classes**: Model classes and value objects should be `final`
3. **Readonly value objects**: All value objects in `App\Shared\ValueObjects` must be `final readonly`
4. **Service Contract**: All domain services must implement `ServiceContract` interface
5. **No direct DB calls in controllers**: Use service classes instead
6. **Domain exceptions**: All custom exceptions must extend `DomainException`
7. **No debug helpers**: No `dd()`, `dump()`, `var_dump()`, `ray()`, `rdump()` in production code
8. **No float for currency**: Use `Money` value object (integer centavos internally)

### TypeScript/React

1. **Type safety**: Strict TypeScript configuration
2. **Functional components**: Use function declarations with explicit return types
3. **Hooks**: Custom hooks for data fetching use TanStack Query
4. **State**: Zustand for global state, React Query for server state
5. **Forms**: React Hook Form with Zod schemas
6. **Testing**: Vitest with React Testing Library, MSW for API mocking
7. **Path alias**: Use `@/` for imports from `src/`

## Testing Strategy

### Test Suites

1. **Unit Tests** (`tests/Unit/`): Pure logic tests, value objects, service computations
2. **Feature Tests** (`tests/Feature/`): HTTP endpoint tests, authentication, authorization
3. **Integration Tests** (`tests/Integration/`): Cross-domain workflows (payroll → GL, AP → GL)
4. **Architecture Tests** (`tests/Arch/`): Structural constraints using Pest Arch plugin

### Key Testing Patterns

- **Database**: PostgreSQL test database (`ogami_erp_test`) - never SQLite (triggers and constraints must be tested)
- **RefreshDatabase**: Used for Feature and Integration tests
- **Custom expectations**: `toBeValidationError()`, `toBeDomainError()`
- **Dataset helpers**: `invalidAmounts()` for boundary testing

### Architecture Test Rules (ARCH-001 through ARCH-006)

- **ARCH-001**: Controllers must not contain direct DB calls
- **ARCH-002**: Domain Services must implement ServiceContract
- **ARCH-003**: All custom exceptions must extend DomainException
- **ARCH-004**: Value objects must be final readonly classes
- **ARCH-005**: No dd() / dump() / var_dump() left in app/ source
- **ARCH-006**: Shared contracts namespace contains only interfaces

### Payroll Testing

The payroll domain has extensive testing including:
- Golden suite tests for known-good scenarios
- Edge case handling (negative net pay, mid-period hires)
- Property-based testing for contribution calculations
- Tax computation validation

## Security Considerations

### Authentication & Authorization

- **MFA**: Time-based One-Time Password (TOTP) required for all users
- **RBAC**: Role-based access control via `spatie/laravel-permission`
- **SoD**: Segregation of Duties middleware prevents conflicts (e.g., same user cannot prepare and approve payroll)
- **Department Scoping**: Users can only access data from their assigned departments
- **Session**: Redis-backed sessions with encryption
- **Auth Method**: Session-cookie based (not JWT), no sensitive data in localStorage

### Data Protection

- **Encryption**: Government IDs (SSS, TIN, etc.) encrypted at model layer
- **Hashing**: SHA-256 hashes for unique constraint checking without exposing raw values
- **Audit Trail**: All model changes tracked via `owen-it/laravel-auditing`
- **Rate Limiting**: 120 reads / 60 writes per minute on API routes

### Input Validation

- Form Request classes for all endpoints
- Zod schemas for frontend validation
- Custom validation rules for business logic (e.g., `LeafAccountRule`, `OpenFiscalPeriodRule`)

## Domain Architecture Patterns

### Payroll Computation Pipeline

The payroll system uses a pipeline pattern with 17 steps:

```
Step01SnapshotsStep → Step02PeriodMetaStep → Step03AttendanceSummaryStep → 
Step04LoadYtdStep → Step05BasicPayStep → Step06OvertimePayStep → 
Step07HolidayPayStep → Step08NightDiffStep → Step09GrossPayStep → 
Step10SssStep → Step11PhilHealthStep → Step12PagibigStep → 
Step13TaxableIncomeStep → Step14WithholdingTaxStep → Step15LoanDeductionsStep → 
Step16OtherDeductionsStep → Step17NetPayStep
```

Each step implements `PayrollComputationStep` and operates on a `PayrollComputationContext`.

### State Machines

- **Employee**: `draft → active → on_leave|suspended → resigned|terminated`
- **PayrollRun**: `draft → scoped → computed → hr_reviewed → acctg_reviewed → approved → published|cancelled`

### Value Objects

Key value objects in `App\Shared\ValueObjects`:
- `Money`: Handles centavo-based calculations (never use float for currency)
- `Minutes`: Time duration calculations
- `PayPeriod`: Pay period encapsulation
- `DateRange`: Date range operations
- `OvertimeMultiplier`: OT rate calculations
- `EmployeeCode`: Employee code generation
- `WorkingDays`: Working day calculations

## API Structure

All API routes are under `/api/v1/` and organized by domain:

```
/api/v1/auth/*          # Authentication (login, logout, MFA)
/api/v1/hr/*            # HR domain
/api/v1/leave/*         # Leave domain
/api/v1/loans/*         # Loans domain
/api/v1/attendance/*    # Attendance domain
/api/v1/payroll/*       # Payroll domain
/api/v1/accounting/*    # Accounting domain
/api/v1/ar/*            # Accounts Receivable
/api/v1/tax/*           # Tax/VAT domain
/api/v1/reports/*       # Reports
/api/v1/employee/*      # Employee self-service
/api/v1/admin/*         # Administration
/api/v1/notifications/* # Notifications
```

## Deployment

### Docker Production Setup

The `Dockerfile` has four stages:
1. **base**: PHP 8.3 with extensions
2. **development**: Artisan serve for local dev
3. **frontend-builder**: Compiles React/Vite SPA
4. **production**: Nginx + PHP-FPM + Supervisor

### Services in Production

- **app**: Main application (Nginx + PHP-FPM)
- **horizon**: Queue worker process
- **reverb**: WebSocket server
- **postgres**: PostgreSQL database
- **redis**: Redis cache/queue/session store

### Environment Variables

Key environment variables (see `.env.example`):

```bash
APP_ENV=production
APP_BUILD_TARGET=production
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000,localhost

# Reverb (WebSockets)
REVERB_APP_ID=ogami
REVERB_HOST=localhost
REVERB_PORT=8080

# Philippine region for minimum wage lookups
DEFAULT_REGION=NCR
```

## Troubleshooting

### Common Issues

1. **Permission denied on storage**: Run `chmod -R 775 storage bootstrap/cache`
2. **Queue not processing**: Check Horizon dashboard at `/horizon`
3. **WebSocket not connecting**: Verify Reverb is running and `REVERB_*` env vars are set
4. **Database connection errors**: Check Docker containers are running (`docker ps`)
5. **Test database locked**: Ensure `ogami_erp_test` exists and is accessible

### Logs

- Laravel: `storage/logs/laravel.log`
- Dev server: `storage/logs/serve.log`
- Queue: `storage/logs/queue.log`
- Vite: `storage/logs/vite.log`

## Additional Resources

- Documentation: `docs/` directory
  - Phase documentation: `docs/documentations/`
  - Workflow guides: `docs/guides/`
- Security findings: `docs/zap-security-findings.md`
- Thesis Q&A: `docs/thesis-defence-qa.md`
