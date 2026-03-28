# Ogami ERP - Comprehensive Codebase Study

> **Generated:** 2026-03-28 | **Purpose:** Full architectural and structural analysis of the Ogami ERP codebase.

---

## 1. Project Overview

**Ogami ERP** is a manufacturing Enterprise Resource Planning system built for Philippine businesses. It covers 22 domain modules spanning HR, payroll, accounting, procurement, production, quality control, sales, delivery, and more.

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend Framework | Laravel 11 (PHP 8.2+) |
| Database | PostgreSQL 16 |
| Cache / Queue | Redis |
| Frontend Framework | React 18 + TypeScript |
| Build Tool | Vite 6 |
| Package Manager | pnpm 10 (workspace at repo root) |
| State Management | TanStack Query (server) + Zustand (client, 2 stores only) |
| UI | Tailwind CSS |
| Auth | Laravel Sanctum (session-cookie, no JWT) |
| Permissions | Spatie Laravel Permission (RBAC) |
| Testing | Pest PHP (backend), Vitest (frontend), Playwright (E2E) |
| Static Analysis | PHPStan/Larastan (level 5), ESLint, TypeScript strict |
| Code Style | Laravel Pint (PHP CS Fixer) |

### Key Dependencies

- `spatie/laravel-permission` - RBAC
- `owen-it/laravel-auditing` - Audit trails
- `barryvdh/laravel-dompdf` - PDF generation (payslips, reports)
- `maatwebsite/excel` - Excel import/export
- `spatie/laravel-medialibrary` - File uploads
- `spatie/laravel-backup` - Database backups
- `laravel/horizon` - Queue monitoring
- `laravel/reverb` - WebSocket server
- `laravel/pulse` - Performance monitoring
- `darkaonline/l5-swagger` - API documentation

---

## 2. Quantitative Summary

| Metric | Count |
|--------|-------|
| Domain modules | 22 (under `app/Domains/`) |
| Domain PHP files | 360 |
| Eloquent models | 137 |
| Domain services | 141 |
| Policies | 41 |
| State machines | 17 |
| Controllers | 81 |
| API Resources | 73 |
| Form Requests | 82 |
| Database migrations | 222 |
| Database seeders | 43 |
| API route files | 28 (under `routes/api/v1/`) |
| Backend test files | 94 |
| Frontend pages (TSX) | 241 |
| Frontend hooks | 58 |
| Frontend Zod schemas | 25 |
| Value Objects | 7 |

---

## 3. Architecture

### 3.1 Backend - Domain-Driven Structure

```
app/
  Domains/<Domain>/
    Models/           Eloquent models (HasPublicUlid, SoftDeletes, Auditable)
    Services/         Business logic (final class, implements ServiceContract)
    Policies/         Laravel Gate policies (registered in AppServiceProvider)
    StateMachines/    Status transitions (hold TRANSITIONS constant)
    Pipeline/         Payroll computation steps (Step01-Step18)
    Events/           Domain events (e.g., EmployeeActivated)
    Listeners/        Event handlers
    Validators/       Business rule validators
    Rules/            Custom validation rules
    DataTransferObjects/  DTOs for complex operations

  Http/
    Controllers/      Thin controllers (authorize + delegate to service)
    Requests/         FormRequest validation (one per domain)
    Resources/        API response transformers (one per domain)

  Infrastructure/
    Boot/             Environment validation
    Middleware/        DepartmentScope, SoD, ModuleAccess, SecurityHeaders
    Observers/        Model observers (PayrollRunObserver)
    Scopes/           Global query scopes (DepartmentScope)

  Shared/
    ValueObjects/     Money, Minutes, PayPeriod, DateRange, EmployeeCode, WorkingDays, OvertimeMultiplier
    Exceptions/       DomainException base + 12 specialized exceptions
    Contracts/        Marker interfaces (ServiceContract, BusinessRule)
    Traits/           HasPublicUlid, HasDepartmentScope, ApiResponse
    Concerns/         HasApprovalWorkflow
    Models/           ApprovalLog (shared across domains)

  Notifications/     Email/database notifications (vendor, production, QC)
  Policies/          Cross-domain policies
  Providers/         AppServiceProvider (policy registration, event mapping)
  Services/          Cross-domain services (BankDisbursement, DepartmentPermission)
  Rules/             Shared validation rules (Accounting)
```

### 3.2 Request Flow

```
Route (routes/api/v1/*.php)
  -> Middleware (auth:sanctum, dept_scope, sod, module_access)
    -> Controller ($this->authorize() via Policy)
      -> Service (DB::transaction(), business logic)
        -> Model (Eloquent ORM)
      -> Resource (JSON transformation)
```

**Architecture Rules (enforced by Arch tests):**

| Rule | Constraint |
|------|-----------|
| ARCH-001 | No `DB::` in controllers |
| ARCH-002 | Domain services implement `ServiceContract` |
| ARCH-003 | Exceptions extend `DomainException` |
| ARCH-004 | Value objects are `final readonly class` |
| ARCH-005 | No `dd()`/`dump()`/`var_dump()` in `app/` |
| ARCH-006 | `Shared\Contracts` contains only interfaces |

### 3.3 Frontend Structure

```
frontend/src/
  hooks/use<Domain>.ts     TanStack Query wrappers (58 files)
  pages/<domain>/          Page components (241 files across 27 domains)
  types/<domain>.ts        TypeScript interfaces (26 type files)
  schemas/<domain>.ts      Zod validation schemas (25 files)
  stores/                  authStore.ts + uiStore.ts ONLY
  lib/api.ts               Axios instance (baseURL /api/v1, withCredentials)
  router/index.tsx         All routes (700+ lines, lazy-loaded)
  components/              Shared UI components (layout, modals, ui, dashboard)
  contexts/                React contexts (PayrollWizardContext)
  styles/                  CSS overrides
```

**Frontend Conventions:**
- Server state exclusively in TanStack Query hooks (never Zustand)
- Only 2 Zustand stores (`authStore`, `uiStore`) -- never create more
- API write cooldown: duplicate POST/PUT/PATCH/DELETE to same URL silently aborted within 1500ms
- URL params are ULIDs: `useParams<{ ulid: string }>()`
- Paginated responses use `.meta` (not `.pagination`)
- Permission guard component `RequirePermission` with pipe-separated OR logic

---

## 4. Domain Modules (22)

### 4.1 HR & People

| Domain | Models | Services | Key Features |
|--------|--------|----------|-------------|
| **HR** | Employee, Department, Position, SalaryGrade, EmployeeClearance, EmployeeDocument, PerformanceAppraisal, CompetencyMatrix, Training | EmployeeService, AuthService, OnboardingChecklistService, OrgChartService, PerformanceAppraisalService, EmployeeClearanceService | Employee lifecycle (draft->active->resigned), government ID encryption (SSS/TIN/PhilHealth/PagIBIG with SHA-256 hash), computed daily/hourly rates (PG triggers), org chart |
| **Attendance** | AttendanceLog, ShiftSchedule, EmployeeShiftAssignment, OvertimeRequest, TimesheetApproval | AttendanceProcessingService, AttendanceImportService, OvertimeRequestService, AnomalyResolutionService | Shift management, OT approval (5-step: supervisor->officer->VP), tardiness/undertime tracking |
| **Leave** | LeaveType, LeaveBalance, LeaveRequest | LeaveRequestService, LeaveAccrualService, LeaveCalendarService, LeaveConflictDetectionService, SilMonetizationService | Leave types per AD-084 form, accrual engine, conflict detection, SIL monetization |
| **Loan** | Loan, LoanType, LoanAmortizationSchedule | LoanRequestService, LoanAmortizationService, LoanPayoffService | 5-stage approval, amortization schedules, payroll deduction integration |

### 4.2 Payroll

| Domain | Models | Services | Key Features |
|--------|--------|----------|-------------|
| **Payroll** | PayrollRun, PayrollDetail, PayrollAdjustment, PayPeriod, PayrollRunApproval, PayrollRunExclusion, ThirteenthMonthAccrual + 5 government tables (SSS, PhilHealth, PagIBIG, Tax, MinimumWage) | PayrollRunService, PayrollComputationService, PayrollWorkflowService, PayrollQueryService, PayrollPreRunService, PayrollScopeService, PayrollPostingService, PayslipPdfService, TaxWithholdingService, SssContributionService, PhilHealthContributionService, PagibigContributionService, DeductionService, FinalPayService, GovReportDataService, ThirteenthMonthAccrualService, PayrollBatchDispatcher, PayrollEdgeCaseHandler, TaxStatusDeriver | **18-step computation pipeline** (Step01-Step18), 14-state workflow (DRAFT->PUBLISHED), pre-run validation, batch processing, GL auto-posting, Philippine tax compliance (TRAIN law), 13th month pay |

**Payroll Pipeline (18 Steps):**
```
Step01 Snapshots       -> Step02 PeriodMeta      -> Step03 AttendanceSummary
Step04 LoadYTD         -> Step05 BasicPay         -> Step06 OvertimePay
Step07 HolidayPay      -> Step08 NightDiff        -> Step09 GrossPay
Step10 SSS             -> Step11 PhilHealth       -> Step12 PagIBIG
Step13 TaxableIncome   -> Step14 WithholdingTax   -> Step15 LoanDeductions
Step16 OtherDeductions -> Step17 NetPay           -> Step18 ThirteenthMonth
```

**Payroll Run States (14):**
```
DRAFT -> SCOPE_SET -> PRE_RUN_CHECKED -> PROCESSING -> COMPUTED ->
REVIEW -> SUBMITTED -> HR_APPROVED -> ACCTG_APPROVED -> VP_APPROVED ->
DISBURSED -> PUBLISHED
(+ RETURNED, REJECTED -> back to DRAFT)
```

### 4.3 Finance & Accounting

| Domain | Models | Services | Key Features |
|--------|--------|----------|-------------|
| **Accounting** | ChartOfAccount, FiscalPeriod, JournalEntry, JournalEntryLine, JournalEntryTemplate, RecurringJournalTemplate, BankAccount, BankTransaction, BankReconciliation | JournalEntryService, ChartOfAccountService, FiscalPeriodService, GeneralLedgerService, TrialBalanceService, BalanceSheetService, IncomeStatementService, CashFlowService, FinancialRatioService, BankReconciliationService, PayrollAutoPostService, RecurringJournalTemplateService, YearEndClosingService | Full GL, PFRS-compliant COA, financial statements, bank reconciliation, recurring entries, year-end closing |
| **AP** | Vendor, VendorInvoice, VendorPayment, VendorItem, VendorCreditNote, VendorFulfillmentNote, EwtRate, PaymentBatch, PaymentBatchItem | VendorService, VendorInvoiceService, VendorFulfillmentService, VendorItemService, VendorCreditNoteService, ApPaymentPostingService, EwtService, EarlyPaymentDiscountService, InvoiceAutoDraftService, PaymentBatchService | 5-step invoice approval, EWT computation, payment batches, vendor portal, credit notes, auto-draft from GR |
| **AR** | Customer, CustomerInvoice, CustomerPayment, CustomerAdvancePayment, CustomerCreditNote, DunningLevel, DunningNotice | CustomerService, CustomerInvoiceService, CustomerCreditNoteService, ArAgingService, DunningService, InvoiceAutoDraftService, PaymentAllocationService | Customer management, aging reports, dunning workflow, advance payments, credit notes |
| **Tax** | VatLedger, BirFiling | VatLedgerService, BirFilingService, BirAutoPopulationService, BirFormGeneratorService, BirPdfGeneratorService | VAT ledger, BIR form generation (Philippine tax compliance), auto-population |
| **Budget** | AnnualBudget, BudgetAmendment, CostCenter | BudgetService, BudgetEnforcementService, BudgetForecastService, BudgetVarianceService, BudgetAmendmentService | Cost centers, annual budgets with approval workflow, variance analysis, PR budget checks |
| **Fixed Assets** | FixedAsset, FixedAssetCategory, AssetDepreciationEntry, AssetDisposal, AssetTransfer | FixedAssetService, AssetRevaluationService | Asset tracking (PG trigger sets asset_code), depreciation, disposal, transfers, revaluation |

### 4.4 Supply Chain

| Domain | Models | Services | Key Features |
|--------|--------|----------|-------------|
| **Procurement** | PurchaseRequest, PurchaseRequestItem, PurchaseOrder, PurchaseOrderItem, GoodsReceipt, GoodsReceiptItem, VendorRfq, VendorRfqVendor, BlanketPurchaseOrder | PurchaseRequestService, PurchaseOrderService, GoodsReceiptService, ThreeWayMatchService, VendorRfqService, VendorScoringService, BlanketPurchaseOrderService | 4-stage PR workflow (draft->pending_review->reviewed->budget_verified->approved), PO negotiation, 3-way matching, vendor RFQs, vendor scoring, blanket POs |
| **Inventory** | ItemMaster, ItemCategory, StockBalance, StockLedger, WarehouseLocation, MaterialRequisition, MaterialRequisitionItem, PhysicalCount, PhysicalCountItem, LotBatch, StockReservation | StockService, ItemMasterService, MaterialRequisitionService, PhysicalCountService, InventoryAnalyticsService, InventoryReportService, CostingMethodService, LowStockReorderService, StockReservationService | Stock ledger (audit trail via StockService only), MRQ->PR conversion, physical counts, lot/batch tracking, stock reservations, reorder points |

### 4.5 Manufacturing

| Domain | Models | Services | Key Features |
|--------|--------|----------|-------------|
| **Production** | ProductionOrder, ProductionOutputLog, BillOfMaterials, BomComponent, DeliverySchedule, CombinedDeliverySchedule, WorkCenter, Routing | ProductionOrderService, BomService, CostingService, CapacityPlanningService, MrpService, DeliveryScheduleService, CombinedDeliveryScheduleService, OrderAutomationService, ProductionCostPostingService, ProductionReportService | BOM management with costing, MRP, capacity planning, work center routing, auto-creation from client orders, production cost GL posting |
| **QC** | Inspection, InspectionResult, InspectionTemplate, InspectionTemplateItem, NonConformanceReport, CapaAction | InspectionService, InspectionTemplateService, NcrService, QualityAnalyticsService, QuarantineService, SpcService, SupplierQualityService | Inspection templates, NCR management, CAPA workflow (state machine), SPC analysis, supplier quality scoring, quarantine |
| **Maintenance** | Equipment, MaintenanceWorkOrder, PmSchedule, WorkOrderPart | MaintenanceService, WorkOrderService, EquipmentService, MaintenanceAnalyticsService | Equipment registry, preventive maintenance scheduling, work orders with parts, labor hours tracking, analytics |
| **Mold** | MoldMaster, MoldShotLog | MoldService, MoldAnalyticsService | Mold lifecycle tracking, shot counting, cost amortization, analytics |

### 4.6 Sales & Distribution

| Domain | Models | Services | Key Features |
|--------|--------|----------|-------------|
| **CRM** | ClientOrder, ClientOrderItem, ClientOrderActivity, ClientOrderDeliverySchedule, Lead, Contact, Opportunity, Ticket, TicketMessage | ClientOrderService, LeadService, LeadScoringService, OpportunityService, OrderTrackingService, SalesAnalyticsService, TicketService | Client orders with negotiation tracking, lead scoring, opportunity pipeline, CRM tickets with SLA, order-to-production automation |
| **Sales** | Quotation, QuotationItem, SalesOrder, SalesOrderItem, PriceList, PriceListItem | QuotationService, SalesOrderService, PricingService, ProfitMarginService | Quotation management, sales orders, price lists, profit margin analysis |
| **Delivery** | DeliveryReceipt, DeliveryReceiptItem, Shipment, Vehicle, DeliveryRoute, ImpexDocument | DeliveryReceiptService, DeliveryService, ShipmentService, ProofOfDeliveryService | Delivery receipts, shipment tracking, fleet management, proof of delivery, import/export documents |

### 4.7 Compliance & Quality

| Domain | Models | Services | Key Features |
|--------|--------|----------|-------------|
| **ISO** | ControlledDocument, DocumentRevision, DocumentDistribution, InternalAudit, AuditFinding, ImprovementAction | ISOService, DocumentControlService, AuditService, DocumentAcknowledgmentService | ISO 9001 document control, revision tracking, distribution acknowledgment, internal audits, improvement actions |

### 4.8 Cross-Domain

| Domain | Services | Key Features |
|--------|----------|-------------|
| **Dashboard** | DashboardKpiService, RoleBasedDashboardService | Role-based KPI dashboards |

---

## 5. Shared Building Blocks

### 5.1 Value Objects (`app/Shared/ValueObjects/`)

| VO | Description | Key Rules |
|----|-------------|-----------|
| `Money` | Monetary amounts in centavos (int) | Never use float; `fromCentavos()` throws on negative; ROUND_HALF_UP for division |
| `Minutes` | Time duration | Used in attendance/payroll calculations |
| `PayPeriod` | Payroll period definition | Encapsulates cutoff dates and pay date |
| `DateRange` | Date interval | Immutable start/end date pair |
| `EmployeeCode` | Formatted employee ID | Pattern: `EMP-YYYY-NNN` |
| `WorkingDays` | Working days count | Used for pro-rating calculations |
| `OvertimeMultiplier` | OT rate multiplier | Philippine labor law rates |

### 5.2 Domain Exception Hierarchy

Base: `DomainException(message, errorCode, httpStatus, context[])` extends `RuntimeException`

Specialized exceptions:
- `AuthorizationException` - Permission denied
- `SodViolationException` - Segregation of Duties violation
- `InvalidStateTransitionException` - State machine violation
- `ValidationException` - Business validation failure
- `NegativeNetPayException` - Payroll net pay < 0
- `DuplicatePayrollRunException` - Overlapping payroll periods
- `InsufficientLeaveBalanceException` - Leave balance exceeded
- `CreditLimitExceededException` - Customer credit limit
- `LockedPeriodException` - Writing to locked fiscal period
- `UnbalancedJournalEntryException` - Debits != Credits
- `ContributionTableNotFoundException` - Missing government rate table
- `TaxTableNotFoundException` - Missing tax bracket

### 5.3 Key Traits

- **`HasPublicUlid`** - Adds ULID column for URL routing (requires SoftDeletes)
- **`HasDepartmentScope`** - Auto-applies department-based query filtering
- **`ApiResponse`** - Standard JSON response formatting
- **`HasApprovalWorkflow`** - Multi-step approval with SoD enforcement

---

## 6. Security & Authorization

### RBAC Roles (Reversed Hierarchy)
```
Officer (highest access)
  -> Manager
    -> Head
      -> Staff (lowest access)

Special roles: admin, super_admin, executive, vice_president
```

### Key Security Features
- **Session-cookie auth** (Sanctum) -- no JWT, no localStorage tokens
- **SoD enforcement**: Creator cannot approve own records (middleware + policy level)
- **Department scoping**: Auto-applied via middleware; only admin/super_admin/executive/VP bypass
- **Module access middleware**: Controls which departments see which modules
- **Government ID encryption**: AES-256 encrypted + SHA-256 hash for uniqueness
- **Rate limiting**: Applied via Laravel's built-in rate limiter
- **Audit trails**: All model changes logged via `owen-it/laravel-auditing`
- **Security headers middleware**: CSP, HSTS, X-Frame-Options

---

## 7. Database

### Migration Strategy
- 222 migrations spanning 2026-02-23 to 2026-03-28
- PostgreSQL-specific features used extensively:
  - Stored generated columns (`daily_rate`, `hourly_rate`)
  - CHECK constraints for enums (never `$table->enum()`)
  - Triggers (employee hard-delete prevention, asset_code generation, production qty_rejected)
- Money columns: always `unsignedBigInteger` (centavos)
- Every table has: `$table->ulid('ulid')->unique()` for public routing
- Soft deletes on all tables (added in migration `2026_03_07_200000`)

### Seeder Dependency Chain (strict order)
1. **Configuration tables** (zero dependencies): SystemSettings, Tax brackets, SSS/PhilHealth/PagIBIG rates, Minimum wage, OT multipliers, Holiday calendar
2. **RBAC**: RolePermissionSeeder -> Modules -> ModulePermissions -> SampleAccounts
3. **HR reference**: SalaryGrades, LeaveTypes, LoanTypes, ShiftSchedules
4. **Accounting reference**: ChartOfAccounts
5. **Organizational**: FiscalPeriods, Departments/Positions, DepartmentModuleAssignment, DepartmentPermissionProfiles, ReversedHierarchyPermissions
6. **Employee data**: ConsolidatedEmployeeSeeder, ComprehensiveTestAccounts
7. **Transactional**: SampleData, LeaveBalances, Attendance, Payroll samples

---

## 8. Testing

### Test Suites

| Suite | Location | Count | DB Required | Description |
|-------|----------|-------|-------------|-------------|
| Unit/Shared | `tests/Unit/Shared/` | 1 | No | Pure value object tests (Money) |
| Unit/Payroll | `tests/Unit/Payroll/` | 11 | Yes | Contribution, tax, deduction, golden suite, edge cases, property-based |
| Feature | `tests/Feature/` | ~60 | Yes | HTTP endpoint tests per domain |
| Integration | `tests/Integration/` | 10 | Yes | Cross-domain workflows (AP->GL, Payroll->GL, Procurement->Inventory, etc.) |
| Arch | (in Feature) | | No | Structural constraint enforcement |

### Key Test Patterns
- **Always PostgreSQL** (`ogami_erp_test`) -- never SQLite
- **RefreshDatabase** for Feature and Integration suites
- **RBAC seeding** required: `$this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])`
- **PayrollTestHelper**: Strips generated columns, handles field aliases
- **Custom Pest expectations**: `->toBeValidationError('field')`, `->toBeDomainError('ERROR_CODE')`
- **Golden Suite**: 24 canonical payroll scenarios with fixed expected values
- **Load tests**: k6 scripts for payroll stress testing

### Notable Integration Tests
- `APPaymentToGLTest` - AP payment creates correct GL entries
- `ARToBankingTest` - AR collection flows to banking
- `PayrollToGLTest` - Payroll posting creates balanced journal entries
- `ProcurementToInventoryTest` - GR updates stock balances
- `ProductionToInventoryTest` - Production output updates finished goods
- `ClientOrderToDeliveryWorkflowTest` - End-to-end order fulfillment
- `LeaveAttendancePayrollTest` - Leave affects attendance affects payroll

---

## 9. Frontend Deep Dive

### Pages by Domain (241 total)

| Domain | Pages | Key Pages |
|--------|-------|-----------|
| Payroll | 14 | Wizard (Create, DraftScope, DraftValidate), 7 review stages, PayPeriods |
| Accounting | 21 | COA, GL, JE CRUD, Trial Balance, Balance Sheet, Income Statement, Cash Flow, Financial Ratios, AP/AR views, VAT Ledger |
| Procurement | 15 | PR CRUD, PO CRUD, GR CRUD, Vendor RFQs, Vendor Scorecards, Payment Batches, Analytics |
| Production | 16 | BOM CRUD, Production Orders, Delivery Schedules, MRP, Capacity Planning, Cost Breakdown |
| HR | 3+ | Employee List/Detail/Form |
| Inventory | Multiple | Item Masters, Stock, MRQ, Physical Counts, Warehouse |
| CRM | Multiple | Client Orders, Leads, Opportunities, Tickets |
| Sales | Multiple | Quotations, Sales Orders, Price Lists |
| QC | Multiple | Inspections, Templates, NCRs, CAPA |
| Delivery | Multiple | Receipts, Shipments, Routes |
| And more... | | Budget, Fixed Assets, ISO, Maintenance, Mold, Tax, Leave, Loan, Attendance |

### Hooks Pattern (TanStack Query)
Each hook file (`use<Domain>.ts`) exports:
- **Query hooks**: `useQuery` with proper `queryKey` arrays including filter params
- **Mutation hooks**: `useMutation` with `onSuccess` cache invalidation
- **Stale time**: Typically 15-30 seconds
- **Error handling**: Standardized via API interceptor

### Zod Schemas
25 schema files providing:
- Client-side form validation
- `z.coerce.number()` for numeric fields
- Type-safe form data extraction
- Consistent error messages

### Router Architecture
- Single `router/index.tsx` file (703 lines)
- All pages lazy-loaded via `React.lazy()`
- `RequirePermission` guard component with pipe-separated OR logic
- `RoleLandingRedirect` for role-aware home page
- `PayrollWizardProvider` context wrapping payroll wizard routes

---

## 10. Cross-Domain Integrations

```
Client Order (CRM)
  |-> Production Order (auto-created)
  |     |-> Material Requisition (Inventory)
  |     |     |-> Purchase Request (Procurement)
  |     |           |-> Purchase Order
  |     |                 |-> Goods Receipt
  |     |                       |-> Stock Update (via StockService)
  |     |                       |-> Vendor Invoice (AP, auto-draft)
  |     |-> QC Inspection
  |     |-> Production Output -> Stock Update
  |-> Delivery Schedule
        |-> Delivery Receipt
              |-> Customer Invoice (AR)
                    |-> Payment -> Banking

Employee (HR)
  |-> Attendance Logs
  |     |-> Overtime Requests
  |-> Leave Requests
  |     |-> Leave Balances
  |-> Loans
  |     |-> Amortization Schedule
  |-> Payroll Run
        |-> 18-step Pipeline Computation
        |-> GL Posting (Accounting)
        |-> Government Contributions (SSS, PhilHealth, PagIBIG)
        |-> Tax Withholding (TRAIN law)
        |-> Payslip PDF Generation

Accounting Hub:
  - Payroll -> Journal Entries (auto-post)
  - AP Payments -> Journal Entries
  - AR Collections -> Journal Entries
  - Production Costs -> Journal Entries
  - Fixed Asset Depreciation -> Journal Entries
  - Budget -> Purchase Request validation
```

---

## 11. Philippine Business Compliance

The system is specifically designed for Philippine labor and tax law:

- **TRAIN Law** tax computation (progressive brackets)
- **SSS** contribution tables (employee + employer shares)
- **PhilHealth** premium tables
- **Pag-IBIG** contribution tables
- **13th Month Pay** computation and accrual
- **BIR** form generation (tax filings)
- **VAT Ledger** for BIR reporting
- **EWT** (Expanded Withholding Tax) on vendor payments
- **Minimum Wage** compliance checking
- **DOLE** overtime rate multipliers (regular, special non-working, regular holiday, double holiday)
- **AD-084** leave type form compliance
- **SIL** (Service Incentive Leave) monetization

---

## 12. DevOps & Infrastructure

### Development
```bash
npm run dev          # PG + Redis + Laravel:8000 + Vite:5173 + queue
npm run dev:minimal  # Without queue/Reverb
npm run dev:full     # With Reverb WebSocket
```

### Docker
- `Dockerfile` + `docker/` directory for containerized deployment
- Docker Compose for local development

### CI / Quality Gates
- PHPStan (Larastan) level 5 with baseline
- Laravel Pint for code formatting
- ESLint + TypeScript strict mode
- Pest test suites (Unit, Feature, Integration, Arch)
- Vitest for frontend
- Playwright for E2E

### Key Configuration Files
- `phpunit.xml` - Test config (force PostgreSQL)
- `phpstan.neon` + `phpstan-baseline.neon` - Static analysis
- `kilo.json` - Project metadata
- `.npmrc` - pnpm configuration
- `frontend/vite.config.ts` - Vite build config
- `frontend/vitest.config.ts` - Frontend test config

---

## 13. Notable Design Decisions

1. **Integer centavos for money** - Avoids IEEE 754 floating-point precision issues
2. **ULID public keys** - Opaque, non-enumerable URLs while keeping integer PKs for FK performance
3. **PostgreSQL stored generated columns** - `daily_rate` and `hourly_rate` computed at DB level, never in PHP
4. **Pipeline pattern for payroll** - Each step is a single-responsibility invokable class mutating a shared context
5. **State machines as constants** - Simple array-based transition maps rather than a state machine library
6. **Strict SoD enforcement** - Both middleware-level and policy-level checks to prevent same-user create-and-approve
7. **Department-scoped queries** - Global scope auto-applied, with explicit bypass for admin roles
8. **Two Zustand stores only** - All server state in TanStack Query; Zustand reserved for auth and UI state
9. **Write cooldown on API client** - Prevents button-spam without server-side idempotency keys
10. **Government ID encryption + hash** - Encrypted for storage, hashed for uniqueness constraints
11. **No SQLite in tests** - PostgreSQL-specific features (triggers, generated columns, CHECK constraints) require real PG
12. **Reversed hierarchy** - Officer has MORE access than Manager, which has more than Head, which has more than Staff

---

## 14. File Reference Index

| Path | Purpose |
|------|---------|
| `AGENTS.md` | Full technical reference for AI agents |
| `CLAUDE.md` | Domain-specific gotchas, commands, architecture overview |
| `MODULES.md` | Module listing |
| `FLOWCHARTS.md` | Business process flowcharts |
| `MODULE_ANALYSIS.md` | Module gap analysis |
| `sod.md` | Segregation of Duties documentation |
| `system_specs.md` | System specifications |
| `docs/testing/` | Testing guides |
| `plans/` | Enhancement and improvement plans |
| `.github/instructions/` | Copilot rules per concern |
| `.agents/skills/` | Specialized AI skills (payroll-debugger, migration-writer, code-reviewer, budget-planner) |
