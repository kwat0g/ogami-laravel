/**
 * Authentication helpers for E2E tests
 * Non-admin roles only
 */
import type { Page, APIRequestContext } from '@playwright/test';

// Test credentials from ManufacturingEmployeeSeeder (NON-ADMIN ONLY)
export const NON_ADMIN_CREDENTIALS = {
  // HR Department
  hr_manager: {
    email: 'hr.manager@ogamierp.local',
    password: 'Manager@Test1234!',
  },
  hr_officer: {
    email: 'hr.officer@ogamierp.local',
    password: 'Officer@Test1234!',
  },
  hr_head: {
    email: 'hr.head@ogamierp.local',
    password: 'Head@Test1234!',
  },
  hr_staff: {
    email: 'hr.staff@ogamierp.local',
    password: 'Staff@Test1234!',
  },

  // Accounting Department
  acctg_manager: {
    email: 'acctg.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  acctg_officer: {
    email: 'acctg.officer@ogamierp.local',
    password: 'Officer@Test1234!',
  },
  acctg_head: {
    email: 'acctg.head@ogamierp.local',
    password: 'Head@Test1234!',
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
    email: 'plant.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  qc_manager: {
    email: 'qc.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  mold_manager: {
    email: 'mold.manager@ogamierp.local',
    password: 'Manager@12345!',
  },
  maintenance_manager: {
    email: 'maintenance.head@ogamierp.local',
    password: 'Head@123456789!',
  },

  // Sales & Purchasing
  sales_manager: {
    email: 'crm.manager@ogamierp.local',
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
    email: 'executive@ogamierp.local',
    password: 'Executive@Test1234!',
  },
} as const;

export type NonAdminRole = keyof typeof NON_ADMIN_CREDENTIALS;

const BASE_URL = process.env.FRONTEND_URL || 'http://localhost:5173';
const API_URL = process.env.API_URL || 'http://localhost:8000';

/**
 * Login via UI
 */
export async function loginAs(page: Page, role: NonAdminRole): Promise<void> {
  const creds = NON_ADMIN_CREDENTIALS[role];
  
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[type="email"]', creds.email);
  await page.fill('input[type="password"]', creds.password);
  await page.click('button[type="submit"]');
  
  // Wait for navigation to dashboard
  await page.waitForURL(/dashboard/, { timeout: 15000 });
  
  // Wait for sidebar to be ready
  await page.waitForSelector('nav, aside', { timeout: 10000 });
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
  await page.evaluate(() => {
    localStorage.clear();
    sessionStorage.clear();
  });
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
