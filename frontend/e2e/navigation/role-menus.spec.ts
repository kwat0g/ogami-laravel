/**
 * Navigation — Role-Based Sidebar Visibility (Non-Admin Roles)
 * 
 * Tests that each role sees ONLY the modules they have permission for.
 * Based on ModulePermissionSeeder permission matrix.
 */
import { test, expect } from '@playwright/test';
import { loginAs, logout, getSidebarText, NonAdminRole } from '../helpers/auth';

// Permission matrix from ModulePermissionSeeder (NON-ADMIN ROLES ONLY)
const ROLE_MODULE_ACCESS: Record<NonAdminRole, { see: string[]; hide: string[] }> = {
  // HR Department
  hr_manager: {
    see: ['Team Management', 'Human Resources', 'Payroll', 'Reports'],
    hide: ['Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking', 'Production', 'QC / QA', 'Inventory'],
  },
  hr_officer: {
    see: ['Team Management', 'Human Resources'],
    hide: ['Payroll', 'Accounting', 'Production', 'Inventory'],
  },
  hr_head: {
    see: ['Team Management'],
    hide: ['Human Resources', 'Payroll', 'Accounting', 'Production', 'Inventory'],
  },
  hr_staff: {
    see: [],
    hide: ['Team Management', 'Human Resources', 'Payroll', 'Accounting', 'Production', 'Inventory'],
  },

  // Accounting Department
  acctg_manager: {
    see: ['Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking', 'Financial Reports', 'Fixed Assets', 'Budget'],
    hide: ['Payroll', 'Production', 'QC / QA', 'Inventory'],
  },
  acctg_officer: {
    see: ['Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking', 'Financial Reports'],
    hide: ['Payroll', 'Production', 'Inventory'],
  },
  acctg_head: {
    see: ['Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking'],
    hide: ['Payroll', 'Production', 'Inventory', 'Financial Reports'],
  },

  // Production Department
  prod_manager: {
    see: ['Production', 'QC / QA', 'Maintenance', 'Mold', 'Delivery', 'ISO / IATF'],
    hide: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
  },
  prod_head: {
    see: ['Production', 'QC / QA', 'Maintenance', 'Mold', 'Delivery', 'ISO / IATF'],
    hide: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
  },
  prod_staff: {
    see: ['Production'],
    hide: ['Payroll', 'Accounting', 'QC / QA', 'Maintenance', 'Mold', 'Inventory'],
  },

  // Warehouse Department
  wh_head: {
    see: ['Inventory'],
    hide: ['Payroll', 'Accounting', 'Production', 'QC / QA'],
  },

  // Plant & Operations
  plant_manager: {
    see: ['Production', 'QC / QA', 'Maintenance', 'Mold', 'Delivery', 'ISO / IATF'],
    hide: ['Payroll', 'Accounting', 'Banking'],
  },
  qc_manager: {
    see: ['QC / QA', 'Production'],
    hide: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
  },
  mold_manager: {
    see: ['Mold', 'Production', 'QC / QA'],
    hide: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
  },
  maintenance_manager: {
    see: ['Maintenance', 'Production'],
    hide: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
  },

  // Sales & Purchasing
  sales_manager: {
    see: ['CRM'],
    hide: ['Payroll', 'Accounting', 'Production', 'Inventory', 'Procurement'],
  },
  purchasing_officer: {
    see: ['Procurement'],
    hide: ['Payroll', 'Accounting', 'Production', 'Inventory'],
  },

  // Executive
  vp: {
    see: ['VP Approvals'],
    hide: ['Payroll', 'Accounting', 'Production', 'Procurement'],
  },
  executive: {
    see: ['Reports'],
    hide: ['Payroll', 'Accounting', 'Production', 'Procurement'],
  },
};

test.describe('🧭 Navigation — Sidebar by Role', () => {
  
  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  for (const [role, access] of Object.entries(ROLE_MODULE_ACCESS)) {
    const roleName = role as NonAdminRole;
    
    test(`${roleName} sees correct modules in sidebar`, async ({ page }) => {
      await loginAs(page, roleName);
      const sidebarText = await getSidebarText(page);
      
      // Should see these modules
      for (const module of access.see) {
        expect(sidebarText, `${roleName} should see "${module}"`)
          .toContain(module);
      }
      
      // Should NOT see these modules
      for (const module of access.hide) {
        expect(sidebarText, `${roleName} should NOT see "${module}"`)
          .not.toContain(module);
      }
    });
  }
});

test.describe('🔒 Navigation — Direct URL Access Control', () => {
  
  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  // Production roles should NOT access Payroll
  const PRODUCTION_ROLES: NonAdminRole[] = ['prod_manager', 'prod_head', 'prod_staff'];
  
  for (const role of PRODUCTION_ROLES) {
    test(`${role} cannot access Payroll via direct URL`, async ({ page }) => {
      await loginAs(page, role);
      await page.goto('/payroll/runs');
      
      // Should be redirected to 403 or dashboard
      await page.waitForTimeout(1000);
      const url = page.url();
      
      expect(url, `${role} should be blocked from Payroll`).not.toContain('/payroll/runs');
    });
  }

  // Production roles should NOT access Inventory Categories
  for (const role of PRODUCTION_ROLES) {
    test(`${role} cannot access Inventory Categories via direct URL`, async ({ page }) => {
      await loginAs(page, role);
      await page.goto('/inventory/categories');
      
      await page.waitForTimeout(1000);
      const url = page.url();
      
      expect(url, `${role} should be blocked from Inventory Categories`).not.toContain('/inventory/categories');
    });
  }

  // Warehouse should NOT access Production
  test('wh_head cannot access Production via direct URL', async ({ page }) => {
    await loginAs(page, 'wh_head');
    await page.goto('/production/orders');
    
    await page.waitForTimeout(1000);
    const url = page.url();
    
    expect(url).not.toContain('/production/orders');
  });
});

test.describe('📋 Navigation — Module Expansion', () => {
  
  test.afterEach(async ({ page }) => {
    await logout(page);
  });

  test('Warehouse Head can expand Inventory and see Categories', async ({ page }) => {
    await loginAs(page, 'wh_head');
    
    // Click on Inventory to expand
    await page.click('text=Inventory');
    await page.waitForTimeout(500);
    
    // Should see management options
    await expect(page.locator('text=Item Categories')).toBeVisible();
    await expect(page.locator('text=Warehouse Locations')).toBeVisible();
    await expect(page.locator('text=Stock Balances')).toBeVisible();
  });

  test('Production Manager sees Production sub-modules', async ({ page }) => {
    await loginAs(page, 'prod_manager');
    
    await page.click('text=Production');
    await page.waitForTimeout(500);
    
    await expect(page.locator('text=Bill of Materials')).toBeVisible();
    await expect(page.locator('text=Work Orders')).toBeVisible();
    await expect(page.locator('text=Delivery Schedules')).toBeVisible();
  });

  test('HR Manager sees Payroll sub-menu', async ({ page }) => {
    await loginAs(page, 'hr_manager');
    
    await page.click('text=Payroll');
    await page.waitForTimeout(500);
    
    await expect(page.locator('text=Payroll Runs')).toBeVisible();
    await expect(page.locator('text=Pay Periods')).toBeVisible();
  });
});
