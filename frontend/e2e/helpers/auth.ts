/**
 * Authentication helpers for E2E tests
 * Non-admin roles only
 */
import type { Page, APIRequestContext } from '@playwright/test';

type Credential = {
  email: string;
  password: string;
};

const successfulCredentialByRole = new Map<string, Credential>();

// Test credentials from ManufacturingEmployeeSeeder (NON-ADMIN ONLY)
export const NON_ADMIN_CREDENTIALS = {
  // HR Department
  hr_manager: {
    email: 'hr.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  hr_officer: {
    email: 'hr.officer@ogamierp.local',
    password: 'Officer@12345!',
  },
  hr_head: {
    email: 'hr.head@ogamierp.local',
    password: 'Head@123456789!',
  },
  hr_staff: {
    email: 'prod.staff@ogamierp.local',
    password: 'Staff@123456789!',
  },

  // Accounting Department
  acctg_manager: {
    email: 'acctg.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  acctg_officer: {
    email: 'accounting@ogamierp.local',
    password: 'Officer@12345!',
  },
  acctg_head: {
    email: 'acctg.head@ogamierp.local',
    password: 'Head@123456789!',
  },

  // Production Department
  prod_manager: {
    email: 'prod.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  prod_head: {
    email: 'production.head@ogamierp.local',
    password: 'Head@123456789!',
  },
  prod_staff: {
    email: 'prod.staff@ogamierp.local',
    password: 'Staff@123456789!',
  },

  // Warehouse Department
  wh_head: {
    email: 'warehouse.head@ogamierp.local',
    password: 'Head@123456789!',
  },

  // Plant & Operations
  plant_manager: {
    email: 'it.admin@ogamierp.local',
    password: 'Manager@12345!',
  },
  qc_manager: {
    email: 'qc.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  mold_manager: {
    email: 'prod.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  maintenance_manager: {
    email: 'maintenance.head@ogamierp.local',
    password: 'Head@123456789!',
  },

  // Sales & Purchasing
  sales_manager: {
    email: 'sales.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  purchasing_officer: {
    email: 'purchasing.officer@ogamierp.local',
    password: 'Officer@12345!',
  },

  // Executive (non-admin)
  vp: {
    email: 'vp@ogamierp.local',
    password: 'VicePresident@1!',
  },
  executive: {
    email: 'chairman@ogamierp.local',
    password: 'Executive@12345!',
  },
} as const;

export type NonAdminRole = keyof typeof NON_ADMIN_CREDENTIALS;

export const TEST_ACCOUNTS = {
  admin: {
    email: 'admin@ogamierp.local',
    password: 'Admin@12345!',
    fallbacks: [{ email: 'admin@ogamierp.local', password: 'Admin@12345!' }],
  },
  superAdmin: {
    email: 'superadmin@ogamierp.local',
    password: 'SuperAdmin@12345!',
    fallbacks: [],
  },
  executive: {
    email: 'chairman@ogamierp.local',
    password: 'Executive@12345!',
    fallbacks: [
      { email: 'president@ogamierp.local', password: 'Executive@12345!' },
    ],
  },
  vp: {
    email: 'vp@ogamierp.local',
    password: 'VicePresident@1!',
    fallbacks: [{ email: 'vp@ogamierp.local', password: 'Vice_president@Test1234!' }],
  },
  hrManager: {
    email: 'hr.manager@ogamierp.local',
    password: 'Manager@12345!',
    fallbacks: [
      { email: 'hr.manager@ogamierp.local', password: 'Manager@12345!' },
    ],
  },
  accountingOfficer: {
    email: 'accounting@ogamierp.local',
    password: 'Officer@12345!',
    fallbacks: [{ email: 'acctg.head@ogamierp.local', password: 'Head@123456789!' }],
  },
  productionManager: {
    email: 'prod.manager@ogamierp.local',
    password: 'Manager@12345!',
    fallbacks: [{ email: 'prod.manager@ogamierp.local', password: 'Manager@Test1234!' }],
  },
  qcManager: {
    email: 'qc.manager@ogamierp.local',
    password: 'Manager@12345!',
    fallbacks: [{ email: 'qc.manager@ogamierp.local', password: 'Manager@Test1234!' }],
  },
  moldManager: {
    email: 'mold.manager@ogamierp.local',
    password: 'Manager@12345!',
    fallbacks: [{ email: 'mold.manager@ogamierp.local', password: 'Manager@Test1234!' }],
  },
  crmManager: {
    email: 'sales.manager@ogamierp.local',
    password: 'Manager@12345!',
    fallbacks: [
      { email: 'sales.manager@ogamierp.local', password: 'Manager@12345!' },
    ],
  },
  productionHead: {
    email: 'production.head@ogamierp.local',
    password: 'Head@123456789!',
    fallbacks: [{ email: 'prod.head@ogamierp.local', password: 'Head@Test1234!' }],
  },
  warehouseHead: {
    email: 'warehouse.head@ogamierp.local',
    password: 'Head@123456789!',
    fallbacks: [{ email: 'wh.head@ogamierp.local', password: 'Head@Test1234!' }],
  },
  purchasingOfficer: {
    email: 'purchasing.officer@ogamierp.local',
    password: 'Officer@12345!',
    fallbacks: [{ email: 'purch.officer@ogamierp.local', password: 'Officer@Test1234!' }],
  },
} as const;

export type AccountRole = keyof typeof TEST_ACCOUNTS;

const BASE_URL = process.env.FRONTEND_URL || 'http://localhost:5173';
const API_URL = process.env.API_URL || 'http://localhost:8000';

async function submitLogin(page: Page, credential: Credential): Promise<boolean> {
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded', timeout: 15000 });

  if (/dashboard|vendor-portal|client-portal|change-password/.test(page.url())) {
    // Reuse existing authenticated session to avoid repeated login throttling.
    return true;
  }

  const emailInput = page.locator('input[type="email"]').first();
  if (!(await emailInput.isVisible({ timeout: 10000 }).catch(() => false))) {
    return false;
  }

  await emailInput.fill(credential.email);
  await page.locator('input[type="password"]').first().fill(credential.password);
  await page.getByRole('button', { name: /login|sign in/i }).click();

  try {
    await page.waitForURL(/dashboard|vendor-portal|client-portal|change-password/, { timeout: 15000 });
    return true;
  } catch {
    const bodyText = ((await page.locator('body').textContent().catch(() => null)) ?? '').toLowerCase();
    if (bodyText.includes('too many') || bodyText.includes('throttle') || bodyText.includes('rate limit')) {
      await page.waitForTimeout(1600);
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded', timeout: 15000 });
      if (await emailInput.isVisible({ timeout: 10000 }).catch(() => false)) {
        await emailInput.fill(credential.email);
        await page.locator('input[type="password"]').first().fill(credential.password);
        await page.getByRole('button', { name: /login|sign in/i }).click();
        try {
          await page.waitForURL(/dashboard|vendor-portal|client-portal|change-password/, { timeout: 15000 });
          return true;
        } catch {
          return false;
        }
      }
    }
    return false;
  }
}

/**
 * Login using role aliases from TEST_ACCOUNTS.
 * Uses fallback credentials when seeded account passwords vary by seeder chain.
 */
export async function loginAsRole(page: Page, role: AccountRole): Promise<void> {
  await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => {})
  if (!page.url().includes('/login')) {
    return
  }

  throw new Error(
    `Pre-authenticated storage state is missing for role ${role}. Run setup project first and avoid per-test login loops.`
  )

  const cachedCredential = successfulCredentialByRole.get(role);
  if (cachedCredential && (await submitLogin(page, cachedCredential))) {
    try {
      await page.waitForLoadState('domcontentloaded', { timeout: 5000 });
    } catch {
      // Some routes keep fetching data; URL-based success check already passed.
    }
    return;
  }

  const primary = TEST_ACCOUNTS[role];
  const candidates: Credential[] = [
    { email: primary.email, password: primary.password },
    ...(primary.fallbacks ?? []),
  ];

  for (const candidate of candidates) {
    if (await submitLogin(page, candidate)) {
      successfulCredentialByRole.set(role, candidate);
      try {
        await page.waitForLoadState('domcontentloaded', { timeout: 5000 });
      } catch {
        // Some routes keep fetching data; URL-based success check already passed.
      }
      return;
    }
  }

  throw new Error(`Login failed for role ${role} after trying ${candidates.length} credential set(s).`);
}

/**
 * Login via UI
 */
export async function loginAs(page: Page, role: NonAdminRole): Promise<void> {
  const creds = NON_ADMIN_CREDENTIALS[role];
  const ok = await submitLogin(page, { email: creds.email, password: creds.password });
  if (!ok) {
    throw new Error(`Login failed for non-admin role ${role}.`);
  }
  await page.waitForSelector('nav, aside, main', { timeout: 10000 });
}

/**
 * Login via API (faster for setup)
 */
export async function loginViaApi(
  request: APIRequestContext, 
  role: NonAdminRole
): Promise<{ token: string; user: Record<string, unknown> }> {
  const creds = NON_ADMIN_CREDENTIALS[role];
  
  // Get CSRF cookie
  await request.get(`${API_URL}/sanctum/csrf-cookie`);
  
  // Login
  const response = await request.post(`${API_URL}/api/v1/auth/login`, {
    data: {
      email: creds.email,
      password: creds.password,
    },
  });

  if (!response.ok()) {
    throw new Error(`Login failed for ${role}: ${await response.text()}`);
  }

  return await response.json();
}

/**
 * Clear session
 */
export async function logout(page: Page): Promise<void> {
  try {
    await page.request.post(`${API_URL}/api/v1/auth/logout`);
  } catch {
    // Keep going; client-side clear below handles stale state as fallback.
  }

  try {
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
  } catch {
    // Page may be in the middle of a navigation; proceed to explicit login route.
  }

  try {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded', timeout: 7000 });
  } catch {
    // Avoid hard-failing tests due to browser navigation stalls on teardown.
  }
}

/**
 * Get auth state from API
 */
export async function getAuthMe(
  request: APIRequestContext,
  token: string
): Promise<{ user: { id: number; email: string; roles: string[]; permissions: string[] } }> {
  const response = await request.get(`${API_URL}/api/v1/auth/me`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (!response.ok()) {
    throw new Error(`Failed to get auth me: ${await response.text()}`);
  }

  return await response.json();
}

/**
 * Test route access via API
 */
export async function testRouteAccess(
  request: APIRequestContext,
  token: string,
  method: 'GET' | 'POST' | 'PUT' | 'DELETE',
  route: string,
  data?: Record<string, unknown>
): Promise<number> {
  const url = `${API_URL}/api/v1${route}`;
  
  let response;
  switch (method) {
    case 'GET':
      response = await request.get(url, {
        headers: { 'Authorization': `Bearer ${token}` },
      });
      break;
    case 'POST':
      response = await request.post(url, {
        headers: { 'Authorization': `Bearer ${token}` },
        data: data || {},
      });
      break;
    case 'PUT':
      response = await request.put(url, {
        headers: { 'Authorization': `Bearer ${token}` },
        data: data || {},
      });
      break;
    case 'DELETE':
      response = await request.delete(url, {
        headers: { 'Authorization': `Bearer ${token}` },
      });
      break;
  }

  return response.status();
}

/**
 * Get sidebar navigation text
 */
export async function getSidebarText(page: Page): Promise<string> {
  const sidebar = page.locator('aside, nav').first();
  return await sidebar.innerText();
}

/**
 * Check if element exists in sidebar
 */
export async function hasNavItem(page: Page, itemText: string): Promise<boolean> {
  const nav = page.locator('aside, nav').first();
  const items = await nav.locator('text=' + itemText).count();
  return items > 0;
}
