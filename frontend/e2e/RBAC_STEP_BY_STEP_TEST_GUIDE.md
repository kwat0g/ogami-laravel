# RBAC E2E Tests - Step-by-Step Testing Guide

Complete guide to verify all 40 RBAC tests one by one.

## Quick Reference

| Section | Tests | Command |
|---------|-------|---------|
| 1. HR | 4 | `-g "HR Department"` |
| 2. Accounting | 4 | `-g "Accounting Department"` |
| 3. Production | 5 | `-g "Production Department"` |
| 4. Warehouse | 3 | `-g "Warehouse Department"` |
| 5. QC | 3 | `-g "QC Department"` |
| 6. Procurement | 2 | `-g "Procurement Department"` |
| 7. Executive | 2 | `-g "Executive Roles"` |
| 8. Admin | 3 | `-g "Admin Role"` |
| 9. Cross-Cutting | 11 | `-g "Cross-Cutting"` |
| 10. Action Buttons | 5 | `-g "Action Button"` |
| 11. Summary | 1 | `-g "Verify all roles"` |

## Pre-Test Setup

### 1. Start Servers

Terminal 1 (Backend):
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan serve
```

Terminal 2 (Frontend):
```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm dev
```

### 2. Clear Rate Limits

Terminal 3:
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan cache:clear
php artisan tinker --execute="Illuminate\Support\Facades\Cache::flush();"
```

### 3. Verify Accounts

```bash
php artisan db:seed --class=ManufacturingEmployeeSeeder
```

---

## SECTION 1: HR Department (4 tests)

```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend
```

### Test 1.1: HR Manager Sidebar
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "HR Manager - Sidebar Navigation" --reporter=line
```
Expected: PASS

### Test 1.2: HR Manager Page Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "HR Manager - Page Access and Actions" --reporter=line
```
Expected: PASS

### Test 1.3: HR Head Limited Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "HR Head - Limited Access" --reporter=line
```
Expected: PASS

### Test 1.4: HR Staff Minimal Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "HR Staff - Minimal Access" --reporter=line
```
Expected: PASS

---

## SECTION 2: Accounting Department (4 tests)

### Test 2.1: Accounting Manager Full Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Accounting Manager - Full Accounting Access" --reporter=line
```
Expected: PASS

### Test 2.2: Accounting Manager Page Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Accounting Manager - Page Access" --reporter=line
```
Expected: PASS

### Test 2.3: Accounting Officer Banking
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Accounting Officer - Banking Access" --reporter=line
```
Expected: PASS

### Test 2.4: Accounting Head View Only
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Accounting Head - View Only Access" --reporter=line
```
Expected: PASS

---

## SECTION 3: Production Department (5 tests)

**Clear rate limits first:**
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan cache:clear
```

### Test 3.1: Production Manager No Payroll
```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Manager - No Payroll Access" --reporter=line
```
Expected: PASS  
CRITICAL: Verifies Production blocked from Payroll

### Test 3.2: Production Manager No Inventory
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Manager - No Inventory Categories Access" --reporter=line
```
Expected: PASS  
CRITICAL: Verifies Production blocked from Inventory Categories

### Test 3.3: Production Manager Can Access Production
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Manager - Can Access Production" --reporter=line
```
Expected: PASS

### Test 3.4: Production Head Limited Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Head - Limited Production Access" --reporter=line
```
Expected: PASS

### Test 3.5: Production Manager No Create on Inventory
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Manager - No Create Button on Inventory" --reporter=line
```
Expected: PASS

### Test 3.6: Production Manager Has Create on Production
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Manager - Has Create Button on Production" --reporter=line
```
Expected: PASS

---

## SECTION 4: Warehouse Department (3 tests)

### Test 4.1: Warehouse Head Full Inventory Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Warehouse Head - Full Inventory Access" --reporter=line
```
Expected: PASS  
CRITICAL: Verifies Warehouse CAN access Inventory

### Test 4.2: Warehouse Head All Inventory Pages
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Warehouse Head - Can Access All Inventory Pages" --reporter=line
```
Expected: PASS

### Test 4.3: Warehouse Head Create Button
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Warehouse Head - Has Create Button on Inventory" --reporter=line
```
Expected: PASS

---

## SECTION 5: QC Department (3 tests)

**Clear rate limits:**
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan cache:clear
```

### Test 5.1: QC Manager Access
```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "QC Manager - QC and Production Access" --reporter=line
```
Expected: PASS

### Test 5.2: QC Manager Page Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "QC Manager - Page Access" --reporter=line
```
Expected: PASS

### Test 5.3: QC Blocked from Payroll
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "QC accessing Payroll should be BLOCKED" --reporter=line
```
Expected: PASS

---

## SECTION 6: Procurement Department (2 tests)

### Test 6.1: Purchasing Officer Procurement
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Purchasing Officer - Procurement Access" --reporter=line
```
Expected: PASS

### Test 6.2: Purchasing Officer Page Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Purchasing Officer - Page Access" --reporter=line
```
Expected: PASS

---

## SECTION 7: Executive Roles (2 tests)

**Clear rate limits:**
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan cache:clear
```

### Test 7.1: VP Wide Access
```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "VP - Wide Access but No Payroll/HR" --reporter=line
```
Expected: PASS

### Test 7.2: Executive Limited Access
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Executive - Limited Module Access" --reporter=line
```
Expected: PASS

---

## SECTION 8: Admin Role (3 tests)

### Test 8.1: Admin System Only
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Admin - System Administration Only" --reporter=line
```
Expected: PASS

### Test 8.2: Admin Can Access Admin Pages
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Admin - Can Access Admin Pages" --reporter=line
```
Expected: PASS

### Test 8.3: Admin Cannot Access Business
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Admin - Cannot Access Business Modules" --reporter=line
```
Expected: PASS

---

## SECTION 9: Cross-Cutting Forbidden Access (11 tests)

**Clear rate limits before running:**
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan cache:clear
```

### Run All Cross-Cutting Tests
```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Cross-Cutting Forbidden Access" --reporter=line
```
Expected: All 11 tests PASS

Individual tests:
- Production accessing Payroll should be BLOCKED
- Production accessing Inventory Categories should be BLOCKED
- Production accessing Accounting should be BLOCKED
- HR accessing Inventory should be BLOCKED
- HR accessing Production should be BLOCKED
- Accounting accessing Production should be BLOCKED
- Accounting Officer accessing Payroll should be BLOCKED
- Warehouse accessing Payroll should be BLOCKED
- Warehouse accessing Production should be BLOCKED
- Sales accessing AP should be BLOCKED
- Mold accessing QC should be BLOCKED

---

## SECTION 10: Action Button Visibility (5 tests)

### Test 10.1: Production Manager No Create on Inventory
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Manager - No Create Button on Inventory" --reporter=line
```
Expected: PASS

### Test 10.2: Warehouse Head Has Create on Inventory
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Warehouse Head - Has Create Button on Inventory" --reporter=line
```
Expected: PASS

### Test 10.3: HR Manager Has Create on Employees
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "HR Manager - Has Create Button on Employees" --reporter=line
```
Expected: PASS

### Test 10.4: Production Manager Has Create on Production
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Manager - Has Create Button on Production" --reporter=line
```
Expected: PASS

### Test 10.5: Accounting Officer Has Create on AP
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Accounting Officer - Has Create Button on AP" --reporter=line
```
Expected: PASS

---

## SECTION 11: Summary Test (1 test)

### Test 11.1: All Roles Can Login
```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Verify all roles can login and access dashboard" --reporter=line
```
Expected: PASS (takes 2-3 minutes)

---

## Quick Commands by Department

### Run All HR Tests
```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "HR Department" --reporter=line
```

### Run All Accounting Tests
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Accounting Department" --reporter=line
```

### Run All Production Tests
```bash
cd /home/kwat0g/Desktop/ogamiPHP && php artisan cache:clear
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Production Department" --reporter=line
```

### Run All Warehouse Tests
```bash
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Warehouse Department" --reporter=line
```

### Run All Cross-Cutting Tests
```bash
cd /home/kwat0g/Desktop/ogamiPHP && php artisan cache:clear
cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 \
  -g "Cross-Cutting" --reporter=line
```

---

## Full Suite Run

### All 40 Tests
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan cache:clear

cd /home/kwat0g/Desktop/ogamiPHP/frontend
pnpm exec playwright test e2e/rbac-comprehensive-ui.spec.ts --workers=1 --reporter=line
```
Expected: 40/40 PASS  
Duration: 10-15 minutes

---

## Troubleshooting

### Account Locked
```bash
php artisan tinker --execute="App\Models\User::where('email', 'prod.manager@ogamierp.local')->update(['failed_login_attempts' => 0, 'locked_until' => null]);"
```

### Rate Limited
```bash
php artisan cache:clear
php artisan tinker --execute="Illuminate\Support\Facades\Cache::flush();"
```

### Test Timeouts
Edit `playwright.config.ts`:
```typescript
actionTimeout: 60000,
navigationTimeout: 60000,
```

---

## Test Results Checklist

Copy and fill out as you test:

```
Date: ___________

Section 1: HR Department
[ ] Test 1.1  HR Manager - Sidebar Navigation
[ ] Test 1.2  HR Manager - Page Access and Actions
[ ] Test 1.3  HR Head - Limited Access
[ ] Test 1.4  HR Staff - Minimal Access

Section 2: Accounting Department
[ ] Test 2.1  Accounting Manager - Full Accounting Access
[ ] Test 2.2  Accounting Manager - Page Access
[ ] Test 2.3  Accounting Officer - Banking Access
[ ] Test 2.4  Accounting Head - View Only Access

Section 3: Production Department
[ ] Test 3.1  Production Manager - No Payroll Access (CRITICAL)
[ ] Test 3.2  Production Manager - No Inventory Categories Access (CRITICAL)
[ ] Test 3.3  Production Manager - Can Access Production
[ ] Test 3.4  Production Head - Limited Production Access
[ ] Test 3.5  Production Manager - No Create Button on Inventory
[ ] Test 3.6  Production Manager - Has Create Button on Production

Section 4: Warehouse Department
[ ] Test 4.1  Warehouse Head - Full Inventory Access (CRITICAL)
[ ] Test 4.2  Warehouse Head - Can Access All Inventory Pages
[ ] Test 4.3  Warehouse Head - Has Create Button on Inventory

Section 5: QC Department
[ ] Test 5.1  QC Manager - QC and Production Access
[ ] Test 5.2  QC Manager - Page Access
[ ] Test 5.3  QC accessing Payroll should be BLOCKED

Section 6: Procurement Department
[ ] Test 6.1  Purchasing Officer - Procurement Access
[ ] Test 6.2  Purchasing Officer - Page Access

Section 7: Executive Roles
[ ] Test 7.1  VP - Wide Access but No Payroll/HR
[ ] Test 7.2  Executive - Limited Module Access

Section 8: Admin Role
[ ] Test 8.1  Admin - System Administration Only
[ ] Test 8.2  Admin - Can Access Admin Pages
[ ] Test 8.3  Admin - Cannot Access Business Modules

Section 9: Cross-Cutting Forbidden Access (11 tests)
[ ] Test 9.1  Production accessing Payroll should be BLOCKED (CRITICAL)
[ ] Test 9.2  Production accessing Inventory Categories should be BLOCKED (CRITICAL)
[ ] Test 9.3  Production accessing Accounting should be BLOCKED
[ ] Test 9.4  HR accessing Inventory should be BLOCKED
[ ] Test 9.5  HR accessing Production should be BLOCKED
[ ] Test 9.6  Accounting accessing Production should be BLOCKED
[ ] Test 9.7  Accounting Officer accessing Payroll should be BLOCKED
[ ] Test 9.8  Warehouse accessing Payroll should be BLOCKED
[ ] Test 9.9  Warehouse accessing Production should be BLOCKED
[ ] Test 9.10 Sales accessing AP should be BLOCKED
[ ] Test 9.11 Mold accessing QC should be BLOCKED

Section 10: Action Button Visibility
[ ] Test 10.1 Production Manager - No Create Button on Inventory
[ ] Test 10.2 Warehouse Head - Has Create Button on Inventory
[ ] Test 10.3 HR Manager - Has Create Button on Employees
[ ] Test 10.4 Production Manager - Has Create Button on Production
[ ] Test 10.5 Accounting Officer - Has Create Button on AP

Section 11: Summary
[ ] Test 11.1 Verify all roles can login and access dashboard

TOTAL: ___/40 tests passed
```
