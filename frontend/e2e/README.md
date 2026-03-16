# Ogami ERP E2E Testing Guide

## Overview

End-to-end tests using Playwright covering critical business workflows across all 20 domains.

**Total Test Coverage: 93+ test scenarios**

## Test Structure

```
e2e/
├── auth.spec.ts              # Authentication flows (5 tests)
├── accounting.spec.ts        # GL, JE, Financial reports (8 tests)
├── ap-workflow.spec.ts       # AP Invoice → Payment (7 tests) ⭐ NEW
├── hr-onboarding.spec.ts     # Employee management (8 tests)
├── leave.spec.ts             # Leave requests (7 tests)
├── payroll.spec.ts           # Payroll runs (7 tests)
├── procurement.spec.ts       # PR → PO → GR (10 tests)
├── inventory-workflow.spec.ts # Items, MR, Stock (8 tests) ⭐ NEW
├── production-workflow.spec.ts # Production orders (8 tests) ⭐ NEW
├── qc-workflow.spec.ts       # Inspections, NCR, CAPA (8 tests) ⭐ NEW
├── sod.spec.ts               # SoD compliance (15+ tests)
└── setup/
    └── auth.setup.ts         # Shared auth session setup
```

## Running Tests

### Prerequisites

Both servers must be running:

```bash
# Terminal 1 - Backend
php artisan serve --port=8000

# Terminal 2 - Frontend
cd frontend && pnpm dev
```

### Run All Tests

```bash
cd frontend
pnpm e2e
```

### Run Specific Test File

```bash
pnpm e2e accounting.spec.ts
pnpm e2e procurement.spec.ts
pnpm e2e ap-workflow.spec.ts
```

### Run with UI Mode

```bash
pnpm e2e:ui
```

### Run Headed (visible browser)

```bash
pnpm e2e:headed
```

### View Test Report

```bash
pnpm e2e:report
```

## Critical Workflow Coverage

### 1. Procurement → AP → GL (End-to-End)
- PR-01: Create Purchase Request
- PR-02: Submit for approval
- PR-03: Head notes the PR
- PR-04: Manager checks the PR
- PR-05: Officer reviews the PR
- PR-06: VP approves the PR
- PO-01: Convert PR to PO
- PO-02: Send PO to vendor
- GR-01: Receive goods against PO
- AP-01: Create invoice from GR
- AP-02: Submit invoice for approval
- AP-03: Process payment
- GL-01: Verify AP entries posted

### 2. Production Workflow
- PROD-01: Create production order
- PROD-02: Check BOM components
- PROD-03: Release order (stock check)
- PROD-04: Record output
- PROD-05: Complete order

### 3. Quality Control
- QC-01: Create inspection (IQC/IPQC/OQC)
- QC-02: Record results
- QC-03: Fail → Create NCR
- QC-04: NCR → CAPA action

## Test Data Requirements

Tests assume the following seeded data exists:

- Admin user: `admin@ogamierp.local` / `Admin@1234567890!`
- Chart of Accounts (standard Philippine GL structure)
- Departments (HR, Finance, Operations, etc.)
- At least one active vendor
- At least one active customer
- Sample employees with completed profiles

## Troubleshooting

### Tests failing with connection errors
Ensure both servers are running:
- Backend: http://localhost:8000
- Frontend: http://localhost:5173

### Authentication failures
Delete auth state and rerun setup:
```bash
rm frontend/e2e/.auth/admin.json
pnpm e2e
```

### Element not found errors
Tests use timeouts and retries. If consistently failing:
1. Check if UI selectors changed
2. Verify test data exists
3. Run with UI mode to debug: `pnpm e2e:ui`

## Adding New Tests

Follow the naming convention:
- `DOMAIN-XXX` for test IDs (e.g., `PROC-01`, `AP-WF-01`)
- Group related tests in `test.describe()` blocks
- Use `BASE` constant for URLs
- Always check for 500 errors first
- Use timeouts: `{ timeout: 10_000 }` or `{ timeout: 15_000 }`

Example:
```typescript
test('DOMAIN-XX descriptive test name', async ({ page }) => {
    await page.goto(`${BASE}/path/to/page`)
    await page.waitForLoadState('networkidle')
    
    // Verify no error
    await expect(page.locator('body')).not.toContainText('500')
    
    // Test assertions
    await expect(page.locator('selector')).toBeVisible()
})
```

## CI/CD Integration

Tests are configured to run sequentially (`workers: 1`) due to shared test data.

For CI environments:
```bash
# Headless with retries
pnpm e2e -- --reporter=html

# Upload report as artifact
```
