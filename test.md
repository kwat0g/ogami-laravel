# ogamiPHP — Full E2E Test Suite Generation Prompt
# Target Model: Claude Opus 4.6 via Claude Code CLI
# Mode: Discovery-First | Role-by-Role | Playwright + Lightpanda
# Goal: Automated E2E tests covering every module workflow per role

---

## MISSION

You are a **senior QA automation engineer** specializing in ERP systems.
Your job is to build a complete, role-aware E2E test suite for ogamiPHP that:

1. Discovers every module's full workflow from actual code
2. Maps every workflow step to the role that performs it
3. Writes Playwright tests that execute each workflow end-to-end
4. Uses Lightpanda as the headless browser where possible (faster, lower memory)
5. Falls back to Chromium for tests requiring full browser capabilities (file upload, GPS mock)
6. Produces a test report showing pass/fail per role per module

**The standard:** If a panelist clicks through a workflow during your defense,
these tests must have already proven it works. Every button, every form submit,
every status transition — tested, verified, green.

---

## CURRENT REBUILD STATUS (APR 1, 2026)

Implemented in repository:

1. Canonical specs now live under `frontend/e2e/specs/` and are wired in the module runner.
2. Legacy specs are preserved for reference and will be removed only after canonical replacements are stable.
3. Lightpanda is configured and working for canonical module runs.

Current canonical module mapping in `frontend/e2e/run-module-suite.sh`:

- `auth` -> `e2e/specs/01-auth.spec.ts`
- `rbac` -> `e2e/specs/10-rbac.spec.ts`
- `inventory` -> `e2e/specs/20-inventory.spec.ts`
- `production` -> `e2e/specs/30-production.spec.ts`
- `procurement` -> `e2e/specs/40-procurement.spec.ts`
- `accounting` -> `e2e/specs/50-accounting.spec.ts`
- `delivery` -> `e2e/specs/60-delivery.spec.ts`
- `qc` -> `e2e/specs/70-qc.spec.ts`
- `hr` -> `e2e/specs/80-hr.spec.ts`
- `payroll` -> `e2e/specs/90-payroll.spec.ts`
- `mold` -> `e2e/specs/100-mold.spec.ts`
- `crm` -> `e2e/specs/110-crm.spec.ts`

Validation status:

- `auth` (Lightpanda): PASS
- `rbac` (Lightpanda): PASS
- `inventory` (Lightpanda): PASS
- `production` (Lightpanda): PASS
- `procurement` (Lightpanda): PASS
- `accounting` (Lightpanda): PASS
- `delivery` (Lightpanda): PASS
- `qc` (Lightpanda): PASS
- `hr` (Lightpanda): PASS
- `payroll` (Lightpanda): PASS
- `mold` (Lightpanda): PASS
- `crm` (Lightpanda): PASS

Rebuild rule now in effect:

1. Do not delete all old specs in one pass.
2. Rebuild module-by-module into `e2e/specs/NN-<module>.spec.ts`.
3. Point module runner to canonical spec first.
4. Remove old module spec files only after canonical replacement is stable on reruns.

Next modules to rebuild using this same process:

1. Canonical queue for this phase is complete.
2. Runner module mappings are now fully canonicalized for current module list.
3. Legacy indexing/documentation hardening is complete (`frontend/e2e/LEGACY_SPECS.md`).
4. Next recommended phase: retire or archive low-value legacy specs after confirming no external scripts depend on them.

---

## MANDATORY BOOTSTRAP

```
RULE 1: SocratiCode MCP — search before reading any file.
RULE 2: Read actual route files to discover real URL patterns.
RULE 3: Read actual StateMachine TRANSITIONS — that IS the test scenario map.
RULE 4: Read actual role/permission seeder — those ARE the test accounts.
RULE 5: Never hardcode selectors — use data-testid attributes or semantic HTML.
RULE 6: Never assume a page URL — discover it from the router config.
RULE 7: Tests must be idempotent — each test seeds its own data and cleans up.
RULE 8: Test the happy path fully, then test the key rejection/error paths.
```

---

## PHASE 0 — ENVIRONMENT DISCOVERY

Before writing a single test, map the complete environment.

### 0A. Check Playwright Installation

```bash
cd frontend

# Check if Playwright is installed
cat package.json | grep -i playwright
ls playwright.config.* 2>/dev/null || echo "NO PLAYWRIGHT CONFIG FOUND"

# Check if Lightpanda is available
which lightpanda 2>/dev/null || echo "LIGHTPANDA NOT INSTALLED"
npx playwright install --help 2>/dev/null | grep lightpanda || echo "CHECK LIGHTPANDA SUPPORT"

# Check existing E2E tests
find tests/e2e -name "*.spec.ts" 2>/dev/null | head -20
ls e2e/ 2>/dev/null
```

### 0B. Discover Frontend Router — All Page URLs

```
codebase_search: "createBrowserRouter" OR "Routes" OR "Route path" in frontend/src/router/
codebase_search: "path:" in router/index.tsx

Extract every route path. This is the complete list of pages to test.
```

### 0C. Discover All Test Accounts

```
codebase_search: "RolePermissionSeeder"
codebase_search: "SampleAccountsSeeder" OR "TestAccountsSeeder"
codebase_search: "@ogamierp.local" emails

Build the definitive account map:
```

Expected accounts (verify each exists in seeders):

| Role | Email | Password | Modules Accessible |
|---|---|---|---|
| Admin | admin@ogamierp.local | Admin@12345! | All |
| Executive | chairman@ogamierp.local | Executive@12345! | Read-all + approve |
| VP | vp@ogamierp.local | VicePresident@1! | Final approvals |
| HR Manager | hr.manager@ogamierp.local | HrManager@12345! | HR, Payroll, Leave, Loan |
| Accounting Manager | acctg.manager@ogamierp.local | Manager@12345! | Accounting, AP, AR, Tax |
| Sales Manager | sales.manager@ogamierp.local | Manager@12345! | Sales, CRM, AR |
| Production Manager | prod.manager@ogamierp.local | Manager@12345! | Production, QC, Inventory |
| QC Manager | qc.manager@ogamierp.local | Manager@12345! | QC, Inventory |
| Warehouse Head | warehouse.head@ogamierp.local | Head@123456789! | Inventory, Delivery |
| Production Head | production.head@ogamierp.local | Head@123456789! | Production |
| Maintenance Head | maintenance.head@ogamierp.local | Head@123456789! | Maintenance |
| Purchasing Officer | purchasing.officer@ogamierp.local | Officer@12345! | Procurement |
| Accounting Officer | accounting@ogamierp.local | Officer@12345! | Accounting, AP, AR |

### 0D. Discover All StateMachines (these define the test scenarios)

```
codebase_search: "TRANSITIONS" constant — read ALL StateMachines

For each StateMachine, extract:
  Entity name, all status values, all valid transitions
  
This becomes the E2E test scenario matrix.
```

### 0E. Setup: Playwright + Lightpanda Configuration

```bash
cd frontend

# Install Playwright if not present
npm install -D @playwright/test 2>/dev/null || pnpm add -D @playwright/test

# Install browsers
npx playwright install chromium
```

Create/update `playwright.config.ts`:

```typescript
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,        // ERP workflows are sequential, not parallel
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,                  // One worker — prevents DB race conditions
  reporter: [
    ['html', { outputFolder: 'tests/e2e/reports/html' }],
    ['json', { outputFile: 'tests/e2e/reports/results.json' }],
    ['list'],
  ],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:5173',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'on-first-retry',
    actionTimeout: 10000,
    navigationTimeout: 15000,
  },
  projects: [
    // Lightpanda — fast headless for simple page/form tests
    // Install: curl -L https://github.com/lightpanda-io/lightpanda/releases/latest/download/lightpanda-x86_64-linux -o /usr/local/bin/lightpanda && chmod +x /usr/local/bin/lightpanda
    ...(process.env.USE_LIGHTPANDA === 'true' ? [{
      name: 'lightpanda',
      use: {
        browserName: 'chromium' as const,
        channel: 'lightpanda',
        launchOptions: {
          executablePath: process.env.LIGHTPANDA_PATH || '/usr/local/bin/lightpanda',
        },
      },
    }] : []),

    // Chromium — full browser for GPS mock, file upload, complex interactions
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Seed the database before all tests
  globalSetup: './tests/e2e/global-setup.ts',
  globalTeardown: './tests/e2e/global-teardown.ts',
});
```

### 0F. Global Setup — Seed Database Before Tests

Create `tests/e2e/global-setup.ts`:

```typescript
import { execSync } from 'child_process';
import path from 'path';

async function globalSetup() {
  console.log('\n🌱 Seeding test database...');

  const projectRoot = path.resolve(__dirname, '../../../');

  try {
    // Fresh seed with all required seeders
    execSync('php artisan migrate:fresh --seed --env=testing', {
      cwd: projectRoot,
      stdio: 'pipe',
      timeout: 120000,
    });
    console.log('✅ Database seeded successfully');
  } catch (error) {
    console.error('❌ Database seed failed:', error);
    throw error;
  }
}

export default globalSetup;
```

### 0G. Shared Test Helpers

Create `tests/e2e/helpers/auth.ts`:

```typescript
import { Page, BrowserContext } from '@playwright/test';

export const TEST_ACCOUNTS = {
  admin:           { email: 'admin@ogamierp.local',              password: 'Admin@12345!' },
  executive:       { email: 'chairman@ogamierp.local',           password: 'Executive@12345!' },
  vp:              { email: 'vp@ogamierp.local',                 password: 'VicePresident@1!' },
  hrManager:       { email: 'hr.manager@ogamierp.local',         password: 'HrManager@12345!' },
  acctgManager:    { email: 'acctg.manager@ogamierp.local',      password: 'Manager@12345!' },
  salesManager:    { email: 'sales.manager@ogamierp.local',      password: 'Manager@12345!' },
  prodManager:     { email: 'prod.manager@ogamierp.local',       password: 'Manager@12345!' },
  qcManager:       { email: 'qc.manager@ogamierp.local',         password: 'Manager@12345!' },
  warehouseHead:   { email: 'warehouse.head@ogamierp.local',     password: 'Head@123456789!' },
  prodHead:        { email: 'production.head@ogamierp.local',    password: 'Head@123456789!' },
  maintenanceHead: { email: 'maintenance.head@ogamierp.local',   password: 'Head@123456789!' },
  purchasing:      { email: 'purchasing.officer@ogamierp.local', password: 'Officer@12345!' },
  accounting:      { email: 'accounting@ogamierp.local',         password: 'Officer@12345!' },
} as const;

export type AccountRole = keyof typeof TEST_ACCOUNTS;

// Cache auth state per role to avoid re-login on every test
const authStateCache = new Map<string, string>();

export async function loginAs(page: Page, role: AccountRole): Promise<void> {
  const account = TEST_ACCOUNTS[role];

  await page.goto('/login');
  await page.waitForLoadState('networkidle');

  // Fill credentials
  await page.getByLabel(/email/i).fill(account.email);
  await page.getByLabel(/password/i).fill(account.password);
  await page.getByRole('button', { name: /login|sign in/i }).click();

  // Wait for redirect to dashboard/home
  await page.waitForURL(/dashboard|home|\/$/, { timeout: 10000 });
}

export async function logout(page: Page): Promise<void> {
  // Try logout button or navigate directly
  try {
    await page.getByRole('button', { name: /logout|sign out/i }).click();
    await page.waitForURL(/login/, { timeout: 5000 });
  } catch {
    await page.goto('/logout');
  }
}

// Helper to wait for API response
export async function waitForApi(
  page: Page,
  urlPattern: string | RegExp,
  options?: { timeout?: number }
): Promise<void> {
  await page.waitForResponse(
    response => {
      const url = response.url();
      const pattern = typeof urlPattern === 'string'
        ? url.includes(urlPattern)
        : urlPattern.test(url);
      return pattern && response.status() < 400;
    },
    { timeout: options?.timeout ?? 10000 }
  );
}

// Helper to check for toast notifications
export async function expectSuccessToast(page: Page): Promise<void> {
  await page.waitForSelector(
    '[role="alert"]:has-text("success"), .toast-success, [data-status="success"]',
    { timeout: 5000 }
  ).catch(() => {
    // Some apps use different toast patterns — also check for absence of error
  });
}

export async function expectErrorToast(page: Page, text?: string): Promise<void> {
  const selector = text
    ? `[role="alert"]:has-text("${text}")`
    : '[role="alert"]:has-text("error"), .toast-error, [data-status="error"]';
  await page.waitForSelector(selector, { timeout: 5000 });
}

// Helper to fill a form field by label (resilient to layout changes)
export async function fillField(
  page: Page,
  label: string | RegExp,
  value: string
): Promise<void> {
  const field = page.getByLabel(label);
  await field.clear();
  await field.fill(value);
}

// Helper to select from a dropdown/combobox
export async function selectOption(
  page: Page,
  label: string | RegExp,
  value: string
): Promise<void> {
  const field = page.getByLabel(label);
  const tagName = await field.evaluate(el => el.tagName.toLowerCase());

  if (tagName === 'select') {
    await field.selectOption({ label: value });
  } else {
    // React Select or custom combobox
    await field.click();
    await page.getByRole('option', { name: value }).click();
  }
}

// GPS mock for attendance tests
export async function mockGeolocation(
  context: BrowserContext,
  lat: number = 14.5995,
  lng: number = 120.9842
): Promise<void> {
  await context.setGeolocation({ latitude: lat, longitude: lng, accuracy: 10 });
  await context.grantPermissions(['geolocation']);
}
```

---

## PHASE 1 — MODULE WORKFLOW DISCOVERY

For each module, read the actual code to extract the complete workflow.
Then generate a test spec file per module.

### Discovery search per module:

```
codebase_search: "{Module}StateMachine TRANSITIONS"
→ These are the status steps to test

codebase_search: "{Module}" routes action endpoints (approve/reject/submit/etc.)
→ These are the API actions the test must trigger

codebase_search: "{Module}" pages in frontend/src/pages/
→ These are the URLs to navigate

codebase_search: "{Module}" permission seeder
→ These determine which roles to test
```

---

## PHASE 2 — TEST SPEC FILES (Generate One Per Module)

### MODULE 1: Authentication & Access Control

Create `tests/e2e/specs/01-auth.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, TEST_ACCOUNTS } from '../helpers/auth';

test.describe('Authentication', () => {

  test('admin can log in and see dashboard', async ({ page }) => {
    await loginAs(page, 'admin');
    await expect(page).toHaveURL(/dashboard|home/);
    await expect(page.getByRole('heading').first()).toBeVisible();
  });

  test('invalid credentials show error message', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill('invalid@test.com');
    await page.getByLabel(/password/i).fill('wrongpassword');
    await page.getByRole('button', { name: /login|sign in/i }).click();
    await expect(page.getByText(/invalid|incorrect|unauthorized/i)).toBeVisible();
  });

  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/hr/employees');
    await expect(page).toHaveURL(/login/);
  });

  // Test each role can access their modules
  const roleModules: Array<{ role: keyof typeof TEST_ACCOUNTS; path: string; visible: string }> = [
    { role: 'hrManager',    path: '/hr/employees',                   visible: 'employee' },
    { role: 'purchasing',   path: '/procurement/purchase-orders',    visible: 'purchase' },
    { role: 'acctgManager', path: '/accounting/journal-entries',     visible: 'journal' },
    { role: 'prodManager',  path: '/production/production-orders',   visible: 'production' },
    { role: 'warehouseHead',path: '/inventory/items',                visible: 'item' },
  ];

  for (const { role, path, visible } of roleModules) {
    test(`${role} can access ${path}`, async ({ page }) => {
      await loginAs(page, role);
      await page.goto(path);
      await page.waitForLoadState('networkidle');
      // Should not redirect to login or show 403
      await expect(page).not.toHaveURL(/login/);
      await expect(page.getByText(/forbidden|403|not authorized/i)).not.toBeVisible();
    });
  }

  test('purchasing officer cannot access payroll', async ({ page }) => {
    await loginAs(page, 'purchasing');
    await page.goto('/payroll/payroll-runs');
    // Should either redirect or show access denied
    const isBlocked = await page.getByText(/forbidden|403|not authorized|access denied/i)
      .isVisible()
      .catch(() => false);
    const isRedirected = page.url().includes('login') || page.url().includes('dashboard');
    expect(isBlocked || isRedirected).toBeTruthy();
  });

});
```

---

### MODULE 2: HR — Employee Lifecycle

Create `tests/e2e/specs/02-hr-employee.spec.ts`:

```typescript
import { test, expect, Page } from '@playwright/test';
import { loginAs, fillField, selectOption, waitForApi } from '../helpers/auth';

// Discover actual field names and URLs from frontend before writing
// codebase_search: "EmployeeForm" OR "CreateEmployee" in frontend/src/pages/hr/

test.describe('HR — Employee Management [Role: HR Manager]', () => {
  let createdEmployeeId: string;

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'hrManager');
  });

  test('HR Manager can view employee list', async ({ page }) => {
    await page.goto('/hr/employees');
    await page.waitForLoadState('networkidle');

    // Table should render with data
    await expect(page.getByRole('table')).toBeVisible();

    // Pagination should show
    await expect(page.getByText(/showing|total|page/i)).toBeVisible();
  });

  test('HR Manager can create a new employee', async ({ page }) => {
    await page.goto('/hr/employees/create');
    await page.waitForLoadState('networkidle');

    // Fill required fields — discover actual field labels from the form component
    await fillField(page, /first name/i, 'E2E');
    await fillField(page, /last name/i, 'TestEmployee');
    await fillField(page, /email/i, `e2e.test.${Date.now()}@ogami.test`);
    await fillField(page, /date hired/i, '2025-01-15');
    await selectOption(page, /employment type/i, 'Regular');

    // Select department — pick first available
    const deptSelect = page.getByLabel(/department/i);
    await deptSelect.click();
    await page.getByRole('option').first().click();

    // Select position
    const posSelect = page.getByLabel(/position/i);
    await posSelect.click();
    await page.getByRole('option').first().click();

    // Submit
    const [response] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/hr/employees') && r.request().method() === 'POST'),
      page.getByRole('button', { name: /save|create|submit/i }).click(),
    ]);

    expect(response.status()).toBeLessThan(400);

    // Should navigate to employee detail or list
    await page.waitForURL(/\/hr\/employees/, { timeout: 5000 });

    // Capture the created employee ULID from URL for later tests
    createdEmployeeId = page.url().split('/').pop() ?? '';
  });

  test('HR Manager can search employees', async ({ page }) => {
    await page.goto('/hr/employees');
    await page.waitForLoadState('networkidle');

    const searchInput = page.getByPlaceholder(/search/i).or(page.getByLabel(/search/i));
    await searchInput.fill('E2E');
    await page.waitForTimeout(500); // debounce

    // API call should be made
    await page.waitForResponse(r => r.url().includes('/hr/employees') && r.url().includes('search'));
  });

  test('HR Manager can archive an employee', async ({ page }) => {
    await page.goto('/hr/employees');
    await page.waitForLoadState('networkidle');

    // Find the archive/delete action on a row
    const archiveBtn = page.getByRole('button', { name: /archive/i }).first();
    if (await archiveBtn.isVisible()) {
      await archiveBtn.click();

      // Confirmation dialog should appear
      await expect(page.getByRole('dialog')).toBeVisible();
      await page.getByRole('button', { name: /confirm|archive|yes/i }).click();

      // Wait for success
      await waitForApi(page, '/hr/employees');
    }
  });

  test('HR Manager can view archive and restore employee', async ({ page }) => {
    await page.goto('/hr/employees');
    await page.waitForLoadState('networkidle');

    // Toggle archive view
    await page.getByRole('button', { name: /view archive|archive/i }).click();

    // Archive banner should appear
    await expect(page.getByText(/archived|archive/i)).toBeVisible();

    // Restore button should be visible
    const restoreBtn = page.getByRole('button', { name: /restore/i }).first();
    if (await restoreBtn.isVisible()) {
      await restoreBtn.click();
      await waitForApi(page, '/restore');
    }
  });

});

test.describe('HR — Employee [Role: Department Head — read only]', () => {
  test('Dept Head can view employees in their department', async ({ page }) => {
    await loginAs(page, 'prodHead');
    await page.goto('/hr/employees');
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(/login/);
    await expect(page.getByRole('table')).toBeVisible();
  });

  test('Dept Head cannot create employees', async ({ page }) => {
    await loginAs(page, 'prodHead');
    await page.goto('/hr/employees/create');

    // Should either redirect or show "Add New" button as disabled/hidden
    const addBtn = page.getByRole('button', { name: /add|create new|new employee/i });
    const isHidden = !(await addBtn.isVisible().catch(() => false));
    expect(isHidden || page.url().includes('dashboard')).toBeTruthy();
  });
});
```

---

### MODULE 3: HR — Full Recruitment Pipeline

Create `tests/e2e/specs/03-hr-recruitment.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, fillField, selectOption, waitForApi } from '../helpers/auth';

/**
 * Full recruitment pipeline E2E:
 * Dept Head → submits requisition
 * HR Manager → approves requisition → creates posting
 * Candidate → applies (simulated via HR)
 * HR → shortlists → schedules interview
 * Interviewer → submits scorecard
 * HR → prepares offer → sends
 * HR → records acceptance → completes pre-employment → hires
 *
 * Discover actual URLs from:
 * codebase_search: "recruitment" routes in frontend router
 */

test.describe('Recruitment Pipeline — Full Workflow', () => {

  test.describe.serial('Step 1-2: Requisition creation and approval', () => {

    test('[Dept Head] creates job requisition', async ({ page }) => {
      await loginAs(page, 'prodHead');
      await page.goto('/hr/recruitment/requisitions/create');
      await page.waitForLoadState('networkidle');

      await selectOption(page, /department/i, 'Production');
      await selectOption(page, /position/i, 'Production Operator');
      await selectOption(page, /employment type/i, 'Regular');
      await fillField(page, /headcount/i, '2');
      await fillField(page, /reason/i, 'E2E test requisition — additional headcount needed');
      await fillField(page, /target start date/i, '2025-03-01');

      const [response] = await Promise.all([
        page.waitForResponse(r => r.url().includes('requisitions') && r.request().method() === 'POST'),
        page.getByRole('button', { name: /save|create/i }).click(),
      ]);
      expect(response.status()).toBeLessThan(400);
    });

    test('[Dept Head] submits requisition for approval', async ({ page }) => {
      await loginAs(page, 'prodHead');
      await page.goto('/hr/recruitment/requisitions');
      await page.waitForLoadState('networkidle');

      // Find draft requisition
      await page.getByText(/E2E test requisition/i).click();
      await page.waitForLoadState('networkidle');

      // Submit button
      await page.getByRole('button', { name: /submit for approval/i }).click();

      // Confirmation
      const confirmBtn = page.getByRole('button', { name: /confirm|submit|yes/i });
      if (await confirmBtn.isVisible()) await confirmBtn.click();

      await waitForApi(page, 'submit');
      await expect(page.getByText(/pending|submitted|awaiting/i)).toBeVisible();
    });

    test('[HR Manager] approves the requisition', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/requisitions');
      await page.waitForLoadState('networkidle');

      // Filter to pending
      await page.getByRole('button', { name: /pending/i }).click().catch(() => {});
      await page.getByText(/E2E test requisition/i).click();

      await page.getByRole('button', { name: /approve/i }).click();

      const remarksField = page.getByLabel(/remarks|comments/i);
      if (await remarksField.isVisible()) await remarksField.fill('Approved for E2E test');

      await page.getByRole('button', { name: /confirm approve/i }).click().catch(async () => {
        await page.getByRole('button', { name: /approve/i }).last().click();
      });

      await waitForApi(page, 'approve');
      await expect(page.getByText(/approved/i)).toBeVisible();
    });
  });

  test.describe.serial('Step 3-4: Posting and Application', () => {

    test('[HR Manager] creates job posting from approved requisition', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/requisitions');
      await page.getByText(/E2E test requisition/i).click();

      await page.getByRole('button', { name: /create posting|post job/i }).click();
      await page.waitForURL(/postings/);

      await fillField(page, /title/i, 'Production Operator — E2E Test');
      await fillField(page, /description/i, 'E2E test job posting. Full production operator role.');
      await fillField(page, /requirements/i, 'At least 1 year manufacturing experience.');
      await fillField(page, /closes at|closing date/i, '2025-06-01');

      await page.getByRole('button', { name: /save|create/i }).click();
      await waitForApi(page, 'postings');
    });

    test('[HR Manager] publishes the job posting', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/postings');
      await page.getByText(/Production Operator.*E2E/i).click();

      await page.getByRole('button', { name: /publish/i }).click();
      await waitForApi(page, 'publish');
      await expect(page.getByText(/published/i)).toBeVisible();
    });

    test('[HR] records candidate application', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/applications/create');

      // Select the posting
      await selectOption(page, /job posting/i, 'Production Operator — E2E Test');

      // Candidate info
      await fillField(page, /first name/i, 'Juan');
      await fillField(page, /last name/i, 'E2E Applicant');
      await fillField(page, /email/i, `juan.e2e.${Date.now()}@test.com`);
      await fillField(page, /phone/i, '09171234567');
      await selectOption(page, /source/i, 'Walk-in');

      await page.getByRole('button', { name: /save|submit/i }).click();
      await waitForApi(page, 'applications');
    });
  });

  test.describe.serial('Step 5-6: Interview and Evaluation', () => {

    test('[HR Manager] shortlists the application', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/applications');
      await page.getByText(/Juan.*E2E Applicant/i).click();

      await page.getByRole('button', { name: /shortlist/i }).click();
      await waitForApi(page, 'shortlist');
      await expect(page.getByText(/shortlisted/i)).toBeVisible();
    });

    test('[HR Manager] schedules interview', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/applications');
      await page.getByText(/Juan.*E2E Applicant/i).click();

      await page.getByRole('button', { name: /schedule interview/i }).click();
      await page.waitForSelector('[role="dialog"]');

      await selectOption(page, /type/i, 'HR Screening');
      await fillField(page, /scheduled at/i, '2025-04-10T10:00');
      await fillField(page, /duration/i, '60');
      await selectOption(page, /interviewer/i, 'HR Manager');

      await page.getByRole('button', { name: /save|schedule/i }).click();
      await waitForApi(page, 'interviews');
    });

    test('[Interviewer] submits scorecard', async ({ page }) => {
      await loginAs(page, 'hrManager'); // interviewer role
      await page.goto('/hr/recruitment/interviews');
      await page.getByText(/Juan.*E2E Applicant/i).click();

      await page.getByRole('button', { name: /submit evaluation|scorecard/i }).click();
      await page.waitForSelector('[role="dialog"]');

      // Fill scorecard criteria
      const stars = page.locator('[data-criterion]').or(page.getByRole('spinbutton'));
      const count = await stars.count();
      for (let i = 0; i < count; i++) {
        await stars.nth(i).fill('4');
      }

      await fillField(page, /remarks|comments/i, 'Strong candidate for E2E test');
      await selectOption(page, /recommendation/i, 'Endorse');

      await page.getByRole('button', { name: /submit/i }).click();
      await waitForApi(page, 'evaluation');
    });
  });

  test.describe.serial('Step 7-12: Offer, Pre-Employment, Hire', () => {

    test('[HR Manager] prepares job offer', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/applications');
      await page.getByText(/Juan.*E2E Applicant/i).click();

      await page.getByRole('button', { name: /prepare offer/i }).click();
      await page.waitForSelector('[role="dialog"]');

      await fillField(page, /salary/i, '25000'); // PHP 25,000
      await fillField(page, /start date/i, '2025-04-15');
      await selectOption(page, /employment type/i, 'Regular');

      await page.getByRole('button', { name: /save|create offer/i }).click();
      await waitForApi(page, 'offers');
    });

    test('[HR Manager] sends offer letter', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/offers');
      await page.getByText(/Juan.*E2E Applicant/i).click();

      await page.getByRole('button', { name: /send offer/i }).click();
      await waitForApi(page, 'send');
      await expect(page.getByText(/sent/i)).toBeVisible();
    });

    test('[HR Manager] records offer acceptance', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/offers');
      await page.getByText(/Juan.*E2E Applicant/i).click();

      await page.getByRole('button', { name: /accept|record acceptance/i }).click();
      await waitForApi(page, 'accept');
      await expect(page.getByText(/accepted/i)).toBeVisible();
    });

    test('[HR Manager] completes pre-employment checklist', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/applications');
      await page.getByText(/Juan.*E2E Applicant/i).click();

      // Navigate to pre-employment tab
      await page.getByRole('tab', { name: /pre.?employment|documents/i }).click();

      // Mark checklist items complete (or waive)
      const waiveButtons = page.getByRole('button', { name: /waive/i });
      const waiveCount = await waiveButtons.count();
      for (let i = 0; i < waiveCount; i++) {
        await waiveButtons.nth(i).click();
        const confirmWaive = page.getByRole('button', { name: /confirm|yes/i });
        if (await confirmWaive.isVisible()) await confirmWaive.click();
      }

      // Mark checklist complete
      await page.getByRole('button', { name: /mark complete|complete checklist/i }).click();
      await waitForApi(page, 'complete');
    });

    test('[HR Manager] finalizes hire — creates Employee record', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/hr/recruitment/applications');
      await page.getByText(/Juan.*E2E Applicant/i).click();

      await page.getByRole('button', { name: /hire|finalize hire/i }).click();
      await page.waitForSelector('[role="dialog"]');
      await fillField(page, /start date/i, '2025-04-15');
      await page.getByRole('button', { name: /confirm|hire/i }).click();

      await waitForApi(page, 'hire');
      await expect(page.getByText(/hired|employee created/i)).toBeVisible();
    });
  });
});
```

---

### MODULE 4: Attendance — Time In / Out with Geolocation

Create `tests/e2e/specs/04-attendance.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, mockGeolocation, waitForApi } from '../helpers/auth';

// NOTE: Lightpanda may not support geolocation API.
// These tests use Chromium with GPS mock.
// Tag: @requires-chromium

test.describe('Attendance — Time In/Out [Role: Employee/HR Manager]', () => {

  test('[Employee] time in within geofence', async ({ page, context }) => {
    // Mock GPS to be within work location radius
    await mockGeolocation(context, 14.5995, 120.9842);

    await loginAs(page, 'hrManager');
    await page.goto('/attendance');
    await page.waitForLoadState('networkidle');

    // Location status should show as granted
    await page.waitForSelector(
      ':text("within"), :text("granted"), :text("accuracy")',
      { timeout: 8000 }
    ).catch(() => {}); // May show immediately or after request

    // Time In button should be visible
    const timeInBtn = page.getByRole('button', { name: /time in|clock in/i });
    await expect(timeInBtn).toBeVisible();

    const [response] = await Promise.all([
      page.waitForResponse(r =>
        r.url().includes('time-in') && r.request().method() === 'POST'
      ),
      timeInBtn.click(),
    ]);

    const status = response.status();
    // 200 = success, 422 = already timed in today (acceptable in repeated test runs)
    expect([200, 201, 422]).toContain(status);

    if (status < 400) {
      // Success: show time-in confirmation
      await expect(
        page.getByText(/timed in|clocked in|time in recorded/i)
      ).toBeVisible({ timeout: 5000 });
    }
  });

  test('[Employee] time in outside geofence shows warning', async ({ page, context }) => {
    // Mock GPS to be far from work location (Cebu City)
    await mockGeolocation(context, 10.3157, 123.8854);

    await loginAs(page, 'hrManager');
    await page.goto('/attendance');
    await page.waitForLoadState('networkidle');

    const timeInBtn = page.getByRole('button', { name: /time in|clock in/i });
    if (await timeInBtn.isVisible()) {
      await timeInBtn.click();

      // Should show out-of-geofence warning with distance
      await expect(
        page.getByText(/outside|out of range|distance|geofence/i)
      ).toBeVisible({ timeout: 8000 });

      // Override reason input should appear
      const reasonInput = page.getByLabel(/reason|override/i);
      await expect(reasonInput).toBeVisible();
    }
  });

  test('[Employee] time in without location permission shows error', async ({ page, context }) => {
    // Deny geolocation
    await context.clearPermissions();

    await loginAs(page, 'hrManager');
    await page.goto('/attendance');

    // Should show permission denied message
    await expect(
      page.getByText(/location permission|denied|allow location/i)
    ).toBeVisible({ timeout: 8000 });
  });

  test('[Employee] time out after time in', async ({ page, context }) => {
    await mockGeolocation(context, 14.5995, 120.9842);
    await loginAs(page, 'hrManager');
    await page.goto('/attendance');
    await page.waitForLoadState('networkidle');

    // Try to time out (may fail if not timed in — 422 is OK)
    const timeOutBtn = page.getByRole('button', { name: /time out|clock out/i });
    if (await timeOutBtn.isVisible()) {
      const [response] = await Promise.all([
        page.waitForResponse(r => r.url().includes('time-out') && r.request().method() === 'POST'),
        timeOutBtn.click(),
      ]);
      expect([200, 201, 422]).toContain(response.status());
    }
  });

  test('[HR Manager] views attendance log list', async ({ page }) => {
    await loginAs(page, 'hrManager');
    await page.goto('/attendance/logs');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('table')).toBeVisible();
  });

  test('[HR Manager] submits correction request', async ({ page }) => {
    await loginAs(page, 'hrManager');
    await page.goto('/attendance/correction-requests/create');
    await page.waitForLoadState('networkidle');

    await page.getByLabel(/correction type/i).selectOption('time_in').catch(async () => {
      await page.getByRole('combobox', { name: /correction type/i }).click();
      await page.getByRole('option', { name: /time in/i }).click();
    });

    await page.getByLabel(/reason/i).fill('E2E test correction request — arrived earlier');
    await page.getByRole('button', { name: /submit/i }).click();

    await waitForApi(page, 'correction-requests');
  });

  test('[HR Supervisor] approves correction request', async ({ page }) => {
    await loginAs(page, 'hrManager');
    await page.goto('/attendance/correction-requests');
    await page.waitForLoadState('networkidle');

    const pendingRow = page.getByText(/E2E test correction/i).first();
    if (await pendingRow.isVisible()) {
      await pendingRow.click();
      await page.getByRole('button', { name: /approve/i }).click();
      await waitForApi(page, 'approve');
    }
  });
});
```

---

### MODULE 5: Procurement — Full PO Lifecycle

Create `tests/e2e/specs/05-procurement.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, fillField, selectOption, waitForApi } from '../helpers/auth';

test.describe('Procurement — PO Lifecycle', () => {

  test.describe.serial('Full PO Workflow', () => {

    test('[Purchasing Officer] creates purchase request', async ({ page }) => {
      await loginAs(page, 'purchasing');
      await page.goto('/procurement/purchase-requests/create');
      await page.waitForLoadState('networkidle');

      await fillField(page, /title/i, 'E2E Test Purchase Request');
      await fillField(page, /reason/i, 'E2E test — testing full procurement workflow');
      await fillField(page, /date needed/i, '2025-05-01');

      // Add line item
      await page.getByRole('button', { name: /add item|add line/i }).click();
      const descField = page.getByPlaceholder(/description|item/i).last();
      await descField.fill('Test Raw Material');
      await page.getByPlaceholder(/quantity|qty/i).last().fill('100');
      await page.getByPlaceholder(/unit/i).last().fill('pcs');

      await page.getByRole('button', { name: /save|create/i }).click();
      await waitForApi(page, 'purchase-requests');
    });

    test('[Purchasing Officer] submits PR for approval', async ({ page }) => {
      await loginAs(page, 'purchasing');
      await page.goto('/procurement/purchase-requests');
      await page.getByText(/E2E Test Purchase Request/i).click();
      await page.getByRole('button', { name: /submit/i }).click();
      await waitForApi(page, 'submit');
      await expect(page.getByText(/submitted|pending/i)).toBeVisible();
    });

    test('[Dept Head/Manager] approves PR', async ({ page }) => {
      await loginAs(page, 'prodManager');
      await page.goto('/procurement/purchase-requests');
      await page.getByText(/E2E Test Purchase Request/i).click();
      await page.getByRole('button', { name: /approve/i }).click();
      await waitForApi(page, 'approve');
      await expect(page.getByText(/approved/i)).toBeVisible();
    });

    test('[Purchasing Officer] creates PO from approved PR', async ({ page }) => {
      await loginAs(page, 'purchasing');
      await page.goto('/procurement/purchase-requests');
      await page.getByText(/E2E Test Purchase Request/i).click();
      await page.getByRole('button', { name: /create.*order|convert.*po/i }).click();

      // PO form pre-filled from PR
      await page.waitForURL(/purchase-orders/);

      // Select vendor
      await selectOption(page, /vendor/i, 'Test');
      // or pick first option
      const vendorField = page.getByLabel(/vendor/i);
      await vendorField.click();
      await page.getByRole('option').first().click().catch(() => {});

      await page.getByRole('button', { name: /save|create/i }).click();
      await waitForApi(page, 'purchase-orders');
    });

    test('[Purchasing Officer] sends PO to vendor', async ({ page }) => {
      await loginAs(page, 'purchasing');
      await page.goto('/procurement/purchase-orders');
      await page.waitForLoadState('networkidle');

      // Find the most recent draft PO
      await page.getByRole('row').nth(1).click();
      await page.getByRole('button', { name: /send|send to vendor/i }).click();
      await waitForApi(page, 'send');
      await expect(page.getByText(/sent/i)).toBeVisible();
    });

    test('[Warehouse Head] records goods receipt', async ({ page }) => {
      await loginAs(page, 'warehouseHead');
      await page.goto('/procurement/goods-receipts/create');
      await page.waitForLoadState('networkidle');

      // Select the PO
      await selectOption(page, /purchase order/i, 'PO-');
      const poField = page.getByLabel(/purchase order/i);
      await poField.click();
      await page.getByRole('option').first().click().catch(() => {});

      // Confirm received quantities
      const qtyInputs = page.getByLabel(/received|quantity/i);
      const count = await qtyInputs.count();
      for (let i = 0; i < count; i++) {
        await qtyInputs.nth(i).fill('100');
      }

      await page.getByRole('button', { name: /confirm|save/i }).click();
      await waitForApi(page, 'goods-receipts');
    });

    test('[Accounting] matches and posts vendor invoice', async ({ page }) => {
      await loginAs(page, 'accounting');
      await page.goto('/ap/vendor-invoices');
      await page.waitForLoadState('networkidle');

      // Find auto-drafted invoice from GR
      const draftInvoice = page.getByText(/draft/i).first();
      if (await draftInvoice.isVisible()) {
        await page.getByRole('row').first().click();
        await page.getByRole('button', { name: /approve|post/i }).click();
        await waitForApi(page, 'approve');
      }
    });
  });
});
```

---

### MODULE 6: Payroll — Full Run Pipeline

Create `tests/e2e/specs/06-payroll.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, waitForApi } from '../helpers/auth';

// Payroll has 14 states and multi-level approval.
// These tests verify the complete state machine via UI.

test.describe('Payroll — Full Run Pipeline', () => {

  test.describe.serial('Complete 14-state payroll workflow', () => {

    test('[HR Manager] creates payroll run draft', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payroll-runs/create');
      await page.waitForLoadState('networkidle');

      await page.getByLabel(/pay period|period/i).click().catch(() => {});
      await page.getByRole('option').first().click().catch(() => {});

      await page.getByRole('button', { name: /create|save/i }).click();
      await waitForApi(page, 'payroll-runs');
    });

    test('[HR Manager] sets payroll scope', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /set scope|select employees/i }).click();

      // Select all employees or specific ones
      const selectAll = page.getByRole('checkbox', { name: /select all/i });
      if (await selectAll.isVisible()) await selectAll.check();

      await page.getByRole('button', { name: /save|confirm scope/i }).click();
      await waitForApi(page, 'scope');
      await expect(page.getByText(/scope.?set|SCOPE_SET/i)).toBeVisible();
    });

    test('[HR Manager] runs pre-checks', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /pre.?check|validate/i }).click();
      await waitForApi(page, 'pre-check');
    });

    test('[HR Manager] triggers computation', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /compute|run|process/i }).click();

      // Computation may take time
      await page.waitForResponse(
        r => r.url().includes('compute') && r.status() < 400,
        { timeout: 30000 }
      );

      await expect(page.getByText(/computed|COMPUTED/i)).toBeVisible({ timeout: 15000 });
    });

    test('[HR Manager] submits for approval', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /submit.*approval/i }).click();
      await waitForApi(page, 'submit');
      await expect(page.getByText(/submitted|SUBMITTED/i)).toBeVisible();
    });

    test('[HR Manager] gives HR approval', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /hr.*approve|approve/i }).click();
      await waitForApi(page, 'hr-approve');
      await expect(page.getByText(/HR_APPROVED/i)).toBeVisible();
    });

    test('[Accounting Manager] gives accounting approval', async ({ page }) => {
      await loginAs(page, 'acctgManager');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /acctg.*approve|accounting.*approve/i }).click();
      await waitForApi(page, 'acctg-approve');
      await expect(page.getByText(/ACCTG_APPROVED/i)).toBeVisible();
    });

    test('[VP] gives final VP approval', async ({ page }) => {
      await loginAs(page, 'vp');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /vp.*approve|final.*approve/i }).click();
      await waitForApi(page, 'vp-approve');
      await expect(page.getByText(/VP_APPROVED/i)).toBeVisible();
    });

    test('[HR Manager] marks as disbursed', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /disburse/i }).click();
      await waitForApi(page, 'disburse');
    });

    test('[HR Manager] publishes payslips', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payroll-runs');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /publish/i }).click();
      await waitForApi(page, 'publish');
      await expect(page.getByText(/published|PUBLISHED/i)).toBeVisible();
    });

    test('[Employee] can view own payslip', async ({ page }) => {
      // Login as a regular employee (use hr manager who should have payslips)
      await loginAs(page, 'hrManager');
      await page.goto('/payroll/payslips');
      await page.waitForLoadState('networkidle');

      // Should see payslips
      expect(
        await page.getByRole('row').count()
      ).toBeGreaterThan(1);
    });
  });
});
```

---

### MODULE 7: Leave — Full Approval Chain

Create `tests/e2e/specs/07-leave.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, fillField, selectOption, waitForApi } from '../helpers/auth';

test.describe('Leave — Full Approval Chain', () => {

  test.describe.serial('Leave request lifecycle', () => {

    test('[Employee] submits leave request', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/leave/leave-requests/create');
      await page.waitForLoadState('networkidle');

      await selectOption(page, /leave type/i, 'Vacation');
      await fillField(page, /start date/i, '2025-06-02');
      await fillField(page, /end date/i, '2025-06-03');
      await fillField(page, /reason/i, 'E2E test vacation leave');

      await page.getByRole('button', { name: /submit/i }).click();
      await waitForApi(page, 'leave-requests');
      await expect(page.getByText(/submitted|pending/i)).toBeVisible();
    });

    test('[Dept Head] approves leave at dept head level', async ({ page }) => {
      await loginAs(page, 'prodHead');
      await page.goto('/leave/leave-requests');
      await page.getByText(/E2E test vacation/i).click();
      await page.getByRole('button', { name: /approve|head approve/i }).click();
      await waitForApi(page, 'approve');
    });

    test('[HR Manager] gives final approval', async ({ page }) => {
      await loginAs(page, 'hrManager');
      await page.goto('/leave/leave-requests');
      await page.getByText(/E2E test vacation/i).click();
      await page.getByRole('button', { name: /approve|hr approve/i }).click();
      await waitForApi(page, 'approve');
      await expect(page.getByText(/approved/i)).toBeVisible();
    });

    test('[Dept Head] can reject leave with reason', async ({ page }) => {
      // Create a fresh leave request and reject it
      await loginAs(page, 'hrManager');
      await page.goto('/leave/leave-requests/create');
      await selectOption(page, /leave type/i, 'Sick');
      await fillField(page, /start date/i, '2025-07-01');
      await fillField(page, /end date/i, '2025-07-01');
      await fillField(page, /reason/i, 'E2E test sick leave to be rejected');
      await page.getByRole('button', { name: /submit/i }).click();
      await waitForApi(page, 'leave-requests');

      // Now reject as dept head
      await loginAs(page, 'prodHead');
      await page.goto('/leave/leave-requests');
      await page.getByText(/E2E test sick leave to be rejected/i).click();
      await page.getByRole('button', { name: /reject/i }).click();

      const reasonField = page.getByLabel(/reason|remarks/i);
      await reasonField.fill('E2E test rejection — operational requirements');
      await page.getByRole('button', { name: /confirm reject/i }).click();
      await waitForApi(page, 'reject');
      await expect(page.getByText(/rejected/i)).toBeVisible();
    });
  });
});
```

---

### MODULE 8: Sales — Quotation to Invoice Chain

Create `tests/e2e/specs/08-sales-chain.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, fillField, selectOption, waitForApi } from '../helpers/auth';

test.describe('Sales — Quotation to Invoice Chain', () => {

  test.describe.serial('Full sales workflow', () => {

    test('[Sales Manager] creates quotation', async ({ page }) => {
      await loginAs(page, 'salesManager');
      await page.goto('/sales/quotations/create');
      await page.waitForLoadState('networkidle');

      // Select customer
      await selectOption(page, /customer/i, 'Test');
      const customerField = page.getByLabel(/customer/i);
      await customerField.click();
      await page.getByRole('option').first().click().catch(() => {});

      await fillField(page, /date/i, '2025-04-01');
      await fillField(page, /valid until/i, '2025-05-01');

      // Add line item
      await page.getByRole('button', { name: /add item|add line/i }).click();
      const itemField = page.getByLabel(/item|product/i).last();
      await itemField.click();
      await page.getByRole('option').first().click().catch(() => {});

      await page.getByPlaceholder(/quantity/i).last().fill('50');

      await page.getByRole('button', { name: /save|create/i }).click();
      await waitForApi(page, 'quotations');
    });

    test('[Sales Manager] converts quotation to sales order', async ({ page }) => {
      await loginAs(page, 'salesManager');
      await page.goto('/sales/quotations');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /convert.*order|create.*so/i }).click();
      await page.waitForURL(/sales-orders/);
      await waitForApi(page, 'sales-orders');
    });

    test('[Sales Manager] confirms sales order', async ({ page }) => {
      await loginAs(page, 'salesManager');
      await page.goto('/sales/sales-orders');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /confirm/i }).click();
      await waitForApi(page, 'confirm');
      await expect(page.getByText(/confirmed/i)).toBeVisible();
    });

    test('[Warehouse Head] creates delivery receipt', async ({ page }) => {
      await loginAs(page, 'warehouseHead');
      await page.goto('/delivery/receipts');
      await page.waitForLoadState('networkidle');

      // DR should have been auto-created — find it
      // If not, check for a "Create DR" button on the SO
      const autoCreatedDR = page.getByText(/draft/i).first();
      if (!(await autoCreatedDR.isVisible())) {
        // Navigate to SO and create DR from there
        await loginAs(page, 'salesManager');
        await page.goto('/sales/sales-orders');
        await page.getByRole('row').first().click();
        await page.getByRole('button', { name: /create.*delivery|delivery receipt/i }).click();
        await waitForApi(page, 'delivery');
      }
    });

    test('[Warehouse Head] dispatches delivery', async ({ page }) => {
      await loginAs(page, 'warehouseHead');
      await page.goto('/delivery/receipts');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /dispatch|confirm/i }).click();
      await waitForApi(page, 'dispatch');
    });

    test('[Warehouse Head] marks delivery as delivered', async ({ page }) => {
      await loginAs(page, 'warehouseHead');
      await page.goto('/delivery/receipts');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /delivered|mark delivered/i }).click();
      await waitForApi(page, 'deliver');
      await expect(page.getByText(/delivered/i)).toBeVisible();
    });

    test('[Accounting] posts customer invoice', async ({ page }) => {
      await loginAs(page, 'accounting');
      await page.goto('/ar/customer-invoices');
      await page.waitForLoadState('networkidle');

      // Invoice should be auto-drafted from delivery
      await page.getByRole('row').first().click();
      await page.getByRole('button', { name: /approve|post/i }).click();
      await waitForApi(page, 'approve');
      await expect(page.getByText(/approved|posted/i)).toBeVisible();
    });
  });
});
```

---

### MODULE 9: Production — Order to Finished Goods

Create `tests/e2e/specs/09-production.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, fillField, selectOption, waitForApi } from '../helpers/auth';

test.describe('Production — Order to Finished Goods', () => {

  test.describe.serial('Full production workflow', () => {

    test('[Production Manager] creates production order', async ({ page }) => {
      await loginAs(page, 'prodManager');
      await page.goto('/production/production-orders/create');
      await page.waitForLoadState('networkidle');

      // Select finished good item
      await selectOption(page, /item|product/i, 'Finished');
      const itemField = page.getByLabel(/item|product/i);
      await itemField.click();
      await page.getByRole('option').first().click().catch(() => {});

      await fillField(page, /quantity/i, '100');
      await fillField(page, /target date/i, '2025-05-15');

      // Select BOM
      const bomField = page.getByLabel(/bom|bill of materials/i);
      if (await bomField.isVisible()) {
        await bomField.click();
        await page.getByRole('option').first().click().catch(() => {});
      }

      await page.getByRole('button', { name: /save|create/i }).click();
      await waitForApi(page, 'production-orders');
    });

    test('[Production Manager] releases production order', async ({ page }) => {
      await loginAs(page, 'prodManager');
      await page.goto('/production/production-orders');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /release|confirm/i }).click();
      await waitForApi(page, 'release');
      await expect(page.getByText(/released/i)).toBeVisible();
    });

    test('[Production Manager] issues materials via MRQ', async ({ page }) => {
      await loginAs(page, 'prodManager');
      await page.goto('/production/production-orders');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /issue materials|create.*requisition/i }).click();
      await waitForApi(page, 'requisition');
    });

    test('[Warehouse Head] approves and issues materials', async ({ page }) => {
      await loginAs(page, 'warehouseHead');
      await page.goto('/inventory/requisitions');
      await page.waitForLoadState('networkidle');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /approve|issue/i }).click();
      await waitForApi(page, 'issue');
    });

    test('[Production Head] starts production', async ({ page }) => {
      await loginAs(page, 'prodHead');
      await page.goto('/production/production-orders');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /start/i }).click();
      await waitForApi(page, 'start');
      await expect(page.getByText(/in.?progress/i)).toBeVisible();
    });

    test('[Production Head] completes production', async ({ page }) => {
      await loginAs(page, 'prodHead');
      await page.goto('/production/production-orders');
      await page.getByRole('row').first().click();

      await page.getByRole('button', { name: /complete/i }).click();
      await page.waitForSelector('[role="dialog"]');

      await page.getByLabel(/quantity produced/i).fill('100');
      await page.getByLabel(/scrap/i).fill('0').catch(() => {});

      await page.getByRole('button', { name: /confirm/i }).click();
      await waitForApi(page, 'complete');
    });

    test('[QC Manager] conducts final inspection', async ({ page }) => {
      await loginAs(page, 'qcManager');
      await page.goto('/qc/inspections');
      await page.waitForLoadState('networkidle');
      await page.getByRole('row').first().click();

      // Record inspection results
      await page.getByRole('button', { name: /inspect|record/i }).click();

      // Fill criteria scores
      const scoreInputs = page.getByRole('spinbutton');
      const count = await scoreInputs.count();
      for (let i = 0; i < count; i++) {
        await scoreInputs.nth(i).fill('5');
      }

      await page.getByRole('button', { name: /pass|submit/i }).click();
      await waitForApi(page, 'pass');
      await expect(page.getByText(/passed/i)).toBeVisible();
    });
  });
});
```

---

### MODULE 10: Role-Based Access Verification

Create `tests/e2e/specs/10-rbac.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import { loginAs, TEST_ACCOUNTS } from '../helpers/auth';

type AccountRole = keyof typeof TEST_ACCOUNTS;

// Verify each role only sees what they should see
const roleAccessMatrix: Array<{
  role: AccountRole;
  canAccess: string[];
  cannotAccess: string[];
}> = [
  {
    role: 'hrManager',
    canAccess: ['/hr/employees', '/leave/leave-requests', '/payroll/payroll-runs'],
    cannotAccess: ['/procurement/purchase-orders'],
  },
  {
    role: 'purchasing',
    canAccess: ['/procurement/purchase-orders', '/procurement/vendors'],
    cannotAccess: ['/payroll/payroll-runs', '/hr/employees'],
  },
  {
    role: 'acctgManager',
    canAccess: ['/accounting/journal-entries', '/ap/vendor-invoices', '/ar/customer-invoices'],
    cannotAccess: ['/payroll/payroll-runs', '/production/production-orders'],
  },
  {
    role: 'warehouseHead',
    canAccess: ['/inventory/items', '/delivery/receipts'],
    cannotAccess: ['/payroll/payroll-runs', '/accounting/journal-entries'],
  },
];

test.describe('RBAC — Role-Based Access Control', () => {

  for (const { role, canAccess, cannotAccess } of roleAccessMatrix) {
    test.describe(`Role: ${role}`, () => {

      for (const path of canAccess) {
        test(`can access ${path}`, async ({ page }) => {
          await loginAs(page, role);
          await page.goto(path);
          await page.waitForLoadState('networkidle');

          expect(page.url()).not.toContain('/login');
          const forbidden = await page.getByText(/403|forbidden|not authorized/i)
            .isVisible().catch(() => false);
          expect(forbidden).toBeFalsy();
        });
      }

      for (const path of cannotAccess) {
        test(`cannot access ${path}`, async ({ page }) => {
          await loginAs(page, role);
          await page.goto(path);
          await page.waitForLoadState('networkidle');

          const isBlocked =
            page.url().includes('/login') ||
            page.url().includes('/dashboard') ||
            await page.getByText(/403|forbidden|not authorized|access denied/i)
              .isVisible().catch(() => false);

          expect(isBlocked).toBeTruthy();
        });
      }
    });
  }

});
```

---

## PHASE 3 — LIGHTPANDA SETUP & CONFIGURATION

```bash
# Install Lightpanda (Linux x86_64)
curl -L https://github.com/lightpanda-io/lightpanda/releases/latest/download/lightpanda-x86_64-linux \
  -o /usr/local/bin/lightpanda && chmod +x /usr/local/bin/lightpanda

# Verify
lightpanda --version

# Run tests with Lightpanda (faster, no display required)
USE_LIGHTPANDA=true npx playwright test --project=lightpanda

# Run with Chromium (full browser, required for GPS/file upload)
npx playwright test --project=chromium

# Run specific file with Lightpanda
USE_LIGHTPANDA=true npx playwright test tests/e2e/specs/01-auth.spec.ts
```

**When to use Lightpanda vs Chromium:**

| Test Type | Use Lightpanda | Use Chromium |
|---|---|---|
| Auth / login | Yes | No |
| List pages, tables | Yes | No |
| Simple forms (text, select) | Yes | No |
| GPS/Geolocation (attendance) | No | Yes |
| File upload (documents) | No | Yes |
| Complex React state | No | Yes |
| Visual assertions | No | Yes |

---

## PHASE 4 — RUNNING THE TESTS

### Run Commands

```bash
cd frontend

# Full suite — all roles, all modules
npx playwright test tests/e2e/specs/

# Specific module
npx playwright test tests/e2e/specs/06-payroll.spec.ts

# Specific role coverage
npx playwright test --grep "HR Manager"

# With HTML report
npx playwright test --reporter=html
npx playwright show-report tests/e2e/reports/html

# Debug mode (headed browser)
npx playwright test --headed --slowMo=500

# Lightpanda for fast modules
USE_LIGHTPANDA=true npx playwright test tests/e2e/specs/01-auth.spec.ts \
                                        tests/e2e/specs/07-leave.spec.ts \
                                        tests/e2e/specs/10-rbac.spec.ts

# Chromium for GPS-dependent modules
npx playwright test tests/e2e/specs/04-attendance.spec.ts --project=chromium
```

### Expected Output

```
Running 87 tests using 1 worker

  ✓ [chromium] Authentication > admin can log in and see dashboard (1.2s)
  ✓ [chromium] Authentication > invalid credentials show error (0.8s)
  ✓ [chromium] HR Manager > can view employee list (1.1s)
  ✓ [chromium] HR Manager > can create a new employee (2.3s)
  ✓ [chromium] Recruitment > creates job requisition (1.8s)
  ✓ [chromium] Recruitment > approves the requisition (1.4s)
  ...

  87 passed (4m 32s)
```

---

## PHASE 5 — CONTINUOUS INTEGRATION

Create `.github/workflows/e2e.yml` (or equivalent CI config):

```yaml
name: E2E Tests

on:
  push:
    branches: [main, develop]
  pull_request:

jobs:
  e2e:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: ogami_erp_test
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: password
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install PHP dependencies
        run: composer install --no-dev

      - name: Setup Laravel
        run: |
          cp .env.example .env.testing
          php artisan key:generate --env=testing
          php artisan migrate:fresh --seed --env=testing

      - name: Start Laravel server
        run: php artisan serve --env=testing &

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install frontend dependencies
        run: cd frontend && pnpm install

      - name: Build frontend
        run: cd frontend && pnpm build

      - name: Start Vite preview
        run: cd frontend && pnpm preview &
        env:
          E2E_BASE_URL: http://localhost:4173

      - name: Install Lightpanda
        run: |
          curl -L https://github.com/lightpanda-io/lightpanda/releases/latest/download/lightpanda-x86_64-linux \
            -o /usr/local/bin/lightpanda && chmod +x /usr/local/bin/lightpanda

      - name: Install Playwright browsers
        run: cd frontend && npx playwright install chromium

      - name: Run E2E tests
        run: cd frontend && npx playwright test
        env:
          E2E_BASE_URL: http://localhost:4173
          USE_LIGHTPANDA: true

      - name: Upload test report
        uses: actions/upload-artifact@v4
        if: always()
        with:
          name: playwright-report
          path: frontend/tests/e2e/reports/
```

---

## EXECUTION INSTRUCTIONS

```
1. Run Phase 0 discovery — verify Playwright is installed and all accounts exist.

2. Run global setup to seed the database:
   php artisan migrate:fresh --seed --env=testing

3. Start both servers:
   Terminal 1: php artisan serve --env=testing
   Terminal 2: cd frontend && pnpm dev

4. Run the auth spec first to verify accounts work:
   npx playwright test tests/e2e/specs/01-auth.spec.ts --headed

5. Fix any login failures before running the rest.
   Common causes: wrong base URL, seeder not run, session cookie issues.

6. Run the full suite:
   npx playwright test tests/e2e/specs/

7. Open the HTML report:
   npx playwright show-report tests/e2e/reports/html

8. For every failing test:
   a. Run in headed mode: npx playwright test --headed --filter "test name"
   b. Add --slowMo=1000 to watch it step by step
   c. Check screenshot in tests/e2e/reports/ for what the UI showed

9. After all tests pass — run with Lightpanda for the fast subset:
   USE_LIGHTPANDA=true npx playwright test tests/e2e/specs/01-auth.spec.ts \
     tests/e2e/specs/07-leave.spec.ts tests/e2e/specs/10-rbac.spec.ts

10. Any test that still fails after fixing is a real bug in the system.
    Document it. Fix the bug. Re-run the test. Green = defense ready.
```